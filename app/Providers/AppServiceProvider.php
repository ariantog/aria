<?php

namespace App\Providers;

use App\Models\Addrbook;
use App\Models\User;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use App\Support\PermissionTableConfig;
use App\View\Composers\AppComposer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        PermissionTableConfig::apply();

        $this->configureDefaults();

        Transaction::observe(TransactionObserver::class);

        Relation::morphMap([
            (string) Addrbook::TYPE_CUSTOMER => Addrbook::class,
            (string) Addrbook::TYPE_WAREHOUSE => Addrbook::class,
            (string) Addrbook::TYPE_BANK => Addrbook::class,
            (string) Addrbook::TYPE_SUPPLIER => Addrbook::class,
            (string) Addrbook::TYPE_V_WAREHOUSE => Addrbook::class,
            (string) Addrbook::TYPE_V_ACCOUNT => Addrbook::class,
            (string) Addrbook::TYPE_RESELLER => Addrbook::class,
            (string) Addrbook::TYPE_ACCOUNT => Addrbook::class,
            (string) Addrbook::TYPE_OTHER => Addrbook::class,

            'App\Models\Addrbook' => Addrbook::class,
            'AppModelsAddrbook' => Addrbook::class,
        ]);

        Gate::before(function ($user, $ability) {
            if ($user instanceof User && User::isSuperadmin($user)) {
                return true;
            }

            return null;
        });

        // Share sidebar data (user, permissions, roles, addrbook types, flash) with the Blade layout.
        View::composer('layouts.app', AppComposer::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
