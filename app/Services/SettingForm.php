<?php
namespace App\Services;

use App\Models\Media;
use Core\Request;
use Core\Setting;
use Core\Upload;
use Core\Validator;

// Ayar formunu doğrulayıp kaydeder. Admin > Ayarlar sekmeleri ve modül ayar sayfaları ortak kullanır.
//   SettingForm::save($group['fields']);
final class SettingForm
{
    // Çoklu dilde yazı alanları (text, textarea) dillere çevrilebilir: 'anahtar__en'. Alan ya da grup 'translatable' => false ile kapatılır.
    public static function translatable(array $field): bool
    {
        return in_array($field['type'], ['text', 'textarea'], true) && ($field['translatable'] ?? true) !== false;
    }

    // $translate: grubun çeviri kutuları (Ayarlar'da grup 'translatable' => false olabilir)
    public static function save(array $fields, bool $translate = true): void
    {
        if ($translate && \Core\Lang::enabled()) {
            foreach ($fields as $key => $f) {
                if (!self::translatable($f)) continue;
                foreach (array_slice(array_keys(\Core\Lang::all()), 1) as $code) {
                    $rules = preg_replace('/(^|\|)required(\||$)/', '$1nullable$2', $f['rules'] ?? 'nullable');
                    $fields[Setting::translationKey($key, $code)] = ['label' => $f['label'] . ' (' . \Core\Lang::name($code) . ')', 'type' => $f['type'], 'rules' => str_contains($rules, 'nullable') ? $rules : "nullable|$rules"];
                }
            }
        }
        // Doğrulama: checkbox ve görsel dışındaki alanlar
        $rules = $labels = [];
        foreach ($fields as $key => $f) {
            if (in_array($f['type'], ['checkbox', 'image'], true)) continue;
            $rules[$key]  = $f['rules'] ?? 'nullable';
            $labels[$key] = $f['label'];
        }
        $values = $rules ? Validator::validate($rules, $labels) : [];

        foreach ($fields as $key => $f) {
            // Şifre alanı formda gösterilmez; boş gelirse kayıtlı değer korunur
            if ($f['type'] === 'password' && ($values[$key] ?? null) === null) unset($values[$key]);
            if ($f['type'] === 'checkbox') $values[$key] = Request::post($key) ? '1' : '0';
            if ($f['type'] === 'image') {
                $image = self::image($key);
                if ($image !== false) $values[$key] = $image;
            }
        }

        Setting::set($values);
    }

    // Görsel medya kütüphanesinden seçilir; formdan dosya yolu gelir (ör. media/2026/11/abc.png).
    // Dönüş: yeni yol | null (kaldırıldı) | false (değişmedi)
    private static function image(string $key)
    {
        $old = (string) Setting::get($key);
        $new = trim((string) Request::post($key));

        if ($new === $old) return false;

        // Eski sürümde doğrudan yüklenmiş ayar görseli kaldırılınca dosyası da silinir (kütüphanedekiler silinmez)
        if ($old !== '' && str_starts_with($old, 'settings/')) Upload::delete($old);

        if ($new === '') return null;

        // Sadece kütüphanedeki görseller seçilebilir (rastgele bir yol yazılamaz)
        $media = Media::findByPath($new);
        if (!$media || $media['type'] !== 'image') {
            Validator::fail([$key => 'Seçilen görsel medya kütüphanesinde bulunamadı.']);
        }
        return $media['path'];
    }
}
