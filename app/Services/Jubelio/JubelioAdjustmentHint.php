<?php

namespace App\Services\Jubelio;

/**
 * Maps a Jubelio stock-adjust error to a short next step.
 */
class JubelioAdjustmentHint
{
    public static function for(string $message): string
    {
        $haystack = mb_strtolower($message);

        foreach (self::rules() as $rule) {
            foreach ($rule['needles'] as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $rule['hint'];
                }
            }
        }

        return 'Perbaiki data di Aria atau Jubelio sesuai pesan di atas, lalu push ulang. Jangan tandai berhasil tanpa nomor penyesuaian di Jubelio.';
    }

    /**
     * @return list<array{needles: list<string>, hint: string}>
     */
    private static function rules(): array
    {
        return [
            [
                'needles' => ['qty exceeds', 'available stock', 'insufficient stock', 'stok tidak', 'on hand', 'not enough stock'],
                'hint' => 'Stok di lokasi Jubelio tidak cukup untuk pengurangan ini. Cek stok gudang pengirim di Jubelio, kurangi qty, atau sesuaikan stok di sana dulu — lalu push ulang.',
            ],
            [
                'needles' => ['item not found', 'invalid item', 'unknown item', 'item_id', 'no linked jubelio items'],
                'hint' => 'Item belum terhubung ke Jubelio, atau ID-nya salah. Buka Item → tab Jubelio, tautkan jubelio_item_id yang benar, lalu push ulang.',
            ],
            [
                'needles' => ['auth failed', 'unauthorized', 'unauthorised', 'token', 'forbidden', 'invalid credentials'],
                'hint' => 'Token Jubelio gagal. Buka Jubelio → Koneksi, refresh token, lalu push ulang.',
            ],
            [
                'needles' => ['mapping not found', 'location not found', 'invalid location', 'not linked'],
                'hint' => 'Gudang Aria belum dipetakan ke lokasi Jubelio. Isi mapping di Jubelio Sync untuk gudang ini, lalu push ulang.',
            ],
            [
                'needles' => ['pending sync warning', 'confirm or clear', 'already synced', 'already attempted'],
                'hint' => 'Sisi ini masih berstatus peringatan atau sudah tersinkron. Hapus peringatan untuk coba lagi, atau konfirmasi hanya jika nomor penyesuaian ada di Jubelio.',
            ],
            [
                'needles' => ['tidak ada respons', 'tidak ada reference id', 'status tidak jelas', 'jangan tandai berhasil'],
                'hint' => 'Cek Inventory → Penyesuaian Stok di Jubelio. Jika dokumennya ada, konfirmasi dengan nomor itu. Jika tidak ada, hapus peringatan lalu push ulang.',
            ],
            [
                'needles' => ['daftar penyesuaian', 'listing'],
                'hint' => 'Jubelio tidak membuat dokumen baru. Jangan tandai berhasil. Push ulang; jika berulang, periksa mapping lokasi/bin di Jubelio Sync.',
            ],
            [
                'needles' => ['bin', 'account_id', 'account id', 'validation error'],
                'hint' => 'Payload penyesuaian ditolak Jubelio. Periksa lokasi, bin, dan akun di Jubelio Sync untuk gudang ini, lalu push ulang.',
            ],
            [
                'needles' => ['api error: 5', '502', '503', '504', 'http 5'],
                'hint' => 'Jubelio sedang tidak merespons. Tunggu sebentar, lalu push ulang. Jangan tandai berhasil.',
            ],
        ];
    }
}
