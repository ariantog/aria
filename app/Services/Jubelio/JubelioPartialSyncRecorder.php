<?php

namespace App\Services\Jubelio;

use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Records lines skipped on partial Jubelio stock push (unlinked SKUs) on the transaction description.
 */
class JubelioPartialSyncRecorder
{
    public const NOTE_MARKER = '[Jubelio — baris tidak disinkronkan]';

    /**
     * @return list<array{code: string, qty: float, item_id: int}>
     */
    public function skippedLines(Transaction $transaction): array
    {
        $transaction->loadMissing('details.item');

        $skipped = [];
        foreach ($transaction->details as $row) {
            if ((float) $row->quantity === 0.0) {
                continue;
            }
            if ($row->item?->jubelio_item_id && (int) $row->item->jubelio_item_id > 0) {
                continue;
            }

            $code = trim((string) ($row->item?->code ?? ''));
            if ($code === '') {
                $code = 'item #'.(int) $row->item_id;
            }

            $skipped[] = [
                'code' => $code,
                'qty' => (float) $row->quantity,
                'item_id' => (int) $row->item_id,
            ];
        }

        return $skipped;
    }

    /**
     * @param  list<array{code: string, qty: float, item_id: int}>  $skipped
     */
    public function appendSkipNoteToDescription(
        Transaction $transaction,
        int $side,
        string $warehouseLabel,
        array $skipped,
        int $syncedLineCount,
        ?string $jubelioReferenceId,
    ): void {
        if ($skipped === []) {
            return;
        }

        $block = $this->formatNoteBlock($side, $warehouseLabel, $skipped, $syncedLineCount, $jubelioReferenceId);
        $existing = trim((string) ($transaction->description ?? ''));

        if ($existing !== '' && str_contains($existing, self::NOTE_MARKER)) {
            $existing = $this->stripPreviousSkipNotes($existing);
        }

        $merged = $existing === ''
            ? $block
            : rtrim($existing)."\n\n".$block;

        $transaction->update(['description' => $merged]);
    }

    /**
     * @param  list<array{code: string, qty: float, item_id: int}>  $skipped
     */
    public function formatNoteBlock(
        int $side,
        string $warehouseLabel,
        array $skipped,
        int $syncedLineCount,
        ?string $jubelioReferenceId,
    ): string {
        $sideLabel = JubelioStockSync::isSenderSide($side) ? 'Side A (pengirim)' : 'Side B (penerima)';
        $timestamp = now()->format('Y-m-d H:i');

        $lines = [
            '---',
            self::NOTE_MARKER,
            'Push Jubelio — '.$sideLabel.' — '.$timestamp,
            'Gudang: '.$warehouseLabel,
        ];

        if ($jubelioReferenceId !== null && $jubelioReferenceId !== '') {
            $lines[] = 'Referensi Jubelio: '.$jubelioReferenceId;
        }

        $lines[] = 'Disinkronkan: '.$syncedLineCount.' baris terhubung.';
        $lines[] = 'Dilewati (belum terhubung ke Jubelio):';

        foreach ($skipped as $row) {
            $qty = $this->formatQty($row['qty']);
            $lines[] = '  • '.$row['code'].' × '.$qty;
        }

        return implode("\n", $lines);
    }

    private function stripPreviousSkipNotes(string $description): string
    {
        $parts = preg_split('/\n---\n/', $description) ?: [$description];
        $kept = [];

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '') {
                continue;
            }
            if (str_contains($trimmed, self::NOTE_MARKER)) {
                continue;
            }
            $kept[] = $trimmed;
        }

        return trim(implode("\n\n", $kept));
    }

    private function formatQty(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.00001) {
            return (string) (int) round($qty);
        }

        return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
    }
}
