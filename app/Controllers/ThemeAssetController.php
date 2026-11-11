<?php
namespace App\Controllers;

use Core\Controller;
use Core\Theme;

// Tema dosyaları: /theme/{tema}/{yol}  ->  app/Themes/{Tema}/assets/{yol}
//   /theme/redmo/css/style.css  ->  app/Themes/Redmo/assets/css/style.css
// Sadece assets/ klasörü ve izinli türler sunulur; temanın PHP dosyalarına dışarıdan ulaşılamaz.
// View içinde adres için Theme::asset('css/style.css').
class ThemeAssetController extends Controller
{
    public function show(string $theme, string $path): void
    {
        $path   = str_replace('\\', '/', $path);
        $type   = ModuleAssetController::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
        $folder = Theme::folderFromSlug($theme);
        if (!$type || !$folder) $this->abort(404);

        foreach (explode('/', $path) as $p) {
            if ($p === '' || $p === '..' || $p[0] === '.') $this->abort(404);
        }

        $file = ROOT . "/app/Themes/$folder/assets/$path";
        if (!is_file($file)) $this->abort(404);

        ModuleAssetController::send($file, $type);
    }
}
