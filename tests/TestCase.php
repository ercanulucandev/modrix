<?php
namespace Tests;

use Core\Database;
use RuntimeException;
use Throwable;

// Bağımlılıksız küçük test altyapısı. Çalıştırma: php modrix test [süzgeç]
//
//   final class HesapTest extends TestCase {
//       public function testToplama(): void { $this->assertSame(4, 2 + 2); }
//   }
//
// tests/ altındaki *Test.php dosyaları; 'test' ile başlayan public metotlar. Veritabanı isteyen test: $this->needsDatabase()
// (bağlantı yoksa atlanır). Veritabanı testleri işlem (transaction) içinde çalışır ve sonunda geri alınır.
abstract class TestCase
{
    public int $assertions = 0;

    public function setUp(): void {}
    public function tearDown(): void {}

    protected function assertTrue($value, string $message = ''): void { $this->check($value === true, $message ?: 'true bekleniyordu, ' . self::show($value) . ' geldi'); }
    protected function assertFalse($value, string $message = ''): void { $this->check($value === false, $message ?: 'false bekleniyordu, ' . self::show($value) . ' geldi'); }
    protected function assertNull($value, string $message = ''): void { $this->check($value === null, $message ?: 'null bekleniyordu, ' . self::show($value) . ' geldi'); }
    protected function assertSame($expected, $actual, string $message = ''): void { $this->check($expected === $actual, $message ?: self::show($expected) . ' bekleniyordu, ' . self::show($actual) . ' geldi'); }
    protected function assertEquals($expected, $actual, string $message = ''): void { $this->check($expected == $actual, $message ?: self::show($expected) . ' bekleniyordu, ' . self::show($actual) . ' geldi'); }
    protected function assertContains(string $needle, string $haystack, string $message = ''): void { $this->check(str_contains($haystack, $needle), $message ?: self::show($needle) . ' içermeli: ' . self::show($haystack)); }
    protected function assertNotContains(string $needle, string $haystack, string $message = ''): void { $this->check(!str_contains($haystack, $needle), $message ?: self::show($needle) . ' içermemeli: ' . self::show($haystack)); }
    protected function assertCount(int $count, array $items, string $message = ''): void { $this->check(count($items) === $count, $message ?: "$count öğe bekleniyordu, " . count($items) . ' geldi'); }

    protected function assertThrows(string $class, callable $fn, string $message = ''): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->check($e instanceof $class, $message ?: "$class bekleniyordu, " . get_class($e) . ' geldi: ' . $e->getMessage());
            return;
        }
        $this->check(false, $message ?: "$class fırlatılmadı");
    }

    protected function needsDatabase(): void
    {
        try {
            Database::pdo()->query('SELECT 1');
        } catch (Throwable) {
            throw new SkipTest('veritabanı yok');
        }
    }

    // Özel (private) metodu çağırmak için
    protected static function call(string $class, string $method, ...$args)
    {
        $m = new \ReflectionMethod($class, $method);
        return $m->invoke(null, ...$args);
    }

    protected static function setStatic(string $class, string $property, $value): void
    {
        (new \ReflectionProperty($class, $property))->setValue(null, $value);
    }

    private function check(bool $ok, string $message): void
    {
        $this->assertions++;
        if (!$ok) throw new AssertionFailed($message);
    }

    private static function show($v): string
    {
        return mb_strimwidth(var_export($v, true), 0, 160, '…');
    }
}

final class AssertionFailed extends RuntimeException {}
final class SkipTest extends RuntimeException {}
