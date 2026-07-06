<?php

namespace App\Services\Legacy;

class LegacyMonthMapper
{
    private const MONTHS = [
        'january' => 1,
        'february' => 2,
        'march' => 3,
        'april' => 4,
        'may' => 5,
        'june' => 6,
        'july' => 7,
        'august' => 8,
        'september' => 9,
        'october' => 10,
        'november' => 11,
        'december' => 12,
    ];

    public function toBillingMonth(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (is_numeric($normalized)) {
            $month = (int) $normalized;

            return ($month >= 1 && $month <= 12) ? $month : null;
        }

        return self::MONTHS[$normalized] ?? null;
    }

    /**
     * @return array{month: int, year: int}|null
     */
    public function fromMonthYear(?string $monthName, int|string|null $year): ?array
    {
        $month = $this->toBillingMonth($monthName);
        $yearInt = $year !== null && $year !== '' ? (int) $year : 0;

        if ($month === null || $yearInt < 2000 || $yearInt > 2100) {
            return null;
        }

        return ['month' => $month, 'year' => $yearInt];
    }

    /**
     * @return array{month: int, year: int}
     */
    public function fromTimestamp(string $timestamp): array
    {
        $time = strtotime($timestamp);

        return [
            'month' => (int) date('n', $time),
            'year' => (int) date('Y', $time),
        ];
    }
}
