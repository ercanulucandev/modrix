<?php
namespace Core;

use ErrorException, RuntimeException, Throwable, finfo;

// HTTP hatası fırlatmak için: Response::abort(403) ya da throw new HttpException(404);
// ErrorHandler yakalar ve uygun sayfayı gösterir. Log'a yazılmaz (beklenen durum).
class HttpException extends RuntimeException
{
    private const TEXTS = [
        400 => 'Geçersiz istek',
        401 => 'Giriş yapmanız gerekiyor',
        403 => 'Bu sayfaya erişim yetkiniz yok',
        404 => 'Sayfa bulunamadı',
        405 => 'Bu işleme izin verilmiyor',
        419 => 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin',
        429 => 'Çok fazla istek. Biraz bekleyip tekrar deneyin',
        500 => 'Bir şeyler ters gitti',
        503 => 'Site şu an bakımda',
    ];

    private int $status;

    public function __construct(int $status, string $message = '', ?Throwable $previous = null)
    {
        $this->status = $status;
        parent::__construct($message !== '' ? $message : self::text($status), $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function text(int $status): string
    {
        return self::TEXTS[$status] ?? 'Hata';
    }
}
