<?php
namespace App\Services;

use App\Models\Media;
use Core\Hook;
use Core\HttpException;
use Core\Log;
use Core\Setting;
use Core\Upload;

// Medya kütüphanesinin iş mantığı: yükle, görseli işle (Ayarlar > Medya), küçük resim üret, listele, güncelle, sil.
// Kullanan yerler: Admin > Medya, medya seçici modalı (editör, ayarlar), modüller.
final class MediaService
{
    public const IMAGE_TYPES    = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];
    public const DOCUMENT_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'];
    private const MAX_PIXELS    = 50_000_000;   // bundan büyük görseller işlenmez (bellek)

    // Ayarlar > Medya
    public static function maxSize(): int    { return max(1, (int) Setting::get('media_max_size', 10)) * 1024 * 1024; }
    // Duyarlı görseller için üretilen genişlikler (srcset). Orijinalden küçük olanlar üretilir.
    public const SIZES = [640, 1024, 1600];

    private static function thumbWidth(): int { return max(100, (int) Setting::get('media_thumb_width', 400)); }

    // Filtreli, sayfalı liste. $filters: ['q' => 'arama', 'type' => 'image']
    public static function paginate(array $filters = [], int $perPage = 24, ?int $page = null): array
    {
        $type = $filters['type'] ?? null;

        return Media::query()
            ->search(['name', 'title', 'alt'], $filters['q'] ?? null)
            ->when(isset(Media::TYPES[(string) $type]), fn($q) => $q->where('type', $type))
            ->orderByDesc('id')
            ->paginate($perPage, $page);
    }

    public static function find(int $id): array
    {
        return Media::find($id) ?? throw new HttpException(404, 'Dosya bulunamadı');
    }

    // Formdaki dosya alanından (tek ya da çoklu) yükler. Dönüş: [[kayıtlar], [hata mesajları]]
    public static function uploadFrom(string $field, ?int $userId = null): array
    {
        $files = Upload::saveMany($field, 'media', [
            'types' => [...self::IMAGE_TYPES, ...self::DOCUMENT_TYPES],
            'max'   => self::maxSize(),
        ]);
        $errors = Upload::errors();

        $saved = [];
        foreach ($files as $file) {
            try {
                $saved[] = self::store(self::optimize($file), $userId);
            } catch (\Throwable $e) {
                Upload::delete($file['path']);
                Log::error('Medya kaydedilemedi', ['file' => $file['name'], 'error' => $e->getMessage()]);
                $errors[] = "{$file['name']}: kaydedilemedi";
            }
        }
        return [$saved, $errors];
    }

    public static function update(int $id, array $data): array
    {
        $media = self::find($id);
        Media::update($media['id'], ['title' => $data['title'] ?? null, 'alt' => $data['alt'] ?? null]);
        return Media::find($media['id']);
    }

    // ---------------------------------------------------------------- görsel düzenleyici

    // Düzenlenebilir görseller (GIF animasyonu ve ICO bozulacağı için hariç)
    public const EDITABLE_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    public static function isEditable(array $media): bool
    {
        return $media['type'] === 'image' && in_array(strtolower(pathinfo($media['path'], PATHINFO_EXTENSION)), self::EDITABLE_TYPES, true);
    }

    // Düzenlenmiş görseli YENİ bir kayıt olarak ekler; orijinal olduğu gibi kalır.
    public static function saveEditedCopy(int $id, string $field, ?int $userId = null): array
    {
        $original = self::find($id);
        if (!self::isEditable($original)) throw new HttpException(422, 'Bu dosya düzenlenemez.');

        $file = self::uploadedImage($field, 'media');
        $base = pathinfo($original['name'], PATHINFO_FILENAME);
        $file['name'] = mb_substr($base, 0, 200) . '-duzenlenmis.' . strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));

        try {
            $copy = self::store($file, $userId);
        } catch (\Throwable $e) {
            Upload::delete($file['path']);
            throw $e;
        }
        // Başlık ve alt metin orijinalden devralınır
        Media::update($copy['id'], ['title' => trim(($original['title'] ?: $base) . ' (düzenlenmiş)'), 'alt' => $original['alt']]);
        return Media::find($copy['id']);
    }

    // Düzenlenmiş görseli ORİJİNALİN YERİNE yazar. Adres (path) değişmez; içerikte/ayarlarda kullanılan yerler bozulmaz.
    // Bu yüzden format aynı kalmalı (png -> png).
    public static function replaceWithEdited(int $id, string $field): array
    {
        $media = self::find($id);
        if (!self::isEditable($media)) throw new HttpException(422, 'Bu dosya düzenlenemez.');

        $file = self::uploadedImage($field, 'tmp');
        $ext  = strtolower(pathinfo($media['path'], PATHINFO_EXTENSION));
        $new  = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));
        $same = $ext === $new || (in_array($ext, ['jpg', 'jpeg'], true) && in_array($new, ['jpg', 'jpeg'], true));
        if (!$same) {
            Upload::delete($file['path']);
            throw new HttpException(422, 'Orijinalin yerine kaydederken format değişemez (.' . $ext . ').');
        }

        if (!@rename(Upload::path($file['path']), Upload::path($media['path']))) {
            Upload::delete($file['path']);
            throw new HttpException(500, 'Dosya yazılamadı.');
        }
        clearstatcache(true, Upload::path($media['path']));

        [$width, $height] = @getimagesize(Upload::path($media['path'])) ?: [null, null];
        if ($media['thumb_path']) Upload::delete($media['thumb_path']);
        self::deleteSizes($media);
        $thumb = $width ? self::thumbnail($media['path'], (int) $width, (int) $height) : null;
        $sizes = $width ? self::sizes($media['path'], (int) $width, (int) $height) : [];

        Media::update($media['id'], [
            'thumb_path' => $thumb,
            'sizes'      => $sizes ? json_encode($sizes) : null,
            'size'       => filesize(Upload::path($media['path'])),
            'width'      => $width,
            'height'     => $height,
        ]);

        $updated = Media::find($media['id']);
        Hook::fire('media.replaced', $updated);
        return $updated;
    }

    // Düzenleyiciden gelen tek görsel dosyası
    private static function uploadedImage(string $field, string $dir): array
    {
        $file = Upload::save($field, $dir, ['types' => self::EDITABLE_TYPES, 'max' => 25 * 1024 * 1024]);
        if (!$file) {
            $errors = Upload::errors();
            throw new HttpException(422, $errors ? implode(' ', $errors) : 'Görsel gönderilmedi.');
        }
        return $file;
    }

    public static function delete(int $id): void
    {
        $media = self::find($id);
        Upload::delete($media['path']);
        if ($media['thumb_path']) Upload::delete($media['thumb_path']);
        self::deleteSizes($media);
        Media::delete($media['id']);
        Hook::fire('media.deleted', $media);
    }

    // ---------------------------------------------------------------- iç

    private static function store(array $file, ?int $userId): array
    {
        $ext     = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));
        $isImage = in_array($ext, self::IMAGE_TYPES, true);
        $width = $height = null;
        $thumb = null;
        $sizes = [];

        if ($isImage && ($info = @getimagesize(Upload::path($file['path'])))) {
            [$width, $height] = $info;
            $thumb = self::thumbnail($file['path'], (int) $width, (int) $height);
            $sizes = self::sizes($file['path'], (int) $width, (int) $height);
        }

        try {
            $id = Media::create([
                'path'        => $file['path'],
                'thumb_path'  => $thumb,
                'sizes'       => $sizes ? json_encode($sizes) : null,
                'name'        => $file['name'],
                'title'       => pathinfo($file['name'], PATHINFO_FILENAME),
                'mime'        => $file['mime'],
                'type'        => $isImage ? 'image' : 'document',
                'size'        => $file['size'],
                'width'       => $width,
                'height'      => $height,
                'uploaded_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            if ($thumb) Upload::delete($thumb); // kayıt olmadıysa küçük resim de kalmasın (orijinali çağıran siler)
            foreach ($sizes as $p) Upload::delete($p);
            throw $e;
        }

        $media = Media::find($id);
        Hook::fire('media.uploaded', $media);
        return $media;
    }

    // ---------------------------------------------------------------- yükleme sonrası görsel işleme (Ayarlar > Medya)

    // Sırasıyla: yönü düzelt (EXIF), en uzun kenara küçült, WebP'ye çevir ya da aynı formatta yeniden kaydet
    // (yeniden kaydetmek EXIF'i de siler). Sonuç daha büyük çıkarsa ve küçültme yapılmadıysa orijinal kalır.
    // Herhangi bir sorunda orijinal dosya olduğu gibi kullanılır; yükleme asla bu yüzden başarısız olmaz.
    // Dönüş: (gerekirse güncellenmiş) $file: ['name', 'path', 'size', 'mime']
    private static function optimize(array $file): array
    {
        $ext = strtolower(pathinfo($file['path'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !function_exists('imagecreatetruecolor')) return $file;

        $source = Upload::path($file['path']);
        $info = @getimagesize($source);
        if (!$info || $info[0] * $info[1] > self::MAX_PIXELS) return $file;

        try {
            $image = match ($ext) {
                'jpg', 'jpeg' => @imagecreatefromjpeg($source),
                'png'         => @imagecreatefrompng($source),
                'webp'        => @imagecreatefromwebp($source),
            };
            if (!$image) return $file;

            $isJpeg   = in_array($ext, ['jpg', 'jpeg'], true);
            $rotated  = $isJpeg && Setting::bool('media_auto_orient') && self::autoOrient($image, $source);
            $resized  = self::fitInside($image, (int) Setting::get('media_max_dimension', 2560));
            $toWebp   = $ext !== 'webp' && Setting::bool('media_convert_webp') && function_exists('imagewebp');
            $stripJpg = $isJpeg && Setting::bool('media_strip_exif');

            if (!$rotated && !$resized && !$toWebp && !$stripJpg) return $file;   // dokunmaya gerek yok

            $quality = max(40, min(100, (int) Setting::get('media_quality', 82)));
            $target  = $toWebp ? 'webp' : ($isJpeg ? 'jpg' : $ext);
            $newPath = preg_replace('/\.[a-z0-9]+$/i', '', $file['path']) . '-o.' . $target;

            if (!self::encode($image, Upload::path($newPath), $target, $quality)) return $file;
            clearstatcache();
            $newSize = filesize(Upload::path($newPath));

            // WebP'ye çevirince büyüdüyse (ve boyut değişmediyse): çevirmekten vazgeç
            if ($toWebp && !$resized && !$rotated && $newSize >= $file['size']) {
                Upload::delete($newPath);
                if (!$stripJpg) return $file;
                // JPEG'in EXIF'i yine de silinsin: aynı formatta yeniden kaydet
                $target  = 'jpg';
                $newPath = preg_replace('/\.[a-z0-9]+$/i', '', $file['path']) . '-o.jpg';
                if (!self::encode($image, Upload::path($newPath), 'jpg', $quality)) return $file;
                clearstatcache();
                $newSize = filesize(Upload::path($newPath));
            }

            Upload::delete($file['path']);
            Log::info('Görsel işlendi', ['file' => $file['name'], 'from' => $file['size'], 'to' => $newSize, 'format' => $target, 'resized' => $resized]);

            return [
                ...$file,
                'path' => $newPath,
                'size' => $newSize,
                'mime' => ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$target],
                'name' => preg_replace('/\.[a-z0-9]+$/i', '', $file['name']) . '.' . $target,
            ];
        } catch (\Throwable $e) {
            Log::warning('Görsel işlenemedi, orijinal kullanıldı', ['file' => $file['name'], 'error' => $e->getMessage()]);
            return $file;
        }
    }

    // EXIF Orientation'a göre çevir/döndür. Değişiklik yaptıysa true.
    private static function autoOrient(\GdImage &$image, string $path): bool
    {
        if (!function_exists('exif_read_data')) return false;
        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        if ($orientation <= 1 || $orientation > 8) return false;

        if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($image, $orientation === 4 ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
        $angle = [3 => 180, 4 => 0, 5 => 90, 6 => -90, 7 => -90, 8 => 90][$orientation] ?? 0;
        if ($angle) $image = imagerotate($image, $angle, 0);
        return true;
    }

    // En uzun kenarı $max'ı geçiyorsa oranı koruyarak küçült. Küçülttüyse true.
    private static function fitInside(\GdImage &$image, int $max): bool
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if ($max <= 0 || max($w, $h) <= $max) return false;

        $scale = $max / max($w, $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $resized = imagecreatetruecolor($nw, $nh);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $image = $resized;
        return true;
    }

    private static function encode(\GdImage $image, string $path, string $format, int $quality): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        return match ($format) {
            'webp' => imagewebp($image, $path, $quality),
            'jpg'  => imagejpeg($image, $path, $quality),
            'png'  => imagepng($image, $path, 6),
        };
    }

    // Var olan görselin kopyalarını yeniden üretir (php modrix media:sizes). Dönüş: üretilen kopya sayısı
    public static function regenerateSizes(array $media): int
    {
        if ($media['type'] !== 'image' || !$media['width'] || !is_file(Upload::path($media['path']))) return 0;
        self::deleteSizes($media);
        $sizes = self::sizes($media['path'], (int) $media['width'], (int) $media['height']);
        Media::update($media['id'], ['sizes' => $sizes ? json_encode($sizes) : null]);
        return count($sizes);
    }

    private static function deleteSizes(array $media): void
    {
        foreach (json_decode((string) ($media['sizes'] ?? ''), true) ?: [] as $p) Upload::delete($p);
    }

    // srcset kopyaları (WebP): ['640' => 'media/...-640w.webp', ...]
    private static function sizes(string $path, int $width, int $height): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $targets = array_filter(self::SIZES, fn($w) => $w < $width);
        if (!$targets || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !function_exists('imagewebp')) return [];

        $source = Upload::path($path);
        $image = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($source),
            'png'         => @imagecreatefrompng($source),
            'webp'        => @imagecreatefromwebp($source),
        };
        if (!$image) return [];

        $quality = max(40, min(100, (int) Setting::get('media_quality', 82)));
        $base = preg_replace('/\.[a-z0-9]+$/i', '', $path);
        $out = [];
        foreach ($targets as $w) {
            $h = (int) round($height * $w / $width);
            $copy = imagecreatetruecolor($w, $h);
            imagealphablending($copy, false);
            imagesavealpha($copy, true);
            imagecopyresampled($copy, $image, 0, 0, 0, 0, $w, $h, $width, $height);
            if (imagewebp($copy, Upload::path("$base-{$w}w.webp"), $quality)) $out[(string) $w] = "$base-{$w}w.webp";
        }
        return $out;
    }

    // Izgara için küçük resim (WebP). Küçük görsellerde, ICO/GIF'te ya da GD yoksa üretilmez.
    private static function thumbnail(string $path, int $width, int $height): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($width <= self::thumbWidth() || in_array($ext, ['ico', 'gif'], true) || !function_exists('imagewebp')) return null;

        $source = Upload::path($path);
        $image = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($source),
            'png'         => @imagecreatefrompng($source),
            'webp'        => @imagecreatefromwebp($source),
            default       => false,
        };
        if (!$image) return null;

        $thumbH = (int) round($height * self::thumbWidth() / $width);
        $thumb  = imagecreatetruecolor(self::thumbWidth(), $thumbH);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, self::thumbWidth(), $thumbH, $width, $height);

        $thumbPath = preg_replace('/\.[a-z0-9]+$/i', '', $path) . '-thumb.webp';
        // (imagedestroy gerekmez: PHP 8'den beri görseller otomatik serbest bırakılır, 8.5'te kullanımdan kalktı)
        return imagewebp($thumb, Upload::path($thumbPath), 80) ? $thumbPath : null;
    }
}
