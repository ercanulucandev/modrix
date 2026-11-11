<?php
namespace Core;

use ErrorException, RuntimeException, Throwable, finfo;

// Dosya yükleme.
//
//   $file  = Upload::save('avatar', 'users');                       // tek dosya: <input type="file" name="avatar">
//   $files = Upload::saveMany('files', 'notes');                    // çoklu:    <input type="file" name="files[]" multiple>
//   $file  = Upload::save('cv', 'cv', ['types' => ['pdf'], 'max' => 2 * 1024 * 1024]);
//
//   if (Upload::errors()) ...                                       // hata mesajları (Türkçe)
//   Upload::path($file['path'])                                     // diskteki tam yol
//   Upload::delete($file['path'])                                   // dosyayı sil
//
// Dönen bilgi: ['name' => orijinal ad, 'path' => 'notes/2026/11/ab12...f9.pdf', 'size' => byte, 'mime' => 'application/pdf']
// Dosyalar config('upload.path') altına rastgele adla kaydedilir. Bu klasör dışarıya kapalıdır;
// indirtmek için: return Response::download(Upload::path($file['path']), $file['name']);
class Upload
{
    // Uzantı => izin verilen gerçek MIME türleri (dosyanın içeriğinden okunur, tarayıcıya güvenilmez)
    private const TYPES = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'ico' => ['image/vnd.microsoft.icon', 'image/x-icon'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'], 'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'doc' => ['application/msword'], 'xls' => ['application/vnd.ms-excel'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    ];

    private static array $errors = [];

    public static function has(string $field): bool
    {
        $f = $_FILES[$field] ?? null;
        if (!$f) return false;
        $errors = (array) $f['error'];
        return in_array(UPLOAD_ERR_OK, $errors, true) || array_diff($errors, [UPLOAD_ERR_NO_FILE]) !== [];
    }

    public static function save(string $field, string $dir = '', array $rules = []): ?array
    {
        self::$errors = [];
        $f = $_FILES[$field] ?? null;
        if (!$f || is_array($f['name'])) return null;
        return self::store($f, $dir, $rules);
    }

    public static function saveMany(string $field, string $dir = '', array $rules = []): array
    {
        self::$errors = [];
        $f = $_FILES[$field] ?? null;
        if (!$f) return [];

        $saved = [];
        foreach ((array) $f['name'] as $i => $name) {
            $one = ['name' => $name, 'tmp_name' => ((array) $f['tmp_name'])[$i], 'error' => ((array) $f['error'])[$i], 'size' => ((array) $f['size'])[$i]];
            if ($file = self::store($one, $dir, $rules)) $saved[] = $file;
        }
        return $saved;
    }

    public static function errors(): array
    {
        return self::$errors;
    }

    public static function path(string $relative): string
    {
        return self::root() . '/' . ltrim($relative, '/');
    }

    public static function delete(string $relative): void
    {
        $file = self::path($relative);
        if (str_contains($relative, '..') || !is_file($file)) return;
        unlink($file);
    }

    private static function store(array $f, string $dir, array $rules): ?array
    {
        if ($f['error'] === UPLOAD_ERR_NO_FILE) return null;
        $name = self::cleanName((string) $f['name']);

        if ($f['error'] !== UPLOAD_ERR_OK) {
            return self::fail($name, in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'dosya çok büyük' : 'yüklenemedi (hata kodu ' . $f['error'] . ')');
        }

        $max = $rules['max'] ?? Config::get('app.upload.max_size', 5 * 1024 * 1024);
        if ($f['size'] > $max) return self::fail($name, 'en fazla ' . self::human($max) . ' olabilir');

        $ext     = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = $rules['types'] ?? array_keys(self::TYPES);
        if (!in_array($ext, $allowed, true) || !isset(self::TYPES[$ext])) {
            return self::fail($name, 'bu dosya türüne izin yok (' . implode(', ', $allowed) . ')');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!in_array($mime, self::TYPES[$ext], true)) return self::fail($name, 'dosyanın içeriği uzantısıyla uyuşmuyor');

        // notes/2026/11/3f9a...c1.pdf
        $relative = trim($dir, '/') . '/' . date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $relative = ltrim($relative, '/');
        $target   = self::path($relative);

        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true)) return self::fail($name, 'klasör oluşturulamadı');
        if (!move_uploaded_file($f['tmp_name'], $target)) return self::fail($name, 'kaydedilemedi');

        return ['name' => $name, 'path' => $relative, 'size' => (int) $f['size'], 'mime' => $mime];
    }

    // Orijinal adı veritabanına/ekrana yazılabilir hale getirir:
    // geçersiz UTF-8 düzeltilir, yol ve kontrol karakterleri atılır, 255 karakterle sınırlanır.
    private static function cleanName(string $name): string
    {
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = mb_convert_encoding($name, 'UTF-8', 'Windows-1254'); // Türkçe Windows istemcileri
        }
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? 'dosya';
        return mb_substr($name !== '' ? $name : 'dosya', -255);
    }

    private static function fail(string $name, string $reason): ?array   // her zaman null (PHP 8.1: tek başına null dönüş türü yok)
    {
        self::$errors[] = "$name: $reason";
        return null;
    }

    private static function root(): string
    {
        return rtrim(Config::get('app.upload.path', ROOT . '/storage/uploads'), '/\\');
    }

    // 5242880 -> "5 MB"
    public static function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = $bytes > 0 ? min((int) floor(log($bytes, 1024)), 3) : 0;
        return round($bytes / 1024 ** $i, $i ? 1 : 0) . ' ' . $units[$i];
    }
}
