<?php

namespace App\Services\Jubelio;

/**
 * Classifies a Jubelio warehouse-adjustment POST response.
 *
 * Jubelio does not always return HTTP 4xx on business errors, and a created
 * document uses `item_adj_id` (not `id`). Treating every HTTP 2xx as success
 * — or every 2xx without `id` as "confirm it worked" — marks Aria synced
 * when nothing was created.
 */
class JubelioAdjustmentResponse
{
    public const OUTCOME_CREATED = 'created';

    public const OUTCOME_FAILED = 'failed';

    public const OUTCOME_AMBIGUOUS = 'ambiguous';

    public function __construct(
        public readonly string $outcome,
        public readonly ?string $referenceId = null,
        public readonly ?string $message = null,
        public readonly mixed $raw = null,
        public readonly ?int $httpStatus = null,
    ) {}

    public function created(): bool
    {
        return $this->outcome === self::OUTCOME_CREATED && $this->referenceId !== null;
    }

    public function failed(): bool
    {
        return $this->outcome === self::OUTCOME_FAILED;
    }

    public function ambiguous(): bool
    {
        return $this->outcome === self::OUTCOME_AMBIGUOUS;
    }

    public static function fromHttp(?int $status, mixed $json, ?string $body = null): self
    {
        if ($status === null) {
            return new self(
                self::OUTCOME_AMBIGUOUS,
                message: 'Jubelio tidak mengembalikan respons.',
                raw: $json ?? $body,
                httpStatus: null,
            );
        }

        $referenceId = self::extractReferenceId($json);
        if ($referenceId !== null) {
            return new self(
                self::OUTCOME_CREATED,
                referenceId: $referenceId,
                raw: $json,
                httpStatus: $status,
            );
        }

        $error = self::extractErrorMessage($json, $status);
        $isHttpSuccess = $status >= 200 && $status < 300;

        if ($error !== null) {
            return new self(
                self::OUTCOME_FAILED,
                message: $error,
                raw: $json,
                httpStatus: $status,
            );
        }

        if (! $isHttpSuccess) {
            return new self(
                self::OUTCOME_FAILED,
                message: 'API Error: '.$status,
                raw: $json ?? $body,
                httpStatus: $status,
            );
        }

        if (self::looksLikeListing($json)) {
            return new self(
                self::OUTCOME_FAILED,
                message: 'Jubelio mengembalikan daftar penyesuaian, bukan dokumen baru. Transaksi tidak dibuat.',
                raw: $json,
                httpStatus: $status,
            );
        }

        return new self(
            self::OUTCOME_AMBIGUOUS,
            message: 'Respons API Jubelio tidak jelas (tidak ada reference ID). Jangan tandai berhasil sebelum ada nomor penyesuaian di Jubelio.',
            raw: $json ?? $body,
            httpStatus: $status,
        );
    }

    public static function extractReferenceId(mixed $result): ?string
    {
        if (is_int($result) || is_float($result)) {
            $id = (int) $result;

            return $id > 0 ? (string) $id : null;
        }

        if (is_string($result)) {
            $trimmed = trim($result);
            if (is_numeric($trimmed) && (int) $trimmed > 0) {
                return (string) (int) $trimmed;
            }

            return null;
        }

        if (! is_array($result)) {
            return null;
        }

        foreach (['item_adj_id', 'itemAdjId', 'id'] as $key) {
            if (! array_key_exists($key, $result)) {
                continue;
            }

            $value = $result[$key];
            if (is_int($value) || is_float($value)) {
                $id = (int) $value;
                if ($id > 0) {
                    return (string) $id;
                }

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '' || in_array(strtolower($trimmed), ['null', '0', 'false'], true)) {
                continue;
            }

            if (preg_match('/^(ok|success|created|true|berhasil)$/i', $trimmed)) {
                continue;
            }

            if (is_numeric($trimmed)) {
                $id = (int) $trimmed;

                return $id > 0 ? (string) $id : null;
            }

            return $trimmed;
        }

        $data = $result['data'] ?? null;
        if (is_array($data) && $data !== [] && ! array_is_list($data)) {
            return self::extractReferenceId($data);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|mixed  $result
     */
    public static function extractErrorMessage(mixed $result, int $status): ?string
    {
        if (! is_array($result)) {
            return $status >= 400 ? 'API Error: '.$status : null;
        }

        $statusField = strtolower((string) ($result['status'] ?? ''));
        if (in_array($statusField, ['error', 'failed', 'fail'], true)) {
            return self::stringifyMessage($result['message'] ?? $result['error'] ?? null)
                ?? 'Jubelio returned status '.$statusField;
        }

        if (isset($result['error'])) {
            $error = self::stringifyMessage($result['error']);
            if ($error !== null) {
                return $error;
            }
        }

        if ($status >= 400) {
            return self::stringifyMessage($result['message'] ?? null) ?? 'API Error: '.$status;
        }

        $message = self::stringifyMessage($result['message'] ?? null);
        if ($message !== null && ! preg_match('/^(ok|success|created|berhasil)\b/i', $message)) {
            return $message;
        }

        return null;
    }

    public static function looksLikeListing(mixed $result): bool
    {
        return is_array($result)
            && array_key_exists('totalCount', $result)
            && array_key_exists('data', $result)
            && is_array($result['data']);
    }

    private static function stringifyMessage(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed !== '' ? $trimmed : null;
        }

        if (is_array($value)) {
            $parts = array_filter(array_map(
                fn ($part) => is_scalar($part) ? trim((string) $part) : '',
                $value,
            ));
            $joined = implode(' ', $parts);

            return $joined !== '' ? $joined : null;
        }

        return null;
    }
}
