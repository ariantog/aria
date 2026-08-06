<?php

namespace App\Services\Restock;

use App\Models\RestockSheet;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RestockSheetExportService
{
    public function __construct(
        protected RestockGridBuilder $gridBuilder,
    ) {}

    public function download(RestockSheet $sheet): StreamedResponse
    {
        $grid = $this->gridBuilder->build($sheet);
        $spreadsheet = new Spreadsheet;

        foreach ($grid['parents'] as $index => $parent) {
            $worksheet = $index === 0
                ? $spreadsheet->getActiveSheet()
                : $spreadsheet->createSheet($index);

            $worksheet->setTitle($this->safeSheetTitle($parent['pcode']));
            $this->writeParentSheet($worksheet, $parent);
        }

        if ($grid['parents'] === []) {
            $spreadsheet->getActiveSheet()->setTitle('Empty');
            $spreadsheet->getActiveSheet()->setCellValue('A1', 'No data to export.');
        }

        $filename = sprintf(
            'restock-%s-%s.xlsx',
            Str::slug($sheet->name ?: 'sheet'),
            now()->format('Y-m-d'),
        );

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @param  array{pcode: string, name: string, sizes: list<string>, rows: list<array<string, mixed>>}  $parent
     */
    protected function writeParentSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet, array $parent): void
    {
        $worksheet->setCellValue('A1', $parent['name']);
        $worksheet->setCellValue('A2', $parent['pcode']);
        $worksheet->getStyle('A1')->getFont()->setBold(true);

        $stages = [
            ['key' => 'restock', 'title' => 'Restock', 'color' => 'DBEAFE'],
            ['key' => 'production', 'title' => 'Production', 'color' => 'FDE68A'],
            ['key' => 'shipped', 'title' => 'Shipped', 'color' => 'E5E7EB'],
            ['key' => 'stock', 'title' => 'Stock', 'color' => 'D1FAE5'],
        ];

        $col = 2;
        $headerRow = 4;
        $worksheet->setCellValue('A'.$headerRow, 'Color');
        $worksheet->getStyle('A'.$headerRow)->getFont()->setBold(true);

        $columns = [];

        foreach ($stages as $stage) {
            $stageStart = $col;

            foreach ($parent['sizes'] as $size) {
                $prefix = $this->fieldPrefix($size);
                $field = $prefix.$stage['key'];
                $label = $parent['sizes'] === ['—'] ? $stage['title'] : "{$size} {$stage['title']}";
                $worksheet->setCellValue([$col, $headerRow], $label);
                $columns[] = ['field' => $field, 'col' => $col];
                $col++;
            }

            if (count($parent['sizes']) > 1) {
                $totalField = $stage['key'].'_total';
                $worksheet->setCellValue([$col, $headerRow], $stage['title'].' Total');
                $columns[] = ['field' => $totalField, 'col' => $col];
                $col++;
            }

            $worksheet->getStyle([$stageStart, $headerRow, $col - 1, $headerRow])
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FF'.$stage['color']);
        }

        $rowNum = $headerRow + 1;
        foreach ($parent['rows'] as $row) {
            $worksheet->setCellValue('A'.$rowNum, $row['color_name'] ?? '—');
            foreach ($columns as $column) {
                $worksheet->setCellValue([$column['col'], $rowNum], $row[$column['field']] ?? 0);
            }
            $rowNum++;
        }

        foreach (range(1, $col - 1) as $columnIndex) {
            $worksheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }
    }

    protected function fieldPrefix(string $sizeCode): string
    {
        if ($sizeCode === '—') {
            return '';
        }

        return str_replace(['.', ' '], '_', strtolower($sizeCode)).'_';
    }

    protected function safeSheetTitle(string $pcode): string
    {
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '-', $pcode) ?: 'Sheet';

        return Str::limit($title, 31, '');
    }
}
