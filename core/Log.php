<?php
namespace Core;

use ErrorException, RuntimeException, Throwable, finfo;

// Günlük log dosyaları: storage/logs/2026-11-09.log
//
//   Log::info('Kullanıcı giriş yaptı', ['user_id' => 5]);
//   Log::warning('Ödeme gecikti', ['order' => 12]);
//   Log::error('API yanıt vermedi', ['url' => $url]);
//
// Hatalar ErrorHandler tarafından otomatik yazılır.
class Log
{
    private const HIDDEN = ['password', 'pass', 'pwd', '_token', 'token', 'secret', 'api_key', 'authorization'];

    public static function info(string $message, array $context = []): void    { self::write('INFO', $message, $context); }
    public static function warning(string $message, array $context = []): void { self::write('WARNING', $message, $context); }
    public static function error(string $message, array $context = []): void   { self::write('ERROR', $message, $context); }

    // Hata kaydı. Dönen kod kullanıcıya gösterilir, log'da aranır: "#a1b2c3d4"
    public static function exception(Throwable $e): string
    {
        $id = bin2hex(random_bytes(4));
        $text = get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')';

        $trace = $e->getTraceAsString();
        for ($p = $e->getPrevious(); $p; $p = $p->getPrevious()) {
            $trace .= "\nÖnceki: " . get_class($p) . ': ' . $p->getMessage() . ' (' . $p->getFile() . ':' . $p->getLine() . ')';
        }

        self::write('ERROR', "#$id $text", [], $trace);
        return $id;
    }

    // Hassas alanları gizler: ['password' => '***']
    public static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) $data[$key] = self::mask($value);
            elseif (in_array(strtolower((string) $key), self::HIDDEN, true)) $data[$key] = '***';
        }
        return $data;
    }

    private static function write(string $level, string $message, array $context, string $trace = ''): void
    {
        $dir = rtrim(Config::get('app.log.path', ROOT . '/storage/logs'), '/\\');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return;

        $request = PHP_SAPI === 'cli' ? ['cli' => implode(' ', $_SERVER['argv'] ?? [])] : array_filter([
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'url'    => $_SERVER['REQUEST_URI'] ?? null,
            'ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
            'user'   => $_SESSION['user']['id'] ?? null,
        ]);

        $line = sprintf("[%s] %s %s %s\n", date('Y-m-d H:i:s'), $level, $message,
            json_encode(self::mask($context) + $request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($trace !== '') $line .= $trace . "\n";

        @file_put_contents($dir . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
