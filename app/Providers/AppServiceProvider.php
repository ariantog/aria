<?php

namespace App\Providers;

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
        ]);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
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
