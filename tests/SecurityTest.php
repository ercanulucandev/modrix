<?php
namespace Tests;

use App\Services\Crypt;
use App\Services\TwoFactor;
use Core\Html;

final class SecurityTest extends TestCase
{
    // RFC 6238 test vektörleri (SHA1, 30 sn; 8 hanenin son 6 hanesi)
    public function testTotpMatchesRfcVectors(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';   // "12345678901234567890"
        $this->assertSame('287082', self::call(TwoFactor::class, 'code', $secret, intdiv(59, 30)));
        $this->assertSame('081804', self::call(TwoFactor::class, 'code', $secret, intdiv(1111111109, 30)));
        $this->assertSame('005924', self::call(TwoFactor::class, 'code', $secret, intdiv(1234567890, 30)));   // RFC: 89005924
    }

    public function testTotpCheckAcceptsCurrentCodeOnce(): void
    {
        $secret = TwoFactor::newSecret();
        $this->assertSame(32, strlen($secret));
        $step = intdiv(time(), 30);
        $code = self::call(TwoFactor::class, 'code', $secret, $step);
        $this->assertSame($step, TwoFactor::check($secret, $code));
        $this->assertNull(TwoFactor::check($secret, $code, $step), 'aynı kod ikinci kez geçmemeli');
        $this->assertNull(TwoFactor::check($secret, '12345'), '5 haneli kod geçmemeli');
    }

    public function testCryptRoundTripAndTamper(): void
    {
        $sealed = Crypt::encrypt('gizli değer');
        $this->assertSame('gizli değer', Crypt::decrypt($sealed));
        $this->assertTrue($sealed !== Crypt::encrypt('gizli değer'), 'her şifreleme farklı olmalı (nonce)');
        $raw = base64_decode($sealed);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
        $this->assertNull(Crypt::decrypt(base64_encode($raw)), 'değiştirilmiş veri açılmamalı');
        $this->assertNull(Crypt::decrypt('bozuk'));
        $this->assertSame(Crypt::sign('x'), Crypt::sign('x'));
        $this->assertTrue(Crypt::sign('x') !== Crypt::sign('y'));
    }

    public function testHtmlSanitizerRemovesDangerousMarkup(): void
    {
        $html = Html::sanitize('<p onclick="x()">Merhaba <a href="javascript:alert(1)">tık</a><script>alert(1)</script><strong>kalın</strong></p><iframe src="//x"></iframe>');
        $this->assertNotContains('<script', $html);
        $this->assertNotContains('onclick', $html);
        $this->assertNotContains('javascript:', $html);
        $this->assertNotContains('<iframe', $html);
        $this->assertContains('<strong>kalın</strong>', $html);
    }

    public function testHtmlSanitizerOnlyKeepsLibraryImages(): void
    {
        $html = Html::sanitize('<p><img src="https://kotu.example/x.png" onerror="x()"><img src="/media/media/2026/11/a.webp" class="img-md evil"></p>');
        $this->assertNotContains('kotu.example', $html);
        $this->assertNotContains('onerror', $html);
        $this->assertContains('img-md', $html);
        $this->assertNotContains('evil', $html);
    }
}
