<?php

namespace App\Services\Jubelio;

use App\Models\JubelioProxyClient;

class JubelioProxyResponseSanitizer
{
    public function sanitize(mixed $data, JubelioProxyClient $client, bool $containsPii, bool $containsFinance): mixed
    {
        if (! is_array($data)) {
            return $this->scrubScalar($data, $client, $containsPii, $containsFinance);
        }

        $out = [];
        foreach ($data as $key => $value) {
            $keyStr = is_string($key) ? $key : (string) $key;

            if ($this->isSecretField($keyStr)) {
                continue;
            }

            if ($containsFinance && ! $client->hasScope('finance') && $this->matchesPattern($keyStr, config('jubelio_proxy.finance_field_patterns', []))) {
                $out[$key] = null;

                continue;
            }

            if ($containsPii && ! $client->hasScope('pii') && $this->matchesPattern($keyStr, config('jubelio_proxy.pii_field_patterns', []))) {
                $out[$key] = '[redacted]';

                continue;
            }

            $out[$key] = is_array($value)
                ? $this->sanitize($value, $client, $containsPii, $containsFinance)
                : $this->scrubScalar($value, $client, $containsPii, $containsFinance);
        }

        return $out;
    }

  /**
     * @param  list<string>  $patterns
     */
    private function matchesPattern(string $field, array $patterns): bool
    {
        $lower = strtolower($field);

        foreach ($patterns as $pattern) {
            if ($lower === strtolower($pattern) || str_contains($lower, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    private function isSecretField(string $field): bool
    {
        return $this->matchesPattern($field, config('jubelio_proxy.secret_field_patterns', []));
    }

    private function scrubScalar(mixed $value, JubelioProxyClient $client, bool $containsPii, bool $containsFinance): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (strlen($value) > 40 && preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            // Possible bearer token leaked in a string field.
            if (str_starts_with(strtolower($value), 'bearer ') || str_contains(strtolower($value), 'eyj')) {
                return '[redacted]';
            }
        }

        return $value;
    }

  /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function redactParams(array $params): array
    {
        $redacted = [];
        foreach ($params as $key => $value) {
            $keyStr = (string) $key;
            if ($this->isSecretField($keyStr) || $this->matchesPattern($keyStr, config('jubelio_proxy.pii_field_patterns', []))) {
                $redacted[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactParams($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }
}