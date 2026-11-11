<?php
namespace App\Services;

use Core\Database;

// Genel bakış yardımcıları. Modüller grafiğe seri ekler:
//   Hook::on('admin.dashboard.series', fn(array $s) => [...$s, [
//       'key' => 'orders', 'label' => 'Sipariş', 'permission' => 'shop.manage', 'order' => 10,
//       'points' => Dashboard::daily('shop_orders', 'created_at'),             // [Y-m-d => sayı] son 30 gün
//       'format' => 'count',                                                   // 'count' | 'money'
//   ]]);
final class Dashboard
{
    public const DAYS = 30;

    // Son 30 günün her günü için değer (boş günler 0). $value: SQL ifadesi (COUNT(*), SUM(total)...), $where: ek koşul
    public static function daily(string $table, string $column, string $value = 'COUNT(*)', string $where = '1 = 1', array $bindings = []): array
    {
        $from = date('Y-m-d', strtotime('-' . (self::DAYS - 1) . ' days'));
        $points = [];
        for ($i = 0; $i < self::DAYS; $i++) $points[date('Y-m-d', strtotime("$from +$i day"))] = 0;
        try {
            $rows = Database::all("SELECT DATE(`$column`) d, $value v FROM `$table` WHERE `$column` >= ? AND ($where) GROUP BY DATE(`$column`)", [$from . ' 00:00:00', ...$bindings]);
            foreach ($rows as $r) if (isset($points[$r['d']])) $points[$r['d']] = (float) $r['v'];
        } catch (\Throwable) {
            // tablo yoksa (modül kurulmamış) boş seri
        }
        return $points;
    }

    // Çubuk grafik (SVG). $points: [Y-m-d => değer]
    public static function chart(array $points, string $format = 'count'): string
    {
        $w = 720; $h = 180; $pad = 4;
        $max = max(1, max($points));
        $n = count($points);
        $bw = ($w - $pad * 2) / $n;
        $svg = '<svg viewBox="0 0 ' . $w . ' ' . ($h + 22) . '" class="h-auto w-full" role="img" preserveAspectRatio="none">';
        foreach ([0.25, 0.5, 0.75, 1] as $f) {
            $y = round($h - $h * $f, 1);
            $svg .= '<line x1="0" x2="' . $w . '" y1="' . $y . '" y2="' . $y . '" stroke="currentColor" stroke-opacity=".08"/>';
        }
        $i = 0;
        foreach ($points as $date => $v) {
            $bh = $v > 0 ? max(2, $h * $v / $max) : 0;
            $x = round($pad + $i * $bw + $bw * 0.15, 1);
            $label = date('d.m', strtotime($date)) . ': ' . self::format($v, $format);
            $svg .= '<g><rect x="' . $x . '" y="' . round($h - $bh, 1) . '" width="' . round($bw * 0.7, 1) . '" height="' . round($bh, 1) . '" rx="2" fill="currentColor" fill-opacity="' . ($i === $n - 1 ? '1' : '.75') . '"><title>' . htmlspecialchars($label) . '</title></rect>'
                  . '<rect x="' . round($pad + $i * $bw, 1) . '" y="0" width="' . round($bw, 1) . '" height="' . $h . '" fill="transparent"><title>' . htmlspecialchars($label) . '</title></rect></g>';
            if ($i % 5 === 0 || $i === $n - 1) {
                $svg .= '<text x="' . round($pad + $i * $bw + $bw / 2, 1) . '" y="' . ($h + 16) . '" text-anchor="middle" font-size="11" fill="currentColor" fill-opacity=".5">' . date('d.m', strtotime($date)) . '</text>';
            }
            $i++;
        }
        return $svg . '</svg>';
    }

    public static function format(float|int $value, string $format = 'count'): string
    {
        return $format === 'money' ? number_format($value, 2, ',', '.') . ' ₺' : number_format($value, 0, ',', '.');
    }

    // Önceki 30 güne göre değişim (yüzde) — "son 30 gün toplamı"nı geçen dönemle kıyaslamak için
    public static function previousTotal(string $table, string $column, string $value = 'COUNT(*)', string $where = '1 = 1', array $bindings = []): float
    {
        try {
            return (float) Database::value("SELECT COALESCE($value, 0) FROM `$table` WHERE `$column` >= ? AND `$column` < ? AND ($where)",
                [date('Y-m-d 00:00:00', strtotime('-' . (self::DAYS * 2 - 1) . ' days')), date('Y-m-d 00:00:00', strtotime('-' . (self::DAYS - 1) . ' days')), ...$bindings]);
        } catch (\Throwable) {
            return 0;
        }
    }
}
