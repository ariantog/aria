<?php

namespace App\Services\Restock;

use App\Models\RestockSheet;
use App\Support\ItemImageResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
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

    private const COL_IMAGE = 1;

    private const COL_COLOR = 2;

    private const COL_COST = 3;

    private const IMAGE_HEIGHT = 32;

    private const IMAGE_WIDTH = 32;

    /** @var list<string> */
    protected array $tempImageFiles = [];

    public function __construct(
        protected RestockGridBuilder $gridBuilder,
        protected RestockSettingsService $settingsService,
        protected ItemImageResolver $imageResolver,
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
        $costLabel = $this->settingsService->exportCostColumnLabel();
        $this->tempImageFiles = [];

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
                $cellCosts = $this->cellCostMap($sheet, $this->settingsService->exportCostField());

                if ($grid['blocks'] === []) {
                    $worksheet->setCellValue('A1', 'No data to export.');
                } else {
                    $rowNum = 1;
                    foreach ($grid['blocks'] as $blockIndex => $block) {
                        if ($blockIndex > 0) {
                            $rowNum += 2;
                        }
                        if (count($grid['blocks']) > 1) {
                            $worksheet->setCellValue('A'.$rowNum, $block['title'] ?? 'Block');
                            $worksheet->getStyle('A'.$rowNum)->getFont()->setBold(true);
                            $rowNum++;
                        }
                        $rowNum = $block['kind'] === 'flat'
                            ? $this->writeFlatBlock($worksheet, $block, $rowNum, $stages, $costLabel, $cellCosts)
                            : $this->writeMatrixBlock($worksheet, $block, $rowNum, $stages, $costLabel, $cellCosts);
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
            try {
                (new Xlsx($spreadsheet))->save('php://output');
            } finally {
                $this->cleanupHeldImages();
            }
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
     * @param  array{kind: 'matrix', sizes: list<string>, rows: list<array<string, mixed>>}  $block
     * @param  list<string>  $stages
     * @param  array<int, float>  $cellCosts
     */
    protected function writeMatrixBlock(
        Worksheet $worksheet,
        array $block,
        int $startRow,
        array $stages,
        string $costLabel,
        array $cellCosts,
    ): int {
        $sizes = $block['sizes'];
        $rowNum = $this->writeMatrixHeader($worksheet, $startRow, $sizes, $stages, $costLabel);

        $rows = $block['rows'];
        $pendingCostMerge = null;

        foreach ($rows as $index => $blockRow) {
            if (($blockRow['_type'] ?? '') === 'section') {
                if ($pendingCostMerge !== null) {
                    $this->finalizeCostMerge($worksheet, $pendingCostMerge, $rowNum - 1);
                }

                if (! empty($blockRow['_section_divider'])) {
                    $rowNum++;
                }

                $groupCost = $this->resolveParentGroupCost($rows, $index, $cellCosts);
                $pendingCostMerge = ['start' => $rowNum, 'value' => $groupCost];

                $title = trim((string) ($blockRow['name'] ?? ''));
                $pcode = trim((string) ($blockRow['pcode'] ?? ''));
                $colorText = $title !== '' ? $title : $pcode;
                if ($pcode !== '' && strtoupper($pcode) !== strtoupper($title)) {
                    $colorText = $colorText."\n".$pcode;
                }

                $worksheet->setCellValue([self::COL_COLOR, $rowNum], $colorText);
                $worksheet->getStyle([self::COL_COLOR, $rowNum])->getFont()->setBold(true);
                $worksheet->getStyle([self::COL_COLOR, $rowNum])->getAlignment()->setWrapText(true);
                $this->embedSectionImage($worksheet, $rowNum, $blockRow);

                $col = self::COL_COST + 1;
                $parentSizes = $blockRow['sizes'] ?? [];
                foreach ($stages as $stageKey) {
                    foreach ($sizes as $size) {
                        $label = in_array($size, $parentSizes, true) ? $size : '—';
                        $worksheet->setCellValue([$col, $rowNum], $label);
                        $worksheet->getStyle([$col, $rowNum])->getFont()->setBold(true);
                        $col++;
                    }
                    if (count($sizes) > 1) {
                        $worksheet->setCellValue([$col, $rowNum], 'Total');
                        $worksheet->getStyle([$col, $rowNum])->getFont()->setBold(true);
                        $col++;
                    }
                }

                $rowNum++;

                continue;
            }

            if (($blockRow['_type'] ?? '') !== 'data') {
                continue;
            }

            $worksheet->setCellValue([self::COL_COLOR, $rowNum], $blockRow['color_name'] ?? '—');
            $col = self::COL_COST + 1;
            $parentSizes = $blockRow['parent_sizes'] ?? [];
            foreach ($stages as $stageKey) {
                foreach ($sizes as $size) {
                    if (! in_array($size, $parentSizes, true)) {
                        $worksheet->setCellValue([$col, $rowNum], '—');
                    } else {
                        $prefix = $this->fieldPrefix($size);
                        $worksheet->setCellValue([$col, $rowNum], $blockRow[$prefix.$stageKey] ?? 0);
                    }
                    $col++;
                }
                if (count($sizes) > 1) {
                    $worksheet->setCellValue([$col, $rowNum], $blockRow[$stageKey.'_total'] ?? 0);
                    $col++;
                }
            }

            $rowNum++;
        }

        if ($pendingCostMerge !== null) {
            $this->finalizeCostMerge($worksheet, $pendingCostMerge, $rowNum - 1);
        }

        $this->autoSizeColumns($worksheet, $this->matrixLastColumn($sizes, $stages));

        return $rowNum;
    }

    /**
     * @param  list<string>  $sizes
     * @param  list<string>  $stages
     */
    protected function writeMatrixHeader(
        Worksheet $worksheet,
        int $startRow,
        array $sizes,
        array $stages,
        string $costLabel,
    ): int {
        $headerRow = $startRow;
        $subHeaderRow = $startRow + 1;

        $worksheet->setCellValue([self::COL_IMAGE, $headerRow], '');
        $worksheet->mergeCells(
            Coordinate::stringFromColumnIndex(self::COL_IMAGE).$headerRow.':'
            .Coordinate::stringFromColumnIndex(self::COL_IMAGE).$subHeaderRow
        );
        $worksheet->setCellValue([self::COL_COLOR, $headerRow], 'Color');
        $worksheet->mergeCells(
            Coordinate::stringFromColumnIndex(self::COL_COLOR).$headerRow.':'
            .Coordinate::stringFromColumnIndex(self::COL_COLOR).$subHeaderRow
        );
        $worksheet->setCellValue([self::COL_COST, $headerRow], $costLabel);
        $worksheet->mergeCells(
            Coordinate::stringFromColumnIndex(self::COL_COST).$headerRow.':'
            .Coordinate::stringFromColumnIndex(self::COL_COST).$subHeaderRow
        );

        $worksheet->getStyle([self::COL_IMAGE, $headerRow, self::COL_COST, $subHeaderRow])->getFont()->setBold(true);
        $worksheet->getStyle([self::COL_IMAGE, $headerRow, self::COL_COST, $subHeaderRow])
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        $worksheet->getColumnDimensionByColumn(self::COL_IMAGE)->setWidth(6);

        $col = self::COL_COST + 1;
        foreach ($stages as $stageKey) {
            $meta = self::STAGE_META[$stageKey];
            $stageStart = $col;
            $stageSpan = count($sizes) + (count($sizes) > 1 ? 1 : 0);

            $worksheet->setCellValue([$col, $headerRow], $meta['title']);
            if ($stageSpan > 1) {
                $worksheet->mergeCells(
                    Coordinate::stringFromColumnIndex($stageStart).$headerRow.':'
                    .Coordinate::stringFromColumnIndex($stageStart + $stageSpan - 1).$headerRow
                );
            }

            foreach ($sizes as $size) {
                $worksheet->setCellValue([$col, $subHeaderRow], $size);
                $col++;
            }
            if (count($sizes) > 1) {
                $worksheet->setCellValue([$col, $subHeaderRow], 'Total');
                $col++;
            }

            $worksheet->getStyle([$stageStart, $headerRow, $col - 1, $subHeaderRow])
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FF'.$meta['color']);
            $worksheet->getStyle([$stageStart, $headerRow, $col - 1, $subHeaderRow])
                ->getFont()
                ->setBold(true);
        }

        return $subHeaderRow + 1;
    }

    /**
     * @param  array{kind: 'flat', rows: list<array<string, mixed>>}  $block
     * @param  list<string>  $stages
     * @param  array<int, float>  $cellCosts
     */
    protected function writeFlatBlock(
        Worksheet $worksheet,
        array $block,
        int $startRow,
        array $stages,
        string $costLabel,
        array $cellCosts,
    ): int {
        $headerRow = $startRow;
        $worksheet->setCellValue([self::COL_IMAGE, $headerRow], '');
        $worksheet->setCellValue([self::COL_COLOR, $headerRow], 'Color');
        $worksheet->setCellValue([self::COL_COST, $headerRow], $costLabel);
        $worksheet->getColumnDimensionByColumn(self::COL_IMAGE)->setWidth(6);
        $col = self::COL_COST + 1;
        foreach ($stages as $stageKey) {
            $meta = self::STAGE_META[$stageKey];
            $worksheet->setCellValue([$col, $headerRow], $meta['title']);
            $worksheet->getStyle([$col, $headerRow, $col, $headerRow])
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FF'.$meta['color']);
            $col++;
        }
        $worksheet->getStyle([1, $headerRow, $col - 1, $headerRow])->getFont()->setBold(true);

        $rowNum = $headerRow + 1;
        $rows = $block['rows'];
        $pendingCostMerge = null;

        foreach ($rows as $index => $blockRow) {
            if (($blockRow['_type'] ?? '') === 'section') {
                if ($pendingCostMerge !== null) {
                    $this->finalizeCostMerge($worksheet, $pendingCostMerge, $rowNum - 1);
                }

                if (! empty($blockRow['_section_divider'])) {
                    $rowNum++;
                }

                $groupCost = $this->resolveParentGroupCost($rows, $index, $cellCosts);
                $pendingCostMerge = ['start' => $rowNum, 'value' => $groupCost];

                $title = trim((string) ($blockRow['name'] ?? ''));
                $pcode = trim((string) ($blockRow['pcode'] ?? ''));
                $colorText = $title !== '' ? $title : $pcode;
                if ($pcode !== '' && strtoupper($pcode) !== strtoupper($title)) {
                    $colorText = $colorText."\n".$pcode;
                }

                $worksheet->setCellValue([self::COL_COLOR, $rowNum], $colorText);
                $worksheet->getStyle([self::COL_COLOR, $rowNum])->getFont()->setBold(true);
                $worksheet->getStyle([self::COL_COLOR, $rowNum])->getAlignment()->setWrapText(true);
                $this->embedSectionImage($worksheet, $rowNum, $blockRow);
                $rowNum++;

                continue;
            }

            if (($blockRow['_type'] ?? '') !== 'data') {
                continue;
            }

            $worksheet->setCellValue([self::COL_COLOR, $rowNum], $blockRow['color_name'] ?? '—');
            $col = self::COL_COST + 1;
            foreach ($stages as $stageKey) {
                $worksheet->setCellValue([$col, $rowNum], $blockRow[$stageKey] ?? 0);
                $col++;
            }
            $rowNum++;
        }

        if ($pendingCostMerge !== null) {
            $this->finalizeCostMerge($worksheet, $pendingCostMerge, $rowNum - 1);
        }

        $this->autoSizeColumns($worksheet, self::COL_COST + count($stages));

        return $rowNum;
    }

    /**
     * @param  list<array<string, mixed>>  $blockRows
     * @param  array<int, float>  $cellCosts
     */
    protected function resolveParentGroupCost(array $blockRows, int $sectionIndex, array $cellCosts): float
    {
        $costs = [];

        for ($i = $sectionIndex + 1; $i < count($blockRows); $i++) {
            $row = $blockRows[$i];
            if (($row['_type'] ?? '') === 'section') {
                break;
            }
            if (($row['_type'] ?? '') !== 'data') {
                continue;
            }

            foreach ($row['_meta'] ?? [] as $meta) {
                $cellId = (int) ($meta['cell_id'] ?? 0);
                if ($cellId > 0) {
                    $costs[] = round($cellCosts[$cellId] ?? 0, 2);
                }
            }
        }

        if ($costs === []) {
            return 0;
        }

        $unique = array_values(array_unique($costs));

        return $unique[0];
    }

    /**
     * @param  array{start: int, value: float}  $merge
     */
    protected function finalizeCostMerge(Worksheet $worksheet, array $merge, int $endRow): void
    {
        $startRow = $merge['start'];
        if ($endRow < $startRow) {
            return;
        }

        $costCol = self::COL_COST;
        if ($endRow > $startRow) {
            $worksheet->mergeCells(
                Coordinate::stringFromColumnIndex($costCol).$startRow.':'
                .Coordinate::stringFromColumnIndex($costCol).$endRow
            );
        }

        $worksheet->setCellValue([$costCol, $startRow], $merge['value']);
        $worksheet->getStyle([$costCol, $startRow, $costCol, $endRow])
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /**
     * @param  list<string>  $sizes
     * @param  list<string>  $stages
     */
    protected function matrixLastColumn(array $sizes, array $stages): int
    {
        $stageCols = count($stages) * (count($sizes) + (count($sizes) > 1 ? 1 : 0));

        return self::COL_COST + $stageCols;
    }

    protected function autoSizeColumns(Worksheet $worksheet, int $lastCol): void
    {
        foreach (range(1, max(1, $lastCol)) as $columnIndex) {
            if ($columnIndex === self::COL_IMAGE) {
                continue;
            }
            $worksheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }
    }

    /**
     * @param  array<string, mixed>  $sectionRow
     */
    protected function embedSectionImage(Worksheet $worksheet, int $rowNum, array $sectionRow): void
    {
        $diskPath = isset($sectionRow['image_disk_path']) ? (string) $sectionRow['image_disk_path'] : null;
        $path = $this->resolveExportImagePath(
            (string) ($sectionRow['image_url'] ?? ''),
            $diskPath !== '' ? $diskPath : null,
        );
        if ($path === null) {
            return;
        }

        $thumbnailPath = $this->buildThumbnailFile($path, self::IMAGE_WIDTH, self::IMAGE_HEIGHT);
        if ($thumbnailPath === null) {
            return;
        }

        $drawing = new Drawing;
        $drawing->setPath($thumbnailPath);
        $drawing->setWidth(self::IMAGE_WIDTH);
        $drawing->setHeight(self::IMAGE_HEIGHT);
        $drawing->setCoordinates(Coordinate::stringFromColumnIndex(self::COL_IMAGE).$rowNum);
        $drawing->setOffsetX(2);
        $drawing->setOffsetY(2);
        $drawing->setWorksheet($worksheet);

        $targetHeight = self::IMAGE_HEIGHT + 4;
        $currentHeight = $worksheet->getRowDimension($rowNum)->getRowHeight();
        if ($currentHeight < 0 || $currentHeight < $targetHeight) {
            $worksheet->getRowDimension($rowNum)->setRowHeight($targetHeight);
        }
    }

    protected function buildThumbnailFile(string $sourcePath, int $maxWidth, int $maxHeight): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return $this->passthroughEmbeddableFile($sourcePath);
        }

        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return null;
        }

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            default => null,
        };

        if ($source === false || $source === null) {
            return $this->passthroughEmbeddableFile($sourcePath);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($source);

            return null;
        }

        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight, 1.0);
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));

        if ($scale >= 1.0 && strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'jpg') {
            imagedestroy($source);

            return $this->registerTempImageFile($sourcePath);
        }

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($thumbnail === false) {
            imagedestroy($source);

            return $this->passthroughEmbeddableFile($sourcePath);
        }

        $background = imagecolorallocate($thumbnail, 255, 255, 255);
        if ($background !== false) {
            imagefill($thumbnail, 0, 0, $background);
        }

        imagecopyresampled(
            $thumbnail,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight,
        );
        imagedestroy($source);

        $tempPath = tempnam(sys_get_temp_dir(), 'restock-thumb-').'.jpg';
        if (! imagejpeg($thumbnail, $tempPath, 85)) {
            imagedestroy($thumbnail);
            @unlink($tempPath);

            return null;
        }
        imagedestroy($thumbnail);

        return $this->registerTempImageFile($tempPath);
    }

    protected function passthroughEmbeddableFile(string $sourcePath): ?string
    {
        if (! is_file($sourcePath)) {
            return null;
        }

        return $this->registerTempImageFile($sourcePath);
    }

    protected function registerTempImageFile(string $path): string
    {
        if (! in_array($path, $this->tempImageFiles, true)) {
            $this->tempImageFiles[] = $path;
        }

        return $path;
    }

    protected function cleanupHeldImages(): void
    {
        foreach ($this->tempImageFiles as $path) {
            if (str_starts_with($path, sys_get_temp_dir()) && is_file($path)) {
                @unlink($path);
            }
        }

        $this->tempImageFiles = [];
    }

    protected function resolveExportImagePath(string $imageUrl, ?string $diskPath = null): ?string
    {
        if ($diskPath !== null && is_file($diskPath)) {
            $local = $this->embeddableImagePath($diskPath);
            if ($local !== null) {
                return $local;
            }
        }

        if ($imageUrl === '' || str_contains($imageUrl, 'default-item.svg')) {
            return null;
        }

        $fromUrl = $this->imageResolver->resolveExistingDiskPathFromImageUrl($imageUrl);
        if ($fromUrl !== null) {
            $local = $this->embeddableImagePath($fromUrl);
            if ($local !== null) {
                return $local;
            }
        }

        return $this->fetchRemoteImageToTemp($imageUrl);
    }

    protected function fetchRemoteImageToTemp(string $imageUrl): ?string
    {
        foreach ($this->imageResolver->absoluteImageUrlsForFetch($imageUrl) as $absoluteUrl) {
            try {
                $response = Http::timeout(20)->get($absoluteUrl);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $body = $response->body();
            if ($body === '') {
                continue;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'restock-fetch-').'.jpg';
            if (file_put_contents($tempPath, $body) === false) {
                @unlink($tempPath);

                continue;
            }

            if (@getimagesize($tempPath) === false) {
                @unlink($tempPath);

                continue;
            }

            return $this->registerTempImageFile($tempPath);
        }

        return null;
    }

    protected function embeddableImagePath(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            return null;
        }

        return $path;
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
