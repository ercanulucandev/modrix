<?php
namespace Core;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

// Editörden gelen HTML'i güvenli hale getirir (XSS koruması). Global takma adı: Html
//
//   Html::sanitize($html)     sadece izinli etiket/özellikler kalır; script, on* olayları, javascript: linkleri silinir
//   Html::render($body)       sitede göstermek için: HTML ise temizlenmiş hali, düz metinse satır sonlarıyla
//   Html::text($html, 160)    özet: etiketsiz düz metin
//
// İzinli olmayan etiketler (span, div, font...) silinir ama içindeki metin kalır.
// Tehlikeli etiketler (script, style, iframe, form...) içerikleriyle birlikte silinir.
final class Html
{
    private const TAGS = [
        'p' => [], 'br' => [], 'hr' => [],
        'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'del' => [], 'sub' => [], 'sup' => [],
        'blockquote' => [], 'pre' => [], 'code' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href', 'target', 'title'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'img' => ['src', 'alt', 'width', 'height', 'class'],   // sadece medya kütüphanesindeki dosyalar; class: IMAGE_CLASSES (cleanImage)
    ];

    // En dışta bunlardan biri olmayan içerik (metin, <strong>, <a>...) bir <p> içine alınır
    // Editörde görsele verilebilen boyut ve hizalama sınıfları (stilleri: partials/ui-styles, editor.css)
    public const IMAGE_CLASSES = ['img-sm', 'img-md', 'img-lg', 'img-left', 'img-center', 'img-right'];

    private const BLOCKS = ['p', 'h2', 'h3', 'h4', 'blockquote', 'pre', 'ul', 'ol', 'table', 'hr'];

    private const DROP = [
        'script', 'style', 'iframe', 'frame', 'object', 'embed', 'applet', 'form', 'input', 'button', 'textarea',
        'select', 'option', 'svg', 'math', 'template', 'noscript', 'link', 'meta', 'base', 'head', 'title', 'video', 'audio',
    ];

    public static function sanitize(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') return '';

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // UTF-8 karakterler bozulmasın diye sayısal varlığa çevrilip yüklenir
        $doc->loadHTML(
            '<div id="__root">' . mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8') . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $doc->encoding = 'UTF-8';

        $root = (new DOMXPath($doc))->query('//div[@id="__root"]')->item(0);
        if (!$root) return '';
        self::clean($root);
        self::wrapInline($root);

        // İçi tamamen boşalan paragraflar (ör. silinen görselden kalan <p></p>) atılır; editörün boş satırı <p><br></p> kalır
        foreach (iterator_to_array($root->childNodes) as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'p' && !$child->hasChildNodes()) $root->removeChild($child);
        }

        $out = '';
        foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);

        // Sadece boş paragraflardan oluşuyorsa (editörün boş hali: <p><br></p>) boş say
        $isEmpty = trim(html_entity_decode(strip_tags($out), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\u{00A0}") === '' && !str_contains($out, '<hr') && !str_contains($out, '<img');
        return $isEmpty ? '' : trim($out);
    }

    // Sitede gösterim: eski düz metin yazılar da düzgün görünür
    public static function render(?string $body): string
    {
        $body = (string) $body;
        if (!self::isHtml($body)) {
            $paragraphs = preg_split('/\R{2,}/', trim($body));
            return implode('', array_map(fn($p) => '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8')) . '</p>', array_filter($paragraphs, 'strlen')));
        }
        return self::sanitize($body);
    }

    // Etiketsiz düz metin (özet, meta açıklama)
    public static function text(?string $html, int $limit = 0): string
    {
        $text = preg_replace('/<\/(p|h[1-6]|li|blockquote|pre|td|th)>|<br\s*\/?>/i', ' ', (string) $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\[[a-z][a-z0-9_-]*\s+[a-z0-9_-]+\]/i', ' ', $text);   // kısa kodlar: [form iletisim]
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        return $limit > 0 ? mb_strimwidth($text, 0, $limit, '…') : $text;
    }

    // Editör çıktısı her zaman bir blok etiketiyle başlar; eski düz metin yazılar (içinde "<b>" yazsa bile) düz metin sayılır
    public static function isHtml(string $text): bool
    {
        return (bool) preg_match('/^\s*<(p|h[1-6]|ul|ol|blockquote|pre|div|hr|table)[\s>\/]/i', $text);
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) continue; // metin düğümleri kalır (saveHTML kaçırır)

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!isset(self::TAGS[$tag])) {
                // İzinsiz ama zararsız etiket: içeriğini koru, etiketi kaldır
                self::clean($child);
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                if (!in_array(strtolower($attr->name), self::TAGS[$tag], true)) $child->removeAttribute($attr->name);
            }

            if ($tag === 'a') self::cleanLink($child);
            if ($tag === 'img' && !self::cleanImage($child)) {
                $node->removeChild($child);
                continue;
            }
            self::clean($child);
        }
    }

    // "<strong>a</strong> metin<h2>x</h2>" -> "<p><strong>a</strong> metin</p><h2>x</h2>"
    // Böylece kayıtlı HTML her zaman bir blok etiketiyle başlar ve düz metinle karışmaz.
    private static function wrapInline(DOMNode $root): void
    {
        $doc = $root->ownerDocument;
        $run = [];

        $flush = function (?DOMNode $before) use (&$run, $root, $doc) {
            $hasContent = false;
            foreach ($run as $n) {
                if ($n instanceof DOMElement || trim($n->textContent) !== '') { $hasContent = true; break; }
            }
            if ($hasContent) {
                $p = $doc->createElement('p');
                $root->insertBefore($p, $before);
                foreach ($run as $n) $p->appendChild($n);
            } else {
                foreach ($run as $n) $root->removeChild($n); // blokların arasındaki boşluklar
            }
            $run = [];
        };

        foreach (iterator_to_array($root->childNodes) as $child) {
            $isBlock = $child instanceof DOMElement && in_array(strtolower($child->tagName), self::BLOCKS, true);
            if ($isBlock) {
                if ($run) $flush($child);
            } else {
                $run[] = $child;
            }
        }
        if ($run) $flush(null);
    }

    // Görsel sadece kendi medya kütüphanemizden olabilir: /media/media/2026/11/abc.webp
    // (dış adres, data:, javascript: vb. -> görsel silinir; izleme pikseli / XSS / şişirme yok)
    private static function cleanImage(DOMElement $img): bool
    {
        // Önbellek için eklenen ?v=... gibi ekler atılır (görsel silinmesin)
        $src = preg_replace('/[?#].*$/', '', trim($img->getAttribute('src')));
        $img->setAttribute('src', $src);
        $prefix = Request::url('media/media/');
        if (!str_starts_with($src, $prefix) || !preg_match('#^[A-Za-z0-9/_.-]+$#', substr($src, strlen($prefix))) || str_contains($src, '..')) {
            return false;
        }
        foreach (['width', 'height'] as $attr) {
            if ($img->hasAttribute($attr) && !ctype_digit($img->getAttribute($attr))) $img->removeAttribute($attr);
        }
        // Sadece bilinen boyut/hizalama sınıfları kalır
        if ($img->hasAttribute('class')) {
            $classes = array_values(array_intersect(preg_split('/\s+/', trim($img->getAttribute('class'))), self::IMAGE_CLASSES));
            $classes ? $img->setAttribute('class', implode(' ', $classes)) : $img->removeAttribute('class');
        }
        return true;
    }

    private static function cleanLink(DOMElement $a): void
    {
        $href = $a->getAttribute('href');
        if ($href !== '' && !self::safeUrl($href)) $a->removeAttribute('href');

        if ($a->getAttribute('target') === '_blank') {
            $a->setAttribute('rel', 'noopener noreferrer');
        } else {
            $a->removeAttribute('target');
        }
    }

    // http(s), mailto, tel, göreli adres ve #çapa; javascript:, data:, vbscript: vb. reddedilir
    private static function safeUrl(string $url): bool
    {
        $normalized = strtolower(preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (preg_match('/^(https?:|mailto:|tel:)/', $normalized)) return true;
        return !preg_match('/^[a-z][a-z0-9+.\-]*:/', $normalized);
    }
}
