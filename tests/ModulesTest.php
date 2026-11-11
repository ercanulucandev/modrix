<?php
namespace Tests;

use App\Modules\Shop\Services\Shop;
use App\Services\Updater;
use Core\Module;

final class ModulesTest extends TestCase
{
    public function testEveryModuleManifestIsValid(): void
    {
        foreach (glob(ROOT . '/app/Modules/*/module.json') as $file) {
            $folder = basename(dirname($file));
            $m = Module::validateManifest(json_decode((string) file_get_contents($file), true), $folder);
            $this->assertTrue((bool) preg_match('/^\d+\.\d+\.\d+$/', $m['version']), "$folder sürümü x.y.z olmalı");
            foreach (glob(dirname($file) . '/Migrations/*.sql') as $sql) {
                if (str_ends_with($sql, '.down.sql')) continue;
                $this->assertTrue(is_file(substr($sql, 0, -4) . '.down.sql'), basename($sql) . ' için .down.sql olmalı');
            }
        }
    }

    public function testMoneyIsCalculatedInCents(): void
    {
        if (!class_exists(Shop::class)) throw new SkipTest('Mağaza modülü yok');
        $this->assertSame(30, Shop::cents('0.1') + Shop::cents('0.2'), '0.1 + 0.2 kuruşta tam olmalı');
        $this->assertSame('1.234,50 ₺', Shop::money(1234.5));
        $this->assertSame('19.90', Shop::fromCents(1990));
    }

    public function testUpdaterNeverTouchesUserData(): void
    {
        foreach (['storage/uploads/a.png', 'app/Config/database.local.php', 'app/Config/key.local.php', '.git/config', 'dist/x.zip'] as $path) {
            $this->assertTrue(self::call(Updater::class, 'skipped', $path), "$path atlanmalı");
        }
        foreach (['core/App.php', 'app/Modules/Blog/routes.php', 'app/Config/app.php', 'modrix'] as $path) {
            $this->assertFalse(self::call(Updater::class, 'skipped', $path), "$path güncellenmeli");
        }
    }

    public function testReleaseNotesAreEscaped(): void
    {
        $html = Updater::notes("### Yeni\n- **Kalın** madde\n- <script>x</script>");
        $this->assertContains('<strong>Kalın</strong>', $html);
        $this->assertContains('<li>', $html);
        $this->assertNotContains('<script>', $html);
    }
}
