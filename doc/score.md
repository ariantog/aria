<?php

function hitungPerformaInventaris(array $item): array
{
    const MAX_GAP  = 90;
    const MAX_DAYS = 90;

    $gapDays  = $item['gap_days']                       ?? null;
    $daysAgo  = $item['current_warehouse']['days_ago']  ?? null;
    $lastSale = $item['current_warehouse']['last_sale'] ?? null;

    if ($lastSale === null(atau never sold) || $daysAgo === null) {
        return [
            'level'       => 6,
            'key'         => 'critical',
            'label'       => 'Critical (Belum Terjual)',
            'gap_score'   => null,
            'sale_score'  => null,
            'final_score' => null,
        ];
    }

    if ($daysAgo > MAX_DAYS) {
        return [
            'level'       => 5,
            'key'         => 'deadstock',
            'label'       => 'Deadstock (Tidak Bergerak)',
            'gap_score'   => null,
            'sale_score'  => null,
            'final_score' => null,
        ];
    }

    $gapScore  = max(0.0, min(1.0, 1 - ($gapDays / MAX_GAP)));
    $saleScore = max(0.0, min(1.0, 1 - ($daysAgo / MAX_DAYS)));
    $final     = ($gapScore * 0.4) + ($saleScore * 0.6);

    if ($final >= 0.80) {
        $level = 1; $key = 'elite';    $label = 'Elite (Terbaik)';
    } elseif ($final >= 0.60) {
        $level = 2; $key = 'good';     $label = 'Good (Aktif)';
    } elseif ($final >= 0.40) {
        $level = 3; $key = 'lagging';  $label = 'Lagging (Lambat)';
    } else {
        $level = 4; $key = 'stagnant'; $label = 'Stagnant (Sangat Lambat)';
    }

    return [
        'level'       => $level,
        'key'         => $key,
        'label'       => $label,
        'gap_score'   => round($gapScore,  4),
        'sale_score'  => round($saleScore, 4),
        'final_score' => round($final,     4),
    ];
}