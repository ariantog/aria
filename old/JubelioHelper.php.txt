<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JubelioHelper
{
    /**
     * Cek atau update data berdasarkan slug
     *
     * @param string $slug
     * @param mixed|null $newKeyValue
     * @return object
     */

    public static function jubelioAuth()
    {

        // ❌ Jika tidak aktif → langsung null
        if (!env('JUBELIO_ACTIVE', false)) {
            return null;
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post(env('JUBELIO_URL'), [
            'email' => env('JUBELIO_EMAIL'),
            'password' => env('JUBELIO_PASSWORD'),
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    public static function jubelioLogin()
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post(env('JUBELIO_URL'), [
            'email' => env('JUBELIO_EMAIL'),
            'password' => env('JUBELIO_PASSWORD'),
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['token'] ?? null;
        }

        // ambil error dari API
        $error = $response->json();

        return [
            'error' => true,
            'status' => $response->status(),
            'message' => $error['message'] ?? $response->body(),
        ];
    }

    public static function getJubelioCache()
    {
        $cacheKey = 'jubelio_data';

        return Cache::remember($cacheKey, now()->addHours(10), function () {
            $jubelioApi = self::jubelioAuth();

            if (!$jubelioApi || !isset($jubelioApi['token'])) {
                return null; // Jika gagal autentikasi, return null
            }

            return [
                'token' => $jubelioApi['token'],
            ];

            //return $jubelio; // Sesuaikan dengan query yang dibutuhkan
        });

        
    }

    public static function checkOrUpdateData(string $slug, $newKeyValue = null)
    {
        // Cek apakah data dengan slug tersebut ada
        $data = DB::table('ams')->where('name', $slug)->first();

        if (!$data) {

            $jubelioApi = self::jubelioAuth();
            // Jika data tidak ditemukan, buat baru
            $newData = [
                'name' => $slug,
                'sk' => $jubelioApi['token'] ?? 'default_value',
                'expDate' => Carbon::now()->addHours(10), // Expired dalam 10 jam
                'created_at' => now(),
                'updated_at' => now(),
            ];
            DB::table('ams')->insert($newData);
            return (object) $newData;
        }

        // Jika data ditemukan, cek tanggal expired
        if (Carbon::parse($data->expDate)->isPast()) {
            // Update jika sudah expired
            $jubelioApi = self::jubelioAuth();

            DB::table('ams')
                ->where('name', $slug)
                ->update([
                    'sk' => $jubelioApi['token'] ?? $data->key,
                    'expDate' => Carbon::now()->addHours(10),
                    'updated_at' => now(),
                ]);

            $data->key = $jubelioApi['token'] ?? $data->key;
            $data->expDate = Carbon::now()->addHours(10)->toDateTimeString();
        }

        return $data;
    }

    public function fetchInventory(int $page = 1, int $pageSize = 200): ?array
    {
        Log::info("Fetching inventory for page {$page}...");
        $token = Cache::get('jubelio_data')['token'] ?? null;

        if (! $token) {
            Log::error('Failed to get token for fetchInventory.');

            return null;
        }

        $config = config('services.jubelio');

        try {
            $request = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json']);

            if (! ($config['verify_ssl'] ?? true)) {
                $request->withoutVerifying();
            }

            $response = $request->get('https://api2.jubelio.com/inventory/', [
                'page' => $page,
                'pageSize' => 200,
                'sortBy' =>'item_id',
                'sortDirection' => 'DESC', // Kecilkan pageSize agar lebih ringan
            ]);

            Log::info("Jubelio API Response Status: {$response->status()}");

            if ($response->successful()) {
                return $response->json();
            }

            $errorBody = $response->body();
            Log::error('Jubelio fetch inventory failed.', [
                'status' => $response->status(),
                'response' => $errorBody,
                'page' => $page,
            ]);

            return [
                'error' => [
                    'message' => 'API Return HTTP '.$response->status(),
                    'raw' => $errorBody,
                ],
                'statusCode' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Jubelio fetch inventory error: '.$e->getMessage());

            return [
                'error' => [
                    'message' => 'Connection Exception',
                    'raw' => $e->getMessage(),
                ],
            ];
        }

        return null;
    }
}
