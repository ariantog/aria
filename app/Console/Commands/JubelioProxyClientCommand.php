<?php

namespace App\Console\Commands;

use App\Models\JubelioProxyClient;
use App\Services\Jubelio\JubelioProxyService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class JubelioProxyClientCommand extends Command
{
    protected $signature = 'jubelio-proxy:client
                            {action : create|revoke|list}
                            {name? : Client name (e.g. channels-bot)}
                            {--apis=* : Allowed API names (repeatable; default = all read APIs)}
                            {--scopes=* : Optional scopes: pii, finance}
                            {--rate-limit= : Requests per minute (default from config)}
                            {--write-enabled : Allow write APIs when globally enabled (discouraged)}';

    protected $description = 'Create, list, or revoke Jubelio proxy bot bearer tokens';

    public function handle(): int
    {
        $action = Str::lower((string) $this->argument('action'));

        return match ($action) {
            'create' => $this->createClient(),
            'revoke' => $this->revokeClient(),
            'list' => $this->listClients(),
            default => $this->invalidAction($action),
        };
    }

    private function createClient(): int
    {
        $name = (string) $this->argument('name');
        if ($name === '') {
            $this->error('Name is required for create.');

            return self::FAILURE;
        }

        if (JubelioProxyClient::query()->where('name', $name)->exists()) {
            $this->error("Client {$name} already exists.");

            return self::FAILURE;
        }

        $apis = $this->option('apis');
        if ($apis === [] || $apis === null) {
            $apis = JubelioProxyService::readApiNames();
        }

        [$plain, $hash, $prefix] = JubelioProxyClient::generateCredentials();

        $client = JubelioProxyClient::query()->create([
            'name' => $name,
            'token_prefix' => $prefix,
            'token_hash' => $hash,
            'allowed_apis' => array_values($apis),
            'scopes' => array_values($this->option('scopes') ?? []),
            'write_enabled' => (bool) $this->option('write-enabled'),
            'rate_limit_per_minute' => $this->option('rate-limit') !== null
                ? (int) $this->option('rate-limit')
                : null,
            'active' => true,
        ]);

        $this->info("Created client #{$client->id} ({$client->name}).");
        $this->line('Bearer token (shown once):');
        $this->line($plain);
        $this->newLine();
        $this->line('Allowed APIs: '.implode(', ', $client->allowed_apis ?? []));

        return self::SUCCESS;
    }

    private function revokeClient(): int
    {
        $name = (string) $this->argument('name');
        if ($name === '') {
            $this->error('Name is required for revoke.');

            return self::FAILURE;
        }

        $client = JubelioProxyClient::query()->where('name', $name)->first();
        if ($client === null) {
            $this->error("Client {$name} not found.");

            return self::FAILURE;
        }

        $client->update([
            'active' => false,
            'revoked_at' => now(),
        ]);

        $this->info("Revoked client {$name}.");

        return self::SUCCESS;
    }

    private function listClients(): int
    {
        $rows = JubelioProxyClient::query()
            ->orderBy('name')
            ->get(['id', 'name', 'token_prefix', 'allowed_apis', 'scopes', 'write_enabled', 'rate_limit_per_minute', 'active', 'revoked_at', 'last_used_at']);

        if ($rows->isEmpty()) {
            $this->line('No Jubelio proxy clients.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Prefix', 'Active', 'Write', 'Rate/min', 'APIs', 'Scopes', 'Last used'],
            $rows->map(fn (JubelioProxyClient $c) => [
                $c->id,
                $c->name,
                $c->token_prefix,
                $c->active && $c->revoked_at === null ? 'yes' : 'no',
                $c->write_enabled ? 'yes' : 'no',
                $c->effectiveRateLimit(),
                count($c->allowed_apis ?? []),
                implode(',', $c->scopes ?? []),
                $c->last_used_at?->toDateTimeString() ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function invalidAction(string $action): int
    {
        $this->error("Unknown action {$action}. Use create, revoke, or list.");

        return self::FAILURE;
    }
}