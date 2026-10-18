<?php

namespace App\Services\Lark;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LarkWebhookClient
{
    public function sendInteractiveCard(array $card, ?string $eventKey = null, ?string $fingerprint = null): bool
    {
        if (! config('lark.enabled')) {
            return false;
        }

        $payload = [
            'msg_type' => 'interactive',
            'card' => $card,
        ];

        if ($secret = config('lark.bot_secret')) {
            $timestamp = (string) time();
            $sign = base64_encode(hash_hmac('sha256', '', $timestamp."\n".$secret, true));
            $payload['timestamp'] = $timestamp;
            $payload['sign'] = $sign;
        }

        $hash = hash('sha256', json_encode($payload));

        if (config('lark.dry_run')) {
            Log::info('Lark dry-run card', [
                'event_key' => $eventKey,
                'fingerprint' => $fingerprint,
                'payload_hash' => $hash,
            ]);

            NotificationLog::query()->create([
                'channel' => 'lark',
                'event_key' => $eventKey,
                'fingerprint' => $fingerprint,
                'payload_hash' => $hash,
                'status' => 'dry_run',
            ]);

            return true;
        }

        $url = config('lark.bot_webhook_url');
        if (! is_string($url) || $url === '') {
            Log::warning('Lark webhook URL missing');

            return false;
        }

        try {
            $response = Http::timeout(15)->post($url, $payload);
            if (! $response->successful()) {
                NotificationLog::query()->create([
                    'channel' => 'lark',
                    'event_key' => $eventKey,
                    'fingerprint' => $fingerprint,
                    'payload_hash' => $hash,
                    'status' => 'failed',
                    'error' => $response->body(),
                ]);

                return false;
            }

            NotificationLog::query()->create([
                'channel' => 'lark',
                'event_key' => $eventKey,
                'fingerprint' => $fingerprint,
                'payload_hash' => $hash,
                'status' => 'sent',
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Lark webhook failed', ['message' => $e->getMessage()]);
            NotificationLog::query()->create([
                'channel' => 'lark',
                'event_key' => $eventKey,
                'fingerprint' => $fingerprint,
                'payload_hash' => $hash,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
