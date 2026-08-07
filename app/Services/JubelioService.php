<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
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
            Log::info('No jubelio_token setting found. Authenticating...');
            $authData = $this->authenticate();

            if (! $authData || ! isset($authData['token'])) {
                Log::error('Authentication failed during getToken.');

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
            Log::info('Jubelio token expired or invalid. Re-authenticating...');
            $authData = $this->authenticate();

            if (! $authData || ! isset($authData['token'])) {
                Log::error('Re-authentication failed during getToken.', ['old_token_exists' => isset($value['token'])]);

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

    /**
     * Build an authenticated HTTP client for Jubelio API calls.
     * Forces the service active so UI/CLI callers do not depend on JUBELIO_ACTIVE alone.
     */
    public function authenticatedRequest(): ?PendingRequest
    {
        config(['services.jubelio.active' => true]);

        $token = $this->getToken();
        if (! $token) {
            return null;
        }

        $request = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json']);

        if (! ($this->config()['verify_ssl'] ?? true)) {
            $request->withoutVerifying();
        }

        return $request;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function config(): array
    {
        return config('services.jubelio', []);
    }

    /**
     * Fetch inventory from Jubelio API.
     */
    public function fetchInventory(int $page = 1, int $pageSize = 200): ?array
    {
        Log::info("Fetching inventory for page {$page}...");
        $request = $this->authenticatedRequest();

        if (! $request) {
            Log::error('Failed to get token for fetchInventory.');

            return null;
        }

        try {
            $response = $request->get('https://api2.jubelio.com/inventory/', [
                'page' => $page,
                'pageSize' => $pageSize,
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

    /**
     * @return array<string, mixed>|null
     */
    public function fetchSalesOrder(int|string $orderId): ?array
    {
        $request = $this->authenticatedRequest();
        if (! $request) {
            return null;
        }

        try {
            $response = $request->get('https://api2.jubelio.com/sales/orders/'.$orderId);

            if ($response->successful()) {
                $data = $response->json();

                return is_array($data) ? $data : null;
            }

            Log::error('Jubelio fetch sales order failed.', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Jubelio fetch sales order error: '.$e->getMessage(), ['order_id' => $orderId]);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchSalesReturn(int|string $returnId): ?array
    {
        $request = $this->authenticatedRequest();
        if (! $request) {
            return null;
        }

        try {
            $response = $request->get('https://api2.jubelio.com/sales/sales-returns/'.$returnId);

            if ($response->successful()) {
                $data = $response->json();

                return is_array($data) ? $data : null;
            }

            Log::error('Jubelio fetch sales return failed.', [
                'return_id' => $returnId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Jubelio fetch sales return error: '.$e->getMessage(), ['return_id' => $returnId]);
        }

        return null;
    }
}
