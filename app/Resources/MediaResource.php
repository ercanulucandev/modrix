<?php
namespace App\Resources;

use App\Models\Media;
use App\Services\MediaService;
use Core\Upload;

// API'nin döndüğü medya biçimi
final class MediaResource
{
    public static function make(array $m): array
    {
        return [
            'id'         => (int) $m['id'],
            'name'       => $m['name'],
            'title'      => $m['title'],
            'alt'        => $m['alt'],
            'type'       => $m['type'],
            'mime'       => $m['mime'],
            'extension'  => strtolower(pathinfo($m['path'], PATHINFO_EXTENSION)),
            'size'       => (int) $m['size'],
            'size_label' => Upload::human((int) $m['size']),
            'width'      => $m['width'] !== null ? (int) $m['width'] : null,
            'height'     => $m['height'] !== null ? (int) $m['height'] : null,
            'path'       => $m['path'],
            'url'        => Media::url($m['path']),
            'thumb_url'  => Media::url($m['thumb_path'] ?? $m['path']),
            'editable'   => MediaService::isEditable($m),
            // Yerine kaydedilince adres aynı kalır; tarayıcı eski önizlemeyi göstermesin diye sürüm
            'version'    => substr(md5(($m['updated_at'] ?? '') . $m['size']), 0, 8),
            'created_at' => $m['created_at'],
            'updated_at' => $m['updated_at'],
        ];
    }

    public static function collection(array $items): array
    {
        return array_map([self::class, 'make'], $items);
    }
}
