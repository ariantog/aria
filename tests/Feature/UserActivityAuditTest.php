<?php

use App\Models\Addrbook;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserActivityAudit;
use App\Services\UserActivityAuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Carbon::setTestNow('2026-10-15 12:00:00');

    Permission::firstOrCreate(['name' => UserActivityAudit::getPermissions()['view'], 'guard_name' => 'web']);

    $this->staff = User::factory()->create();
    $this->staff->givePermissionTo(UserActivityAudit::getPermissions()['view']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders the user activity audit page for authorized users', function () {
    $this->actingAs($this->staff)
        ->get(route('user-activity-audit.index'))
        ->assertSuccessful()
        ->assertSee('User Activity Audit', false)
        ->assertSee('data-testid="user-activity-audit-filters"', false)
        ->assertSee('value="2026-10"', false);
});

it('forbids users without user-activity-audit-view', function () {
    $other = User::factory()->create();

    $this->actingAs($other)
        ->get(route('user-activity-audit.index'))
        ->assertForbidden();
});

it('scopes suspicious timing to the selected calendar month', function () {
    $inMonth = Transaction::factory()->create([
        'date' => '2026-10-01',
        'type' => Transaction::TYPE_BUY,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'user_id' => $this->staff->id,
        'created_at' => now(),
    ]);

    $otherMonth = Transaction::factory()->create([
        'date' => '2026-08-15',
        'type' => Transaction::TYPE_BUY,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'user_id' => $this->staff->id,
        'created_at' => now(),
    ]);

    $filters = app(UserActivityAuditService::class)->resolveFilters([
        'month' => '2026-10',
        'late_entry_days' => 7,
    ]);

    $ids = app(UserActivityAuditService::class)
        ->suspiciousTimingQuery($filters)
        ->pluck('id');

    expect($ids)->toContain($inMonth->id)
        ->and($ids)->not->toContain($otherMonth->id);
});

it('flags transactions created long after their transaction date', function () {
    $txnDate = '2026-10-01';

    $txn = Transaction::factory()->create([
        'date' => $txnDate,
        'type' => Transaction::TYPE_BUY,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'user_id' => $this->staff->id,
        'created_at' => now(),
    ]);

    $filters = app(UserActivityAuditService::class)->resolveFilters([
        'month' => '2026-10',
        'late_entry_days' => 7,
    ]);

    $flags = app(UserActivityAuditService::class)->timingFlags($txn, $filters);

    expect($flags)->toContain('Entri 7+ hari setelah tanggal transaksi');
});

it('ranks users who frequently move stock into virtual warehouses within the month', function () {
    $virtual = Addrbook::factory()->create(['type' => Addrbook::TYPE_V_WAREHOUSE]);
    $physical = Addrbook::factory()->create(['type' => Addrbook::TYPE_WAREHOUSE]);

    foreach (range(1, 3) as $i) {
        Transaction::factory()->create([
            'date' => '2026-10-05',
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

    Transaction::factory()->create([
        'date' => '2026-09-05',
        'type' => Transaction::TYPE_MOVE,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
        'user_id' => $this->staff->id,
        'sender_id' => $physical->id,
        'sender_type' => (string) Addrbook::TYPE_WAREHOUSE,
        'receiver_id' => $virtual->id,
        'receiver_type' => (string) Addrbook::TYPE_V_WAREHOUSE,
        'invoice' => 'MOVE-AUDIT-OLD',
    ]);

    $filters = app(UserActivityAuditService::class)->resolveFilters([
        'month' => '2026-10',
        'frequent_min_count' => 3,
    ]);

    $rows = app(UserActivityAuditService::class)->frequentVirtualWarehouseMoves($filters);

    expect($rows)->not->toBeEmpty()
        ->and($rows[0]['user_id'])->toBe($this->staff->id)
        ->and($rows[0]['move_count'])->toBe(3);
});

it('runs the artisan audit command for a month', function () {
    Artisan::call('app:user-activity-audit', [
        '--month' => '2026-10',
    ]);

    expect(Artisan::output())->toContain('Bulan 2026-10');
});
