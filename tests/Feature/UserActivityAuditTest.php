<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BookClosingService;
use App\Services\UserActivityAuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 12:00:00');

    Permission::firstOrCreate(['name' => 'setting-general-view', 'guard_name' => 'web']);

    $this->staff = User::factory()->create();
    $this->staff->givePermissionTo('setting-general-view');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders the user activity audit page for authorized users', function () {
    $this->actingAs($this->staff)
        ->get(route('user-activity-audit.index'))
        ->assertSuccessful()
        ->assertSee('User Activity Audit', false)
        ->assertSee('data-testid="user-activity-audit-filters"', false);
});

it('forbids users without setting-general-view', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('user-activity-audit.index'))
        ->assertForbidden();
});

it('flags transactions dated before the book-closing window', function () {
    $min = app(BookClosingService::class)->getMinAllowedDate()->toDateString();

    $txn = Transaction::factory()->create([
        'date' => Carbon::parse($min)->subDay()->toDateString(),
        'type' => Transaction::TYPE_SELL,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'user_id' => $this->staff->id,
        'created_at' => now(),
    ]);

    $filters = app(UserActivityAuditService::class)->resolveFilters([
        'from' => Carbon::parse($min)->subMonths(2)->toDateString(),
        'to' => now()->toDateString(),
    ]);

    $ids = app(UserActivityAuditService::class)
        ->suspiciousTimingQuery($filters)
        ->pluck('id');

    expect($ids)->toContain($txn->id);
});

it('flags transactions created long after their transaction date', function () {
    $txnDate = now()->subDays(20)->toDateString();

    $txn = Transaction::factory()->create([
        'date' => $txnDate,
        'type' => Transaction::TYPE_BUY,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'user_id' => $this->staff->id,
        'created_at' => now(),
    ]);

    $filters = app(UserActivityAuditService::class)->resolveFilters([
        'from' => now()->subDays(30)->toDateString(),
        'to' => now()->toDateString(),
        'late_entry_days' => 7,
    ]);

    $flags = app(UserActivityAuditService::class)->timingFlags($txn, $filters);

    expect($flags)->toContain('Entri 7+ hari setelah tanggal transaksi');
});

it('ranks users who frequently move stock into virtual warehouses', function () {
    $virtual = Addrbook::factory()->create(['type' => Addrbook::TYPE_V_WAREHOUSE]);
    $physical = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);

    foreach (range(1, 3) as $i) {
        Transaction::factory()->create([
            'date' => now()->toDateString(),
            'type' => Transaction::TYPE_MOVE,
            'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
            'user_id' => $this->staff->id,
            'sender_id' => $physical->id,
            'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
            'receiver_id' => $virtual->id,
            'receiver_type' => (string) Addrbook::TYPE_V_WAREHOUSE,
            'invoice' => 'MOVE-AUDIT-'.$i,
        ]);
    }

    $filters = app(UserActivityAuditService::class)->resolveFilters([
        'frequent_min_count' => 3,
    ]);

    $rows = app(UserActivityAuditService::class)->frequentVirtualWarehouseMoves($filters);

    expect($rows)->not->toBeEmpty()
        ->and($rows[0]['user_id'])->toBe($this->staff->id)
        ->and($rows[0]['move_count'])->toBe(3);
});

it('runs the artisan audit command', function () {
    Artisan::call('app:user-activity-audit', [
        '--from' => now()->subDays(7)->toDateString(),
        '--to' => now()->toDateString(),
    ]);

    expect(Artisan::output())->toContain('Period');
});
