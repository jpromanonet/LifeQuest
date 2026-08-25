<?php

declare(strict_types=1);

final class MonthProgress
{
    public const MONTH_LABELS = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    /** @return array<int, bool> */
    public static function emptyMap(): array
    {
        $map = [];
        for ($m = 1; $m <= 12; $m++) {
            $map[$m] = false;
        }
        return $map;
    }

    public static function percentFromCount(int $checked): float
    {
        $checked = max(0, min(12, $checked));
        return round(($checked / 12) * 100, 2);
    }

    /**
     * @param array<int, bool> $months
     * @return array{months: array<int,bool>, checked: int, percent: float}
     */
    public static function summary(array $months): array
    {
        $map = self::emptyMap();
        foreach ($months as $m => $on) {
            $m = (int) $m;
            if ($m >= 1 && $m <= 12) {
                $map[$m] = (bool) $on;
            }
        }
        $checked = count(array_filter($map));
        return [
            'months' => $map,
            'checked' => $checked,
            'percent' => self::percentFromCount($checked),
        ];
    }
}
