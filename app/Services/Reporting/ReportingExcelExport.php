<?php

namespace App\Services\Reporting;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingExcelExport
{
    /**
     * Stream a single-sheet workbook. Each section may have a title, header row, and data rows.
     *
     * @param  list<array{title?: string, headers?: list<string>, rows?: list<list<mixed>>}>  $sections
     */
    public function download(string $filename, string $sheetTitle, array $sections): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        $rowNum = 1;
        $maxCols = 1;

        foreach ($sections as $section) {
            if ($rowNum > 1) {
                $rowNum++;
            }

            if (! empty($section['title'])) {
                $sheet->setCellValue('A'.$rowNum, $section['title']);
                $sheet->getStyle('A'.$rowNum)->getFont()->setBold(true);
                $rowNum++;
            }

            $headers = $section['headers'] ?? [];
            if ($headers !== []) {
                $sheet->fromArray($headers, null, 'A'.$rowNum);
                $lastCol = $this->columnLetter(count($headers));
                $sheet->getStyle('A'.$rowNum.':'.$lastCol.$rowNum)->getFont()->setBold(true);
                $maxCols = max($maxCols, count($headers));
                $rowNum++;
            }

            foreach ($section['rows'] ?? [] as $row) {
                $values = array_values($row);
                $sheet->fromArray($values, null, 'A'.$rowNum);
                $maxCols = max($maxCols, count($values));
                $rowNum++;
            }
        }

        $lastColumn = $this->columnLetter($maxCols);
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function columnLetter(int $columnIndex): string
    {
        $letter = '';
        $index = max(1, $columnIndex);
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
