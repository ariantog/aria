<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JubelioProxyClient extends Model
{
    protected $fillable = [
        'name',
        'token_prefix',
        'token_hash',
        'allowed_apis',
        'scopes',
        'write_enabled',
        'rate_limit_per_minute',
        'active',
        'revoked_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'allowed_apis' => 'array',
            'scopes' => 'array',
            'write_enabled' => 'boolean',
            'active' => 'boolean',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function requestLogs(): HasMany
    {
        return $this->hasMany(JubelioProxyRequestLog::class, 'client_id');
    }

  /**
     * @return array{0: string, 1: string, 2: string} plain token, hash, prefix
     */
    public static function generateCredentials(): array
    {
        $plain = 'jbp_'.Str::lower(Str::random(48));

        return [$plain, self::hashToken($plain), Str::substr($plain, 0, 12)];
    }

    public static function hashToken(string $plain): string
    {
        return hash_hmac('sha256', $plain, (string) config('app.key'));
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        return in_array($scope, $scopes, true);
    }

    public function allowsApi(string $apiName): bool
    {
        $allowed = $this->allowed_apis ?? [];

        return in_array($apiName, $allowed, true);
    }

    public function effectiveRateLimit(): int
    {
        return (int) ($this->rate_limit_per_minute
            ?: config('jubelio_proxy.default_rate_limit_per_minute', 60));
    }

    public function isUsable(): bool
    {
        return $this->active && $this->revoked_at === null;
    }
}