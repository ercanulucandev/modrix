<?php
namespace Tests;

use App\Services\Collection;
use Core\Database;
use Core\Migrator;
use Core\Setting;

// Veritabanı testleri: her test işlem içinde çalışır, sonunda geri alınır (veriler kalmaz)
final class DatabaseTest extends TestCase
{
    public function testAllMigrationsHaveRun(): void
    {
        $this->needsDatabase();
        foreach (Migrator::status() as $name => $s) {
            if (($s['module'] ?? null) === null) $this->assertTrue($s['done'], "Çalışmamış migration: $name");
        }
    }

    public function testQueryBuilderEscapesAndPaginates(): void
    {
        $this->needsDatabase();
        // Kendi satırlarını ekler (temiz veritabanında da çalışsın)
        foreach (['test_%_key' => "O'Reilly", 'test_a_key' => 'x', 'test_b_key' => 'y'] as $key => $value) {
            Database::table('settings')->insert(['key' => $key, 'value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->assertSame("O'Reilly", Database::table('settings')->where('key', 'test_%_key')->value('value'));
        $this->assertSame(1, Database::table('settings')->whereLike('key', 'test_%_')->count(), 'whereLike % karakterini kaçırmalı (test_a_key eşleşmemeli)');
        $page = Database::table('settings')->whereRaw("`key` LIKE 'test\\_%'")->orderBy('key')->paginate(2, 1);
        $this->assertSame(2, count($page['data']));
        $this->assertSame(3, $page['total']);
        $this->assertSame(2, $page['pages']);
    }

    public function testDeleteWithoutConditionIsBlocked(): void
    {
        $this->needsDatabase();
        $this->assertThrows(\LogicException::class, fn() => Database::table('settings')->delete());
    }

    public function testNestedTransactionsJoin(): void
    {
        $this->needsDatabase();
        $result = Database::transaction(fn() => Database::transaction(fn() => 'iç'));
        $this->assertSame('iç', $result);
    }

    public function testSettingLanguageOverride(): void
    {
        $this->needsDatabase();
        Setting::set(['test_tagline' => 'Merhaba', 'test_tagline__en' => 'Hello']);
        $this->assertSame('Merhaba', Setting::raw()['test_tagline']);
        $this->assertSame('test_tagline__en', Setting::translationKey('test_tagline', 'en'));
    }
}
