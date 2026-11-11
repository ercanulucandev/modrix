<?php
namespace Tests;

use Core\HttpException;
use Core\Lang;
use Core\Route;
use Core\Validator;

final class CoreTest extends TestCase
{
    public function tearDown(): void
    {
        Lang::forget();
    }

    public function testValidatorRules(): void
    {
        $rules = ['name' => 'required|max:5', 'email' => 'required|email', 'kind' => 'in:a,b', 'code' => 'nullable|regex:/^\d{3}$/', 'n' => 'nullable|integer|between:1,10'];
        $this->assertCount(0, Validator::check(['name' => 'Ali', 'email' => 'a@b.co', 'kind' => 'a', 'code' => '123', 'n' => '5'], $rules));
        $errors = Validator::check(['name' => '', 'email' => 'x', 'kind' => 'c', 'code' => '12', 'n' => '11'], $rules);
        foreach (['name', 'email', 'kind', 'code', 'n'] as $field) $this->assertTrue(isset($errors[$field]), "$field hatalı sayılmalı");
        $this->assertTrue(isset(Validator::check(['name' => 'Çok uzun ad'], ['name' => 'max:5'])['name']), 'max karakter (bayt değil) saymalı');
        $this->assertCount(0, Validator::check(['name' => 'Şükrü'], ['name' => 'max:5']), 'Türkçe 5 harf geçmeli');
    }

    public function testRouteMatchesParameters(): void
    {
        $route = new Route(['GET'], '/blog/{slug}', fn() => null);
        $this->assertSame(['slug' => 'merhaba-dunya'], $route->match('/blog/merhaba-dunya'));
        $this->assertNull($route->match('/blog/a/b'));
        $wild = new Route(['GET'], '/media/{path*}', fn() => null);
        $this->assertSame(['path' => 'media/2026/11/a.webp'], $wild->match('/media/media/2026/11/a.webp'));
    }

    public function testLanguagePrefixInUrls(): void
    {
        self::setStatic(Lang::class, 'languages', ['tr' => ['code' => 'tr', 'name' => 'Türkçe'], 'en' => ['code' => 'en', 'name' => 'English']]);
        self::setStatic(Lang::class, 'current', 'en');
        $base = \Core\Request::base();
        $this->assertSame("$base/en/blog", \Core\Request::url('blog'));
        $this->assertSame("$base/en", \Core\Request::url(''));
        $this->assertSame("$base/admin/pages", \Core\Request::url('admin/pages'), 'panel önek almamalı');
        $this->assertSame("$base/media/media/a.png", \Core\Request::url('media/media/a.png'), 'medya önek almamalı');
        $this->assertSame("$base/blog", \Core\Request::url('blog', 'tr'), 'varsayılan dil öneksiz');
        $this->assertSame('en/about', Lang::localPath('about', 'en'));
        $this->assertSame('about', Lang::localPath('about', 'tr'));
    }

    public function testHttpExceptionCarriesStatus(): void
    {
        $this->assertThrows(HttpException::class, fn() => throw new HttpException(422, 'x'));
        $this->assertSame(404, (new HttpException(404))->status());
    }
}
