<?php
namespace Core;

// Olay (hook) sistemi. Kayıtlar app/Hooks.php içinde yapılır.
//
//   Hook::on('post.created', function (array $post) { Log::info('Yeni yazı', $post); });
//   Hook::fire('post.created', $post);
//
//   // filtre: değeri sırayla değiştirir
//   Hook::on('post.title', fn($title) => mb_strtoupper($title));
//   $title = Hook::filter('post.title', $title);
//
// Sistemin kendi olayları: app.boot, request.before, response.after
class Hook
{
    private static array $listeners = [];

    public static function on(string $event, callable $listener, int $priority = 10): void
    {
        self::$listeners[$event][$priority][] = $listener;
    }

    public static function fire(string $event, ...$args): void
    {
        foreach (self::listeners($event) as $listener) $listener(...$args);
    }

    public static function filter(string $event, $value, ...$args)
    {
        foreach (self::listeners($event) as $listener) $value = $listener($value, ...$args);
        return $value;
    }

    public static function has(string $event): bool
    {
        return !empty(self::$listeners[$event]);
    }

    private static function listeners(string $event): array
    {
        if (empty(self::$listeners[$event])) return [];
        ksort(self::$listeners[$event]);
        return array_merge(...self::$listeners[$event]);
    }
}
