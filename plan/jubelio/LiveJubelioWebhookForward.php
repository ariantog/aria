<?php

/**
 * Copy-paste helper for the LIVE (Laravel 10) JubelioController.
 *
 * See plan/jubelio/LIVE_WEBHOOK_FORWARD.md for full setup.
 *
 * 1. Add `use Illuminate\Support\Facades\Http;` if not present.
 * 2. Add this method to JubelioController.
 * 3. Wrap order() body (after signature check) in try/finally and call forward in finally.
 */
trait LiveJubelioWebhookForward
{
    private function forwardJubelioWebhookToDev(\Illuminate\Http\Request $request, string $rawBody, ?string $signHeader): void
    {
        $baseUrl = env('JUBELIO_DEV_WEBHOOK_URL');
        if (! $baseUrl) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Sign' => $signHeader,
                    'X-Jubelio-Forwarded-From' => 'production',
                ])
                ->withBody($rawBody, 'application/json')
                ->post(rtrim($baseUrl, '/').'/jubelio/webhook/order');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Jubelio dev webhook forward failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
