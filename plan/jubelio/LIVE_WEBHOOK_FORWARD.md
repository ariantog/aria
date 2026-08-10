# Live → Dev Jubelio webhook forward

Use this while Jubelio still points at **production**. Production keeps handling live traffic; each valid webhook is replayed to the Laravel 12 dev/staging app so you can test queue + processing in parallel.

## Flow

```
Jubelio → production order() → save/process live DB
                ↓ (replay same raw body + Sign header)
         dev POST /jubelio/webhook/order → jubelioorders row (pending)
                ↓ (cron every minute)
         jubelio:order-jubelio-to-aria → ProcessJubelioOrder
```

## 1. Production `.env`

```env
# Base URL of the dev/staging Aria app (no trailing slash)
JUBELIO_DEV_WEBHOOK_URL=https://dev.example.com
```

Use the same `JUBELIO_WEBHOOK_SECRET` / Jubelio `Sign` secret on both environments so the forwarded payload passes signature validation.

## 2. Patch production `JubelioController::order()`

After signature validation succeeds, wrap existing logic in `try/finally` and forward in `finally` so every handled webhook is replayed (including duplicates / early exits).

Add this private method to `JubelioController`:

```php
private function forwardJubelioWebhookToDev(Request $request, string $rawBody, ?string $signHeader): void
{
    $baseUrl = env('JUBELIO_DEV_WEBHOOK_URL');
    if (! $baseUrl) {
        return;
    }

    try {
        Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Sign' => $signHeader,
                'X-Jubelio-Forwarded-From' => 'production',
            ])
            ->withBody($rawBody, 'application/json')
            ->post(rtrim($baseUrl, '/').'/jubelio/webhook/order');
    } catch (\Throwable $e) {
        Log::warning('Jubelio dev webhook forward failed', [
            'message' => $e->getMessage(),
        ]);
    }
}
```

Change the start of `order()` to capture raw body once:

```php
public function order(Request $request)
{
    $secret = 'corenation2025';
    $content = trim($request->getContent());
    $sign = hash_hmac('sha256', $content . $secret, $secret, false);
    $signature = $request->header('Sign');

    if ($signature !== $sign) {
        return response()->json(['error' => 'Invalid signature'], 403);
    }

    try {
        $dataApi = $request->all();
        // ... existing SHIPPED / CANCELED logic unchanged ...
        // keep all existing return response()->json(...) inside this try block
    } finally {
        $this->forwardJubelioWebhookToDev($request, $content, $signature);
    }
}
```

**Important:** move every `return response()->json(...)` that is currently *after* signature validation inside the `try` block. Do not forward invalid signatures.

Optional: apply the same pattern to `retur()` → dev `POST /jubelio/webhook/return`.

## 3. Development `.env`

```env
JUBELIO_WEBHOOK_SECRET=corenation2025   # same as production / Jubelio
```

Ensure cron is active (system `schedule:run` every minute + **Sync Jubelio Orders** enabled in `/cron-manager`):

```bash
# One-off manual run:
php artisan jubelio:order-jubelio-to-aria
```

## 4. Verify

1. Trigger a real Jubelio SHIPPED webhook (or replay from production logs).
2. Production: order appears in live `jubelioorders` as today.
3. Dev: same invoice appears in `/jubelio` (pending → processed if stock OK).
4. Check dev logs for `V2 - Proses order Jubelio` or run `php artisan jubelio:order-jubelio-to-aria` manually.

Manual replay from production (debug):

```bash
curl -X POST "https://dev.example.com/jubelio/webhook/order" \
  -H "Content-Type: application/json" \
  -H "Sign: <hmac from raw body>" \
  -H "X-Jubelio-Forwarded-From: manual" \
  --data-binary @payload.json
```
