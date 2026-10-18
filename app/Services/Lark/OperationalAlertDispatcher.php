<?php

namespace App\Services\Lark;

use App\Models\OperationalAlert;
use Illuminate\Support\Carbon;

class OperationalAlertDispatcher
{
    public function __construct(
        protected LarkWebhookClient $webhook,
        protected LarkCardBuilder $cards,
        protected LarkRosterService $roster,
    ) {}

    /**
     * Record fingerprint and send Lark when newly created or rearmed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(
        string $eventKey,
        string $fingerprint,
        string $severity,
        string $title,
        array $payload,
        bool $forceResend = false,
    ): ?OperationalAlert {
        $alert = OperationalAlert::query()->firstOrNew(['fingerprint' => $fingerprint]);
        $now = now();

        if ($alert->exists && ! $forceResend) {
            if ($alert->status === 'open' && $alert->sent_at !== null) {
                $alert->last_seen_at = $now;
                $alert->payload = $payload;
                $alert->save();

                return $alert;
            }
        }

        if (! $alert->exists) {
            $alert->event_key = $eventKey;
            $alert->severity = $severity;
            $alert->first_seen_at = $now;
            $alert->status = 'open';
        }

        $alert->event_key = $eventKey;
        $alert->severity = $severity;
        $alert->last_seen_at = $now;
        $alert->payload = $payload;
        $alert->save();

        $eventConfig = config('lark.events.'.$eventKey, ['mention' => 'none', 'roles' => []]);
        $mention = null;
        if (($eventConfig['mention'] ?? 'none') === 'roster') {
            $mention = $this->roster->mentionLineForRoles($eventConfig['roles'] ?? []);
        }

        $template = match ($severity) {
            'critical' => 'red',
            'warning' => 'orange',
            default => 'blue',
        };

        $fields = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $fields[(string) $key] = (string) $value;
            }
        }

        $card = $this->cards->simpleAlert($title, $template, $fields, $mention);
        if ($this->webhook->sendInteractiveCard($card, $eventKey, $fingerprint)) {
            $alert->sent_at = $now;
            $alert->save();
        }

        return $alert;
    }

    public function rearmWhenAbove(string $fingerprint, float $coverDays, float $threshold, float $buffer): void
    {
        $alert = OperationalAlert::query()->where('fingerprint', $fingerprint)->first();
        if ($alert === null || $alert->status !== 'open') {
            return;
        }

        if ($coverDays >= ($threshold + $buffer)) {
            $alert->status = 'rearmed';
            $alert->rearmed_at = Carbon::now();
            $alert->save();
        }
    }
}
