<?php
namespace Core;

use RuntimeException;
use Throwable;

// E-posta gönderimi. Ayarlar: Admin > Ayarlar > E-posta (sürücü, SMTP sunucusu, gönderen).
//
//   Mail::send('ali@site.com', 'Konu', "Düz metin");
//   Mail::send(['a@x.com', 'b@x.com'], 'Konu', 'Düz metin', ['html' => '<p>HTML</p>', 'reply_to' => 'musteri@x.com']);
//   if (!Mail::send(...)) Mail::lastError();
//
// Sürücüler:
//   smtp  SMTP sunucusu (Gmail, Yandex, hosting e-postası...): SSL (465) ya da STARTTLS (587), kullanıcı adı/şifre
//   mail  PHP mail() (sunucuda sendmail ayarlıysa)
//   log   gönderilmez, storage/logs/mail.log dosyasına yazılır (geliştirme için)
final class Mail
{
    private static ?string $lastError = null;

    public static function send(string|array $to, string $subject, string $text, array $options = []): bool
    {
        self::$lastError = null;
        try {
            $to = array_values(array_filter(array_map('trim', (array) $to)));
            foreach ($to as $address) self::assertEmail($address);
            if (!$to) throw new RuntimeException('Alıcı yok');

            $from = (string) (Setting::get('mail_from_address') ?: 'noreply@' . self::host());
            self::assertEmail($from);
            $fromName = self::clean((string) (Setting::get('mail_from_name') ?: Setting::get('site_name', 'Site')));
            $replyTo = $options['reply_to'] ?? null;
            if ($replyTo !== null) self::assertEmail($replyTo);

            $message = self::build($to, $from, $fromName, self::clean($subject), $text, $options['html'] ?? null, $replyTo);

            match ((string) Setting::get('mail_driver', 'mail')) {
                'smtp'  => self::smtp($from, $to, $message),
                'log'   => self::log($message),
                default => self::phpMail($to, $subject, $message),
            };
            return true;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            Log::warning('E-posta gönderilemedi', ['to' => $to, 'subject' => $subject, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    // ================================================================ mesaj

    private static function build(array $to, string $from, string $fromName, string $subject, string $text, ?string $html, ?string $replyTo): array
    {
        $headers = [
            'Date'         => date('r'),
            'From'         => self::encode($fromName) . " <$from>",
            'To'           => implode(', ', $to),
            'Subject'      => self::encode($subject),
            'Message-ID'   => '<' . bin2hex(random_bytes(12)) . '@' . self::host() . '>',
            'MIME-Version' => '1.0',
        ];
        if ($replyTo) $headers['Reply-To'] = $replyTo;

        if ($html === null) {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
            $headers['Content-Transfer-Encoding'] = 'base64';
            $body = chunk_split(base64_encode($text));
        } else {
            $boundary = 'b' . bin2hex(random_bytes(10));
            $headers['Content-Type'] = "multipart/alternative; boundary=\"$boundary\"";
            $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
                  . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
                  . "--$boundary--\r\n";
        }
        return ['headers' => $headers, 'body' => $body];
    }

    private static function render(array $message, bool $withToSubject = true): string
    {
        $lines = [];
        foreach ($message['headers'] as $name => $value) {
            if (!$withToSubject && in_array($name, ['To', 'Subject'], true)) continue;
            $lines[] = "$name: $value";
        }
        return implode("\r\n", $lines) . "\r\n\r\n" . $message['body'];
    }

    // ================================================================ sürücüler

    private static function smtp(string $from, array $to, array $message): void
    {
        $host = (string) Setting::get('mail_host');
        $port = (int) Setting::get('mail_port', 587);
        $encryption = (string) Setting::get('mail_encryption', 'tls');
        if ($host === '') throw new RuntimeException('SMTP sunucusu ayarlanmamış (Ayarlar > E-posta)');

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) throw new RuntimeException("SMTP sunucusuna bağlanılamadı ($host:$port): $errstr");
        stream_set_timeout($socket, 15);

        try {
            self::expect($socket, 220);
            $ehlo = 'EHLO ' . self::host();
            self::command($socket, $ehlo, 250);
            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('TLS başlatılamadı');
                }
                self::command($socket, $ehlo, 250);
            }
            if (($user = (string) Setting::get('mail_username')) !== '') {
                self::command($socket, 'AUTH LOGIN', 334);
                self::command($socket, base64_encode($user), 334);
                self::command($socket, base64_encode((string) Setting::get('mail_password')), 235, 'Kullanıcı adı ya da şifre hatalı');
            }
            self::command($socket, "MAIL FROM:<$from>", 250);
            foreach ($to as $address) self::command($socket, "RCPT TO:<$address>", [250, 251]);
            self::command($socket, 'DATA', 354);
            // Satır başındaki nokta iki nokta yapılır (SMTP kuralı)
            $data = preg_replace('/^\./m', '..', self::render($message));
            self::command($socket, $data . "\r\n.", 250);
            self::command($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    private static function phpMail(array $to, string $subject, array $message): void
    {
        $headers = $message['headers'];
        unset($headers['To'], $headers['Subject']);
        $headerLines = implode("\r\n", array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers));
        if (!@mail(implode(', ', $to), $message['headers']['Subject'], $message['body'], $headerLines)) {
            throw new RuntimeException('PHP mail() başarısız (sunucuda e-posta gönderimi ayarlı değil; SMTP kullanın)');
        }
    }

    private static function log(array $message): void
    {
        $dir = (string) Config::get('app.log.path', ROOT . '/storage/logs');
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        file_put_contents("$dir/mail.log", '==== ' . date('Y-m-d H:i:s') . " ====\r\n" . self::render($message) . "\r\n", FILE_APPEND | LOCK_EX);
    }

    // ================================================================ SMTP yardımcıları

    private static function command($socket, string $line, int|array $expect, ?string $message = null): void
    {
        fwrite($socket, $line . "\r\n");
        self::expect($socket, $expect, $message);
    }

    private static function expect($socket, int|array $expect, ?string $message = null): void
    {
        $response = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;   // çok satırlı yanıtın son satırı: "250 ..."
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, (array) $expect, true)) {
            throw new RuntimeException(($message ? "$message: " : 'SMTP hatası: ') . trim($response ?: 'yanıt yok'));
        }
    }

    // ================================================================ iç

    private static function assertEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n<>]/', $email)) throw new RuntimeException("Geçersiz e-posta adresi: $email");
    }

    // Başlık enjeksiyonuna karşı satır sonları silinir
    private static function clean(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    private static function encode(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    private static function host(): string
    {
        $host = preg_replace('/[^a-z0-9.\-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?: 'localhost';
        return preg_replace('/^www\./', '', $host);
    }
}
