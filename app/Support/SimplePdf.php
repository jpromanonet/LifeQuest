<?php

declare(strict_types=1);


/** Colorful weekly-report PDF (WinAnsi + RGB fills). */
final class SimplePdf
{
    /** @var list<array<string, mixed>> */
    private array $blocks = [];

    public function banner(string $title, string $subtitle, string $badge = ''): void
    {
        $this->blocks[] = [
            'type' => 'banner',
            'title' => $title,
            'subtitle' => $subtitle,
            'badge' => $badge,
        ];
    }

    /** @param list<array{label:string,value:string,color:array{0:float,1:float,2:float}}> $items */
    public function metrics(array $items): void
    {
        $this->blocks[] = ['type' => 'metrics', 'items' => $items];
    }

    /** @param array{0:float,1:float,2:float} $color */
    public function section(string $title, array $color, string $count = ''): void
    {
        $this->blocks[] = [
            'type' => 'section',
            'title' => $title,
            'count' => $count,
            'color' => $color,
        ];
    }

    public function item(string $text, string $meta = '', bool $done = false): void
    {
        $this->blocks[] = [
            'type' => 'item',
            'text' => $text,
            'meta' => $meta,
            'done' => $done,
        ];
    }

    /** @param array{0:float,1:float,2:float} $color */
    public function total(string $text, array $color): void
    {
        $this->blocks[] = ['type' => 'total', 'text' => $text, 'color' => $color];
    }

    public function empty(string $text): void
    {
        $this->blocks[] = ['type' => 'empty', 'text' => $text];
    }

    public function footer(string $text): void
    {
        $this->blocks[] = ['type' => 'footer', 'text' => $text];
    }

    public function render(): string
    {
        $pages = $this->buildPages();
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $pageCount = count($pages);
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = (3 + $i * 2) . ' 0 R';
        }
        $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';

        foreach ($pages as $i => $content) {
            $pageObj = 3 + $i * 2;
            $contentObj = $pageObj + 1;
            $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents '
                . $contentObj . ' 0 R /Resources << /Font << '
                . '/F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >> '
                . '/F2 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >> '
                . '>> >> >>';
            $objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    /** @return list<string> */
    private function buildPages(): array
    {
        $pages = [];
        $ops = [];
        $y = 842.0;
        $marginX = 40.0;
        $contentW = 515.0;
        $bottom = 48.0;
        $pageNo = 1;

        $newPage = function () use (&$pages, &$ops, &$y, &$pageNo): void {
            if ($ops !== []) {
                $pages[] = implode("\n", $ops);
            }
            $ops = [];
            $y = 842.0;
            $pageNo++;
            // soft page background
            $ops[] = self::rect(0, 0, 595, 842, [0.973, 0.969, 0.961], true);
        };

        $ops[] = self::rect(0, 0, 595, 842, [0.973, 0.969, 0.961], true);

        $ensure = function (float $need) use (&$newPage, &$y, $bottom): void {
            if ($y - $need < $bottom) {
                $newPage();
            }
        };

        foreach ($this->blocks as $block) {
            $type = (string) $block['type'];

            if ($type === 'banner') {
                $ensure(118);
                $ops[] = self::rect(0, $y - 108, 595, 108, [0.055, 0.431, 0.416], true);
                $ops[] = self::rect(0, $y - 112, 595, 4, [0.867, 0.545, 0.227], true);
                $ops[] = self::text(50, $y - 36, self::winAnsi((string) $block['title']), 18, true, [1, 1, 1]);
                $ops[] = self::text(50, $y - 58, self::winAnsi((string) $block['subtitle']), 11, false, [0.82, 0.93, 0.91]);
                if ((string) $block['badge'] !== '') {
                    $badge = self::winAnsi((string) $block['badge']);
                    $bw = max(78, strlen($badge) * 5.4 + 24);
                    $bx = 595 - 40 - $bw;
                    $ops[] = self::roundish($bx, $y - 52, $bw, 24, [1, 1, 1]);
                    $ops[] = self::text($bx + 12, $y - 44, $badge, 9, true, [0.055, 0.431, 0.416]);
                }
                $y -= 124;
                continue;
            }

            if ($type === 'metrics') {
                /** @var list<array{label:string,value:string,color:array{0:float,1:float,2:float}}> $items */
                $items = $block['items'];
                $n = max(1, count($items));
                $gap = 10.0;
                $cardW = ($contentW - $gap * ($n - 1)) / $n;
                $ensure(64);
                $x = $marginX;
                foreach ($items as $item) {
                    $ops[] = self::roundish($x, $y - 54, $cardW, 54, [1, 1, 1]);
                    $ops[] = self::rect($x, $y - 54, 4, 54, $item['color'], true);
                    $labelChunks = self::wrap($item['label'], max(10, (int) floor($cardW / 4.6)));
                    $ly = $y - 16;
                    foreach (array_slice($labelChunks, 0, 2) as $chunk) {
                        $ops[] = self::text($x + 10, $ly, self::winAnsi($chunk), 7, false, [0.42, 0.45, 0.48]);
                        $ly -= 9;
                    }
                    $ops[] = self::text($x + 10, $y - 44, self::winAnsi($item['value']), 13, true, $item['color']);
                    $x += $cardW + $gap;
                }
                $y -= 68;
                continue;
            }

            if ($type === 'section') {
                $ensure(40);
                $color = $block['color'];
                $tint = self::tint($color, 0.88);
                $ops[] = self::roundish($marginX, $y - 28, $contentW, 28, $tint);
                $ops[] = self::rect($marginX, $y - 28, 5, 28, $color, true);
                $ops[] = self::text($marginX + 14, $y - 19, self::winAnsi((string) $block['title']), 11, true, $color);
                if ((string) $block['count'] !== '') {
                    $count = self::winAnsi((string) $block['count']);
                    $cw = max(32, strlen($count) * 5.6 + 16);
                    $cx = $marginX + $contentW - $cw - 8;
                    $ops[] = self::roundish($cx, $y - 24, $cw, 20, $color);
                    $ops[] = self::text($cx + 8, $y - 17, $count, 9, true, [1, 1, 1]);
                }
                $y -= 36;
                continue;
            }

            if ($type === 'empty') {
                $ensure(26);
                $ops[] = self::roundish($marginX, $y - 22, $contentW, 22, [1, 1, 1]);
                $ops[] = self::text($marginX + 12, $y - 15, self::winAnsi((string) $block['text']), 9, false, [0.55, 0.55, 0.55]);
                $y -= 28;
                continue;
            }

            if ($type === 'item') {
                $meta = (string) $block['meta'];
                $chunks = self::wrap((string) $block['text'], 62);
                $rows = max(1, count($chunks));
                $h = 12.0 + $rows * 12.0 + ($meta !== '' ? 11.0 : 0);
                $ensure($h + 6);
                $ops[] = self::roundish($marginX, $y - $h, $contentW, $h, [1, 1, 1]);
                $ops[] = self::circle($marginX + 14, $y - 14, 3.2, !empty($block['done']) ? [0.055, 0.431, 0.416] : [0.78, 0.8, 0.82]);
                $ty = $y - 16;
                foreach ($chunks as $chunk) {
                    $ops[] = self::text(
                        $marginX + 26,
                        $ty,
                        self::winAnsi($chunk),
                        10,
                        empty($block['done']),
                        !empty($block['done']) ? [0.5, 0.5, 0.5] : [0.15, 0.18, 0.22]
                    );
                    $ty -= 12;
                }
                if ($meta !== '') {
                    $ops[] = self::text($marginX + 26, $ty, self::winAnsi($meta), 8, false, [0.45, 0.48, 0.52]);
                }
                $y -= $h + 5;
                continue;
            }

            if ($type === 'total') {
                $ensure(30);
                $color = $block['color'];
                $ops[] = self::roundish($marginX, $y - 24, $contentW, 24, self::tint($color, 0.85));
                $ops[] = self::text($marginX + 12, $y - 16, self::winAnsi((string) $block['text']), 10, true, $color);
                $y -= 32;
                continue;
            }

            if ($type === 'footer') {
                $ensure(24);
                $ops[] = self::text($marginX, 28, self::winAnsi((string) $block['text']), 8, false, [0.55, 0.55, 0.55]);
                continue;
            }
        }

        // page footers with numbers
        if ($ops !== []) {
            $pages[] = implode("\n", $ops);
        }
        if ($pages === []) {
            $pages[] = self::rect(0, 0, 595, 842, [0.973, 0.969, 0.961], true);
        }

        $total = count($pages);
        foreach ($pages as $i => $content) {
            $label = self::winAnsi('Página ' . ($i + 1) . ' / ' . $total);
            $pages[$i] = $content . "\n" . self::text(480, 28, $label, 8, false, [0.55, 0.55, 0.55]);
        }

        return $pages;
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private static function rect(float $x, float $y, float $w, float $h, array $rgb, bool $fill): string
    {
        return sprintf(
            "%.3F %.3F %.3F rg\n%.2F %.2F %.2F %.2F re %s",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $x,
            $y,
            $w,
            $h,
            $fill ? 'f' : 'S'
        );
    }

    /** Approximate rounded card with a filled rect (PDF Type1, no true rounded corners). */
    /** @param array{0:float,1:float,2:float} $rgb */
    private static function roundish(float $x, float $y, float $w, float $h, array $rgb): string
    {
        return self::rect($x, $y, $w, $h, $rgb, true);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private static function circle(float $cx, float $cy, float $r, array $rgb): string
    {
        // Bézier circle approximation
        $k = $r * 0.5522847498;
        return sprintf(
            "%.3F %.3F %.3F rg\n%.2F %.2F m\n%.2F %.2F %.2F %.2F %.2F %.2F c\n%.2F %.2F %.2F %.2F %.2F %.2F c\n%.2F %.2F %.2F %.2F %.2F %.2F c\n%.2F %.2F %.2F %.2F %.2F %.2F c\nf",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $cx + $r,
            $cy,
            $cx + $r,
            $cy + $k,
            $cx + $k,
            $cy + $r,
            $cx,
            $cy + $r,
            $cx - $k,
            $cy + $r,
            $cx - $r,
            $cy + $k,
            $cx - $r,
            $cy,
            $cx - $r,
            $cy - $k,
            $cx - $k,
            $cy - $r,
            $cx,
            $cy - $r,
            $cx + $k,
            $cy - $r,
            $cx + $r,
            $cy - $k,
            $cx + $r,
            $cy
        );
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private static function text(float $x, float $y, string $text, float $size, bool $bold, array $rgb): string
    {
        return sprintf(
            "BT\n%.3F %.3F %.3F rg\n/%s %.1F Tf\n1 0 0 1 %.2F %.2F Tm (%s) Tj\nET",
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $bold ? 'F2' : 'F1',
            $size,
            $x,
            $y,
            self::escape($text)
        );
    }

    /** @param array{0:float,1:float,2:float} $rgb @return array{0:float,1:float,2:float} */
    private static function tint(array $rgb, float $amount): array
    {
        return [
            $rgb[0] + (1 - $rgb[0]) * $amount,
            $rgb[1] + (1 - $rgb[1]) * $amount,
            $rgb[2] + (1 - $rgb[2]) * $amount,
        ];
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /** @return list<string> UTF-8 chunks */
    private static function wrap(string $text, int $width): array
    {
        if ($text === '') {
            return [''];
        }
        $words = preg_split('/\s+/u', $text) ?: [$text];
        $rows = [];
        $current = '';
        foreach ($words as $word) {
            $trial = $current === '' ? $word : $current . ' ' . $word;
            if (mb_strlen($trial, 'UTF-8') > $width && $current !== '') {
                $rows[] = $current;
                $current = $word;
            } else {
                $current = $trial;
            }
        }
        if ($current !== '') {
            $rows[] = $current;
        }
        return $rows;
    }

    private static function winAnsi(string $text): string
    {
        // Convert UTF-8 → WinAnsi once. Never iconv after replacing accents
        // with Latin-1 bytes (that drops é/á/í as invalid UTF-8).
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
        if ($converted !== false) {
            return $converted;
        }

        return strtr($text, [
            'á' => "\xE1", 'é' => "\xE9", 'í' => "\xED", 'ó' => "\xF3", 'ú' => "\xFA",
            'Á' => "\xC1", 'É' => "\xC9", 'Í' => "\xCD", 'Ó' => "\xD3", 'Ú' => "\xDA",
            'ñ' => "\xF1", 'Ñ' => "\xD1", 'ü' => "\xFC", 'Ü' => "\xDC",
            '¿' => "\xBF", '¡' => "\xA1", '°' => "\xB0", '–' => '-', '—' => '-',
            '“' => '"', '”' => '"', '‘' => "'", '’' => "'", '·' => "\xB7",
        ]);
    }
}
