<?php
namespace Tests;

use Core\Database;
use Throwable;

// php modrix test [süzgeç]  -> çıkış kodu 0: hepsi geçti, 1: hata var
final class Runner
{
    public static function run(?string $filter = null): int
    {
        require_once __DIR__ . '/TestCase.php';
        $passed = $failed = $skipped = $assertions = 0;
        $failures = [];
        $start = microtime(true);

        foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
            require_once $file;
            $class = 'Tests\\' . basename($file, '.php');
            if (!class_exists($class)) continue;
            foreach (get_class_methods($class) as $method) {
                if (!str_starts_with($method, 'test')) continue;
                $name = basename($file, '.php') . "::$method";
                if ($filter && stripos($name, $filter) === false) continue;

                $test = new $class();
                $tx = false;
                try {
                    $test->setUp();
                    try { if (Database::pdo()->inTransaction() === false) { Database::pdo()->beginTransaction(); $tx = true; } } catch (Throwable) {}
                    $test->$method();
                    $passed++;
                    echo "\033[32m.\033[0m";
                } catch (SkipTest $e) {
                    $skipped++;
                    echo "\033[33mS\033[0m";
                } catch (Throwable $e) {
                    $failed++;
                    $failures[] = [$name, $e];
                    echo "\033[31mF\033[0m";
                } finally {
                    try { if ($tx && Database::pdo()->inTransaction()) Database::pdo()->rollBack(); } catch (Throwable) {}
                    $test->tearDown();
                    $assertions += $test->assertions;
                }
            }
        }

        echo "\n\n";
        foreach ($failures as $i => [$name, $e]) {
            $where = $e instanceof AssertionFailed ? self::where($e) : $e->getFile() . ':' . $e->getLine();
            echo ($i + 1) . ") \033[31m$name\033[0m\n   " . get_class($e) . ': ' . $e->getMessage() . "\n   $where\n\n";
        }
        $time = round(microtime(true) - $start, 2);
        echo ($failed ? "\033[31m" : "\033[32m") . "$passed geçti" . ($failed ? ", $failed hatalı" : '') . ($skipped ? ", $skipped atlandı" : '') . " · $assertions doğrulama · {$time} sn\033[0m\n";
        return $failed ? 1 : 0;
    }

    // Hatanın test dosyasındaki satırı
    private static function where(Throwable $e): string
    {
        foreach ($e->getTrace() as $frame) {
            if (isset($frame['file']) && str_ends_with($frame['file'], 'Test.php')) return basename($frame['file']) . ':' . $frame['line'];
        }
        return '';
    }
}
