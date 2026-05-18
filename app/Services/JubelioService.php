<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JubelioService
{
    /**
     * Authenticate with Jubelio API.
     */
    public function authenticate(): ?array
    {
        $config = config('services.jubelio');

        if (! ($config['active'] ?? false)) {
            Log::warning('Jubelio authentication attempted but service is not active.');

            return null;
        }

        try {
            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
            ]);

            if (! ($config['verify_ssl'] ?? true)) {
                $request->withoutVerifying();
            }

            $response = $request->post($config['url'], [
                'email' => $config['email'],
                'password' => $config['password'],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Jubelio authentication failed.', [
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Jubelio authentication error: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Get Jubelio token from cache or authenticate.
     */
    public function getCachedToken(): ?string
    {
        return Cache::remember('jubelio_token', now()->addHours(10), function () {
            $authData = $this->authenticate();

            return $authData['token'] ?? null;
        });
    }

    /**
     * Check or update token using Setting model (persistent storage).
     */
    public function getToken(string $slug = 'jubelio_token'): ?string
    {
        $setting = Setting::where('slug', $slug)->first();

        if (! $setting) {
            $authData = $this->authenticate();

            if (! $authData || ! isset($authData['token'])) {
                return null;
            }

            $setting = Setting::create([
                'group' => 'Jubelio',
                'name' => 'Jubelio Token',
                'slug' => $slug,
                'value' => [
                    'token' => $authData['token'],
                    'expires_at' => Carbon::now()->addHours(10)->toDateTimeString(),
                ],
            ]);

            return $authData['token'];
        }

        $value = $setting->value;
        $expiresAt = isset($value['expires_at']) ? Carbon::parse($value['expires_at']) : null;

        if (! $expiresAt || $expiresAt->isPast()) {
            $authData = $this->authenticate();

            if (! $authData || ! isset($authData['token'])) {
                // Return old token if re-auth fails, or null?
                // Helper returned $data->key (old token).
                return $value['token'] ?? null;
            }

            $newToken = $authData['token'];
            $newExpiresAt = Carbon::now()->addHours(10)->toDateTimeString();

            $setting->update([
                'value' => [
                    'token' => $newToken,
                    'expires_at' => $newExpiresAt,
                ],
            ]);

            return $newToken;
        }

        return $value['token'] ?? null;
    }
}
