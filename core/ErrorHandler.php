<?php
namespace Core;

use ErrorException, RuntimeException, Throwable, finfo;

// Tüm hataları tek yerden yönetir. bootstrap.php içinde ErrorHandler::register() ile açılır.
//
// - PHP uyarıları (warning, notice) exception'a çevrilir; deprecated uyarıları sadece log'a yazılır.
// - Fatal hatalar (bellek taşması, parse hatası) da yakalanır.
// - 500 hataları storage/logs/ altına hata koduyla yazılır.
// - debug açıkken: kod parçası, stack trace ve istek bilgileriyle detaylı sayfa.
// - debug kapalıyken: kullanıcıya sade hata sayfası + hata kodu (log'da aramak için).
// - AJAX / JSON isteklerine JSON döner.
//
// Hata sayfasının görünümü: ErrorHandler::setView('errors/error', 'site');  (app/Hooks.php içinde)
// 404 için Router::notFound(...) varsa o kullanılır.
class ErrorHandler
{
    private const FATAL = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    private static ?string $view = null;
    /** @var string|callable|null */
    private static $layout = null;
    private static bool $handling = false;
    private static ?string $reserved = null;

    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '0');

        // Bellek taşmasında hata sayfasını gösterebilmek için ayrılan yedek alan
        self::$reserved = str_repeat(' ', 32 * 1024);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    // Kullanıcıya gösterilecek hata sayfası (debug kapalıyken). View'a $status, $title, $message, $errorId gelir.
    // $layout: layout adı ya da adı döndüren fonksiyon (ör. admin panelinde farklı layout)
    public static function setView(string $view, string|callable|null $layout = null): void
    {
        self::$view = $view;
        self::$layout = $layout;
    }

    public static function handleError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $level)) return false; // @ ile susturulmuş

        if (in_array($level, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            Log::warning("Deprecated: $message", ['file' => "$file:$line"]);
            return true;
        }

        throw new ErrorException($message, 0, $level, $file, $line);
    }

    public static function handleShutdown(): void
    {
        self::$reserved = null;
        $e = error_get_last();
        if ($e && in_array($e['type'], self::FATAL, true)) {
            // Bellek taşmasında hata sayfasını çizebilmek için limiti biraz aç
            if (str_starts_with($e['message'], 'Allowed memory size')) {
                ini_set('memory_limit', (string) (memory_get_usage(true) + 64 * 1024 * 1024));
            }
            self::handleException(new ErrorException($e['message'], 0, $e['type'], $e['file'], $e['line']));
        }
    }

    public static function handleException(Throwable $e): void
    {
        // Hata sayfası çizilirken yeni hata çıkarsa sonsuz döngüye girme
        if (self::$handling) {
            echo self::plainPage(500, HttpException::text(500), null);
            return;
        }
        self::$handling = true;

        $status  = $e instanceof HttpException ? $e->status() : 500;
        $errorId = $status >= 500 ? Log::exception($e) : null;
        $debug   = (bool) Config::get('app.debug', false);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n");
            exit(1);
        }

        try {
            // Yarım kalmış sayfa çıktısını at, temiz bir sayfa göster
            while (ob_get_level() > 0) ob_end_clean();
            if (!headers_sent()) {
                header_remove();
                // Fatal hatadan sonra sunucu durum kodunu kendisi ayarlamış olabilir; uyarıyı sustur
                @http_response_code($status);
            }

            if (self::wantsJson()) {
                echo self::json($e, $status, $errorId, $debug);
            } elseif ($debug && $status >= 500) {
                if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
                echo self::debugPage($e, $errorId);
            } else {
                if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
                echo self::userPage($e, $status, $errorId);
            }
        } catch (Throwable $inner) {
            Log::exception($inner);
            echo self::plainPage($status, HttpException::text($status), $errorId);
        }
    }

    // ---------------------------------------------------------------- yanıt türleri

    private static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    private static function json(Throwable $e, int $status, ?string $errorId, bool $debug): string
    {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');

        $data = ['error' => self::publicMessage($e, $status), 'status' => $status];
        if ($errorId) $data['error_id'] = $errorId;
        if ($debug && $status >= 500) {
            $data['exception'] = get_class($e);
            $data['message']   = $e->getMessage();
            $data['file']      = $e->getFile() . ':' . $e->getLine();
            $data['trace']     = array_map(fn($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . $f['function'] . '()', $e->getTrace());
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private static function userPage(Throwable $e, int $status, ?string $errorId): string
    {
        if ($status === 404 && ($page = Router::renderNotFound()) !== null) return $page;

        $data = [
            'status'  => $status,
            'title'   => HttpException::text($status),
            'message' => self::publicMessage($e, $status),
            'errorId' => $errorId,
        ];

        if (self::$view) {
            try {
                $layout = is_callable(self::$layout) ? (self::$layout)() : self::$layout;
                return (string) View::render(self::$view, $data)->setLayout($layout);
            } catch (Throwable) {
                // hata sayfası da yazdırılamadıysa (ör. tema bozuk) sade sayfa
            }
        }
        return self::plainPage($status, $data['message'], $errorId);
    }

    // HttpException mesajı kullanıcıya gösterilebilir; diğer hataların detayı asla gösterilmez.
    private static function publicMessage(Throwable $e, int $status): string
    {
        return $e instanceof HttpException ? $e->getMessage() : HttpException::text($status);
    }

    private static function plainPage(int $status, string $message, ?string $errorId): string
    {
        $code = $errorId ? '<p style="color:#94a3b8;font-size:13px">Hata kodu: #' . $errorId . '</p>' : '';
        return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $status . '</title></head>'
            . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;background:#f8fafc;color:#0f172a;text-align:center">'
            . '<div><p style="font-size:64px;font-weight:700;color:#cbd5e1;margin:0">' . $status . '</p><h1 style="font-size:20px">' . self::e($message) . '</h1>' . $code . '</div></body></html>';
    }

    // ---------------------------------------------------------------- debug sayfası

    private static function debugPage(Throwable $e, ?string $errorId): string
    {
        $frames = [['file' => $e->getFile(), 'line' => $e->getLine(), 'call' => 'Hatanın oluştuğu yer']];
        foreach ($e->getTrace() as $f) {
            if (!isset($f['file'])) continue;
            $frames[] = ['file' => $f['file'], 'line' => $f['line'] ?? 0, 'call' => ($f['class'] ?? '') . ($f['type'] ?? '') . $f['function'] . '()'];
        }

        $framesHtml = '';
        foreach ($frames as $i => $f) {
            $app = !str_contains(str_replace('\\', '/', $f['file']), '/core/');
            $framesHtml .= '<details' . ($i === 0 ? ' open' : '') . ' class="frame' . ($app ? ' app' : '') . '"><summary>'
                . '<span class="num">' . $i . '</span><span class="call">' . self::e($f['call']) . '</span>'
                . '<span class="loc">' . self::e(self::short($f['file'])) . ':' . $f['line'] . '</span></summary>'
                . self::snippet($f['file'], $f['line']) . '</details>';
        }

        $previous = '';
        for ($p = $e->getPrevious(); $p; $p = $p->getPrevious()) {
            $previous .= '<div class="prev"><b>Önceki: ' . self::e(get_class($p)) . '</b> ' . self::e($p->getMessage())
                . ' <span class="loc">' . self::e(self::short($p->getFile())) . ':' . $p->getLine() . '</span></div>';
        }

        $request = [
            'İstek'   => ['Method' => $_SERVER['REQUEST_METHOD'] ?? '', 'URL' => $_SERVER['REQUEST_URI'] ?? '', 'IP' => $_SERVER['REMOTE_ADDR'] ?? '',
                          'PHP' => PHP_VERSION, 'Bellek' => round(memory_get_peak_usage() / 1048576, 1) . ' MB'],
            'GET'     => $_GET,
            'POST'    => Log::mask($_POST),
            'Dosyalar'=> array_map(fn($f) => is_array($f['name']) ? implode(', ', $f['name']) : $f['name'], $_FILES),
            'Session' => Log::mask($_SESSION ?? []),
            'Headers' => Log::mask(function_exists('getallheaders') ? (getallheaders() ?: []) : []),
        ];
        $requestHtml = '';
        foreach ($request as $title => $rows) {
            $requestHtml .= '<h3>' . $title . '</h3>' . self::table($rows);
        }

        $type    = self::e($e instanceof ErrorException ? self::levelName($e->getSeverity()) : get_class($e));
        $message = self::e($e->getMessage());
        $where   = self::e(self::short($e->getFile())) . ':' . $e->getLine();
        $id      = $errorId ? '<span class="id">#' . $errorId . ' · storage/logs/' . date('Y-m-d') . '.log</span>' : '';

        return <<<HTML
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$type}</title>
<style>
  *{box-sizing:border-box} body{margin:0;font:14px/1.5 system-ui,sans-serif;background:#f1f5f9;color:#0f172a}
  header{background:#0f172a;color:#fff;padding:28px 32px}
  header .type{display:inline-block;background:#dc2626;color:#fff;font-size:12px;font-weight:600;padding:3px 10px;border-radius:6px}
  header h1{margin:12px 0 6px;font-size:22px;font-weight:600;word-break:break-word}
  header .where{color:#94a3b8;font-family:ui-monospace,Consolas,monospace;font-size:13px}
  header .id{float:right;color:#64748b;font-size:12px;font-family:ui-monospace,Consolas,monospace}
  .prev{margin-top:10px;color:#fca5a5;font-size:13px}
  main{max-width:1200px;margin:0 auto;padding:24px 32px;display:grid;gap:24px;grid-template-columns:minmax(0,3fr) minmax(0,2fr)}
  @media (max-width:900px){main{grid-template-columns:1fr;padding:16px}}
  section{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden}
  section>h2{margin:0;padding:12px 16px;font-size:13px;font-weight:600;border-bottom:1px solid #e2e8f0;background:#f8fafc}
  .frame{border-bottom:1px solid #f1f5f9} .frame:last-child{border-bottom:0}
  .frame summary{cursor:pointer;padding:10px 16px;display:flex;gap:10px;align-items:baseline;list-style:none}
  .frame summary:hover{background:#f8fafc} .frame[open] summary{background:#eef2ff}
  .frame:not(.app) summary{opacity:.6}
  .num{color:#94a3b8;font-size:12px;min-width:18px} .call{font-weight:600;font-family:ui-monospace,Consolas,monospace;font-size:13px}
  .loc{color:#64748b;font-family:ui-monospace,Consolas,monospace;font-size:12px;margin-left:auto;text-align:right;word-break:break-all}
  pre{margin:0;background:#0f172a;color:#e2e8f0;font:12.5px/1.6 ui-monospace,Consolas,monospace;overflow:auto;padding:8px 0}
  pre span{display:block;padding:0 16px;white-space:pre} pre .hl{background:#7f1d1d;color:#fff}
  pre i{display:inline-block;width:42px;color:#64748b;font-style:normal;user-select:none}
  h3{margin:0;padding:10px 16px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b;background:#f8fafc;border-top:1px solid #e2e8f0}
  table{width:100%;border-collapse:collapse;font-size:12.5px} td{padding:6px 16px;border-top:1px solid #f1f5f9;vertical-align:top;word-break:break-all}
  td:first-child{width:35%;color:#475569;font-family:ui-monospace,Consolas,monospace} td.empty{color:#94a3b8}
</style>
</head>
<body>
<header>
  {$id}
  <span class="type">{$type}</span>
  <h1>{$message}</h1>
  <div class="where">{$where}</div>
  {$previous}
</header>
<main>
  <section><h2>Stack trace</h2>{$framesHtml}</section>
  <section><h2>İstek</h2>{$requestHtml}</section>
</main>
</body>
</html>
HTML;
    }

    // ---------------------------------------------------------------- yardımcılar

    private static function snippet(string $file, int $line, int $around = 8): string
    {
        if (!is_file($file) || !is_readable($file)) return '';
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $start = max(1, $line - $around);
        $end   = min(count($lines), $line + $around);

        $html = '<pre>';
        for ($n = $start; $n <= $end; $n++) {
            $html .= '<span' . ($n === $line ? ' class="hl"' : '') . '><i>' . $n . '</i>' . self::e($lines[$n - 1] ?? '') . '</span>';
        }
        return $html . '</pre>';
    }

    private static function table(array $rows): string
    {
        if (!$rows) return '<table><tr><td class="empty" colspan="2">boş</td></tr></table>';
        $html = '<table>';
        foreach ($rows as $k => $v) {
            $v = is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            $html .= '<tr><td>' . self::e($k) . '</td><td>' . nl2br(self::e($v)) . '</td></tr>';
        }
        return $html . '</table>';
    }

    private static function levelName(int $level): string
    {
        return [
            E_WARNING => 'Warning', E_NOTICE => 'Notice', E_USER_WARNING => 'User Warning', E_USER_NOTICE => 'User Notice',
            E_ERROR => 'Fatal Error', E_PARSE => 'Parse Error', E_CORE_ERROR => 'Core Error', E_COMPILE_ERROR => 'Compile Error',
            E_USER_ERROR => 'User Error', E_RECOVERABLE_ERROR => 'Recoverable Error',
        ][$level] ?? 'Error';
    }

    private static function short(string $file): string
    {
        return ltrim(str_replace([ROOT, '\\'], ['', '/'], $file), '/');
    }

    private static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
