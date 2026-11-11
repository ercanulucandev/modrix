<?php
namespace App\Models;

use Core\Model;
use Core\Request;

class Media extends Model
{
    public const TYPES = ['image' => 'Görseller', 'document' => 'Belgeler'];

    protected static string $table = 'media';
    protected static array $fillable = ['path', 'thumb_path', 'sizes', 'name', 'title', 'alt', 'mime', 'type', 'size', 'width', 'height', 'uploaded_by'];

    public static function findByPath(string $path): ?array
    {
        return static::first(['path' => $path]);
    }

    public static function url(?string $path): ?string
    {
        return $path ? Request::url('media/' . $path) : null;
    }
}
