<?php

namespace App\Services\Restock;

use App\Models\RestockSheet;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RestockSheetExportService
{
    /** @var list<string> */
    public const ALL_STAGES = ['restock', 'production', 'shipped', 'stock'];

    /** @var array<string, array{title: string, color: string}> */
    protected const STAGE_META = [
        'restock' => ['title' => 'Restock', 'color' => 'DBEAFE'],
        'production' => ['title' => 'Production', 'color' => 'FDE68A'],
        'shipped' => ['title' => 'Shipped', 'color' => 'E5E7EB'],
        'stock' => ['title' => 'Stock', 'color' => 'D1FAE5'],
    ];

    public function __construct(
        protected RestockGridBuilder $gridBuilder,
        protected RestockSettingsService $settingsService,
    ) {}

    /**
     * @param  list<string>|null  $stages
     */
    public function download(RestockSheet $sheet, ?array $stages = null): StreamedResponse
    {
        return $this->downloadMany(collect([$sheet]), $stages);
    }

    /**
     * @param  Collection<int, RestockSheet>|list<RestockSheet>  $sheets
     * @param  list<string>|null  $stages
     */
    public function downloadMany(Collection|array $sheets, ?array $stages = null): StreamedResponse
    {
        $sheets = $sheets instanceof Collection ? $sheets->values() : collect($sheets)->values();
        $stages = $this->normalizeStages($stages);
        $costField = $this->settingsService->exportCostField();
        $costLabel = $this->settingsService->exportCostColumnLabel();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        if ($sheets->isEmpty()) {
            $worksheet = $spreadsheet->createSheet();
            $worksheet->setTitle('Restock');
            $worksheet->setCellValue('A1', 'No sheets selected.');
        } else {
            foreach ($sheets as $index => $sheet) {
                $worksheet = $spreadsheet->createSheet($index);
                $worksheet->setTitle($this->safeSheetTitle($sheet->name ?: 'Restock '.$sheet->id));
                $grid = $this->gridBuilder->build($sheet);
                $cellCosts = $this->cellCostMap($sheet, $costField);

                if ($grid['parents'] === []) {
                    $worksheet->setCellValue('A1', 'No data to export.');
                } else {
                    $rowNum = 1;
                    foreach ($grid['parents'] as $parentIndex => $parent) {
                        if ($parentIndex > 0) {
                            $rowNum += 2;
                        }
                        $rowNum = $this->writeParentSection(
                            $worksheet,
                            $parent,
                            $rowNum,
                            $stages,
                            $costLabel,
                            $cellCosts,
                        );
                    }
                }
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = $sheets->count() === 1
            ? sprintf(
                'restock-%s-%s.xlsx',
                Str::slug($sheets->first()->name ?: 'sheet'),
                now()->format('Y-m-d'),
            )
            : sprintf('restock-export-%s.xlsx', now()->format('Y-m-d'));

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @param  list<string>|null  $stages
     * @return list<string>
     */
    public function normalizeStages(?array $stages): array
    {
        if ($stages === null || $stages === []) {
            return self::ALL_STAGES;
        }

        $normalized = array_values(array_unique(array_filter(
            $stages,
            fn (string $stage) => in_array($stage, self::ALL_STAGES, true),
        )));

        return $normalized !== [] ? $normalized : self::ALL_STAGES;
    }

    /**
     * @return array<int, float>
     */
    protected function cellCostMap(RestockSheet $sheet, string $costField): array
    {
        $sheet->loadMissing(['cells.item']);

        $map = [];
        foreach ($sheet->cells as $cell) {
            $map[(int) $cell->id] = (float) ($cell->item?->{$costField} ?? 0);
        }

        return $map;
    }

    /**
     * @param  array{pcode: string, name: string, sizes: list<string>, rows: list<array<string, mixed>>}  $parent
     * @param  list<string>  $stages
     * @param  array<int, float>  $cellCosts
     */
    protected function writeParentSection(
        Worksheet $worksheet,
        array $parent,
        int $startRow,
        array $stages,
        string $costLabel,
        array $cellCosts,
    ): int {
        $worksheet->setCellValue('A'.$startRow, $parent['name']);
        $worksheet->setCellValue('A'.($startRow + 1), $parent['pcode']);
        $worksheet->getStyle('A'.$startRow)->getFont()->setBold(true);

        $col = 2;
        $headerRow = $startRow + 3;
        $worksheet->setCellValue('A'.$headerRow, 'Color');
        $worksheet->getStyle('A'.$headerRow)->getFont()->setBold(true);

        $columns = [];

        foreach ($parent['sizes'] as $size) {
            $prefix = $this->fieldPrefix($size);
            $costCol = $col;
            $costHeader = $parent['sizes'] === ['—'] ? $costLabel : "{$size} {$costLabel}";
            $worksheet->setCellValue([$col, $headerRow], $costHeader);
            $columns[] = ['kind' => 'cost', 'prefix' => $prefix, 'col' => $col];
            $col++;

            foreach ($stages as $stageKey) {
                $meta = self::STAGE_META[$stageKey];
                $field = $prefix.$stageKey;
                $label = $parent['sizes'] === ['—']
                    ? $meta['title']
                    : "{$size} {$meta['title']}";
                $stageStart = $col;
                $worksheet->setCellValue([$col, $headerRow], $label);
                $columns[] = ['kind' => 'qty', 'field' => $field, 'col' => $col, 'stage' => $stageKey];
                $col++;

                $worksheet->getStyle([$stageStart, $headerRow, $col - 1, $headerRow])
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FF'.$meta['color']);
            }
        }

        if (count($parent['sizes']) > 1) {
            foreach ($stages as $stageKey) {
                $meta = self::STAGE_META[$stageKey];
                $totalField = $stageKey.'_total';
                $worksheet->setCellValue([$col, $headerRow], $meta['title'].' Total');
                $columns[] = ['kind' => 'qty', 'field' => $totalField, 'col' => $col, 'stage' => $stageKey];
                $col++;

                $worksheet->getStyle([$col - 1, $headerRow, $col - 1, $headerRow])
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FF'.$meta['color']);
            }
        }

        $rowNum = $headerRow + 1;
        foreach ($parent['rows'] as $row) {
            $worksheet->setCellValue('A'.$rowNum, $row['color_name'] ?? '—');
            foreach ($columns as $column) {
                if ($column['kind'] === 'cost') {
                    $meta = $row['_meta'][$column['prefix']] ?? null;
                    $cellId = (int) ($meta['cell_id'] ?? 0);
                    $worksheet->setCellValue([$column['col'], $rowNum], $cellCosts[$cellId] ?? 0);

                    continue;
                }

                $worksheet->setCellValue([$column['col'], $rowNum], $row[$column['field']] ?? 0);
            }
            $rowNum++;
        }

        foreach (range(1, max(1, $col - 1)) as $columnIndex) {
            $worksheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        return $rowNum;
    }

    protected function fieldPrefix(string $sizeCode): string
    {
        if ($sizeCode === '—') {
            return '';
        }

        return str_replace(['.', ' '], '_', strtolower($sizeCode)).'_';
    }

    protected function safeSheetTitle(string $title): string
    {
        $sanitized = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '-', $title) ?: 'Restock';

        return Str::limit($sanitized, 31, '');
    }
}
