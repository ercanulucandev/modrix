<?php
namespace Core;

use InvalidArgumentException;

// Form doğrulama.
//
//   $data = Validator::validate([
//       'title' => 'required|max:200',
//       'email' => 'required|email|unique:users,email',
//       'age'   => 'nullable|integer|between:18,99',
//   ], ['title' => 'Başlık', 'email' => 'E-posta']);
//
// Hata varsa: hatalar ve eski değerler flash'a yazılır, geldiği sayfaya dönülür (API isteğinde 422 JSON).
// View içinde: View::error('title'), View::old('title').  Dönüş: sadece kurallardaki alanlar.
//
// Kurallar: required, nullable, email, min:n, max:n, integer, numeric, between:a,b, in:a,b,c,
// confirmed, same:alan, date, url, phone, iban, regex:/.../, unique:tablo,kolon[,haric_id], exists:tablo,kolon
class Validator
{
    public static function validate(array $rules, array $labels = [], ?array $input = null): array
    {
        $input  = $input ?? Request::all();
        $errors = self::check($input, $rules, $labels);
        if ($errors) self::fail($errors, $input);

        $out = [];
        foreach (array_keys($rules) as $field) {
            $v = $input[$field] ?? null;
            $v = is_string($v) ? trim($v) : $v;
            $out[$field] = $v === '' ? null : $v;
        }
        return $out;
    }

    // Kurallar dışında bir kontrol başarısız olunca aynı şekilde dön:
    //   Validator::fail(['body' => 'İçerik boş olamaz.'])
    // AJAX/API isteğinde 422 JSON, form gönderiminde hatalar + eski değerlerle geri.
    public static function fail(array $errors, ?array $input = null): never
    {
        if (Request::isAjax()) {
            echo Response::json(['error' => 'Doğrulama hatası', 'errors' => $errors], 422);
            exit;
        }
        Session::flash('errors', $errors);
        Session::flash('old', Log::mask($input ?? Request::all()));
        Response::back();
    }

    // Yönlendirmeden kontrol: ['email' => 'E-posta geçerli değil.', ...]
    public static function check(array $input, array $rules, array $labels = []): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $value = $input[$field] ?? null;
            if (is_string($value)) $value = trim($value);
            $label   = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $list    = explode('|', $ruleString);
            $numeric = in_array('integer', $list, true) || in_array('numeric', $list, true);

            if ($value === null || $value === '' || $value === []) {
                if (in_array('required', $list, true)) $errors[$field] = "$label alanı zorunludur.";
                continue;
            }

            foreach ($list as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                if (($message = self::rule($name, $arg, $value, $input, $label, $field, $numeric)) !== null) {
                    $errors[$field] = $message;
                    break;
                }
            }
        }
        return $errors;
    }

    private static function rule(string $name, ?string $arg, $value, array $input, string $label, string $field, bool $numeric): ?string
    {
        $str  = is_scalar($value) ? (string) $value : '';
        $size = $numeric ? (float) $value : mb_strlen($str);
        $unit = $numeric ? '' : ' karakter';

        switch ($name) {
            case 'required':
            case 'nullable':  return null;
            case 'email':     return filter_var($str, FILTER_VALIDATE_EMAIL) ? null : "$label geçerli bir e-posta adresi olmalıdır.";
            case 'url':       return filter_var($str, FILTER_VALIDATE_URL) ? null : "$label geçerli bir adres olmalıdır.";
            case 'integer':   return filter_var($str, FILTER_VALIDATE_INT) !== false ? null : "$label tam sayı olmalıdır.";
            case 'numeric':   return is_numeric($str) ? null : "$label sayı olmalıdır.";
            case 'min':       return $size >= (float) $arg ? null : "$label en az $arg$unit olmalıdır.";
            case 'max':       return $size <= (float) $arg ? null : "$label en fazla $arg$unit olabilir.";
            case 'between':
                [$a, $b] = explode(',', $arg);
                return is_numeric($str) && $str >= $a && $str <= $b ? null : "$label $a ile $b arasında olmalıdır.";
            case 'in':        return in_array($str, explode(',', $arg), true) ? null : "$label için geçersiz seçim.";
            case 'confirmed': return ($input[$field . '_confirmation'] ?? null) === $value ? null : "$label tekrarı eşleşmiyor.";
            case 'same':      return ($input[$arg] ?? null) === $value ? null : "$label eşleşmiyor.";
            case 'date':      return strtotime($str) !== false ? null : "$label geçerli bir tarih olmalıdır.";
            case 'phone':     return preg_match('/^[0-9 +()\-]{10,20}$/', $str) ? null : "$label geçerli bir telefon numarası olmalıdır.";
            case 'iban':      return self::iban($str) ? null : "$label geçerli bir IBAN olmalıdır.";
            case 'regex':     return preg_match($arg, $str) ? null : "$label biçimi geçersiz.";
            case 'unique':
                [$table, $column, $except] = array_pad(explode(',', $arg), 3, null);
                $taken = Database::table($table)
                    ->where($column, $str)
                    ->when($except, fn($q) => $q->where('id', '!=', $except))
                    ->exists();
                return $taken ? "Bu $label zaten kayıtlı." : null;
            case 'exists':
                [$table, $column] = explode(',', $arg);
                return Database::table($table)->where($column, $str)->exists() ? null : "Seçilen $label bulunamadı.";
        }
        throw new InvalidArgumentException("Bilinmeyen doğrulama kuralı: $name");
    }

    // IBAN kontrolü (mod 97, TR için 26 hane)
    public static function iban(string $iban): bool
    {
        $iban = strtoupper(str_replace(' ', '', $iban));
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/', $iban)) return false;
        if (str_starts_with($iban, 'TR') && strlen($iban) !== 26) return false;

        $digits = '';
        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $ch) $digits .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;

        $mod = 0;
        foreach (str_split($digits, 7) as $chunk) $mod = (int) (($mod . $chunk) % 97);
        return $mod === 1;
    }
}
