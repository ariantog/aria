<?php

namespace App\Support;

/**
 * Target operation categories after ledger simplification (plan/reporting/08).
 *
 * @phpstan-type OperationRow array{name: string, report_slug: string, description?: string}
 */
final class OperationSimplificationPlan
{
  /** @return array<int, OperationRow> */
  public static function newOperations(): array
  {
    return [
      29 => [
        'name' => 'Biaya Marketplace',
        'report_slug' => 'marketplace',
        'description' => 'Online channel and dept-store partner fees',
      ],
      30 => [
        'name' => 'Biaya Toko',
        'report_slug' => 'toko',
        'description' => 'Physical shop upkeep (WTC, Citos)',
      ],
      31 => [
        'name' => 'Penyesuaian',
        'report_slug' => 'penyesuaian',
        'description' => 'Adjustments, rounding, write-offs',
      ],
    ];
  }

  /** @return array<int, OperationRow> */
  public static function renames(): array
  {
    return [
      3 => ['name' => 'Marketing Umum', 'report_slug' => 'marketing'],
      4 => ['name' => 'Gaji & Upah', 'report_slug' => 'gaji'],
      7 => ['name' => 'Sewa HQ', 'report_slug' => 'sewa', 'description' => 'HQ rent only (Sambisari, Gedung)'],
      8 => ['name' => 'Kantor & Utilitas', 'report_slug' => 'kantor'],
      10 => ['name' => 'Perbankan', 'report_slug' => 'bank'],
      12 => ['name' => 'Asuransi', 'report_slug' => 'kantor'],
      13 => ['name' => 'Perawatan & Mesin', 'report_slug' => 'maintenance'],
      14 => ['name' => 'Jasa Profesional', 'report_slug' => 'jasa'],
      17 => ['name' => 'Logistik', 'report_slug' => 'logistik'],
      18 => ['name' => 'Pajak & Retribusi', 'report_slug' => 'pajak'],
      20 => ['name' => 'Perijinan', 'report_slug' => 'lain'],
      21 => ['name' => 'Kesejahteraan Karyawan', 'report_slug' => 'sdm'],
      22 => ['name' => 'Lain-lain', 'report_slug' => 'lain'],
      27 => ['name' => 'Produksi', 'report_slug' => 'produksi', 'description' => 'Material, biaya produksi, permak'],
    ];
  }

  /** @return list<int> */
  public static function softDeleteOperationIds(): array
  {
    return [9, 11, 15, 19, 24, 25, 26, 28];
  }

  /** @return array<int, int> old parent_id => new parent_id */
  public static function bulkReparentByOperationEarly(): array
  {
    return [
      9 => 8,
      16 => 8,
      15 => 21,
      19 => 22,
      26 => 22,
      11 => 3,
      25 => 22,
    ];
  }

  /** Run after ledger-specific reparents empty obsolete categories. */
  public static function bulkReparentByOperationLate(): array
  {
    return [
      24 => 31,
      28 => 22,
    ];
  }

  /** @return array<int, int> ledger id => operation id */
  public static function ledgerReparents(): array
  {
    $marketplace = [
      2234, 2273, 2788, 2881, 2899, 2099, 2178, 2633, 2719, 2957, 2963, 2964,
      2250, 2640, 2691, 2070, 2724,
    ];
    $toko = [2889, 2842];
    $produksi = [885];
    $penyesuaian = [879, 880, 2252, 2857, 882, 883, 884, 886, 887, 888, 908, 1303, 2488, 2493];

    $map = [];
    foreach ($marketplace as $id) {
      $map[$id] = 29;
    }
    foreach ($toko as $id) {
      $map[$id] = 30;
    }
    foreach ($produksi as $id) {
      $map[$id] = 27;
    }
    foreach ($penyesuaian as $id) {
      $map[$id] = 31;
    }

    return $map;
  }
}
