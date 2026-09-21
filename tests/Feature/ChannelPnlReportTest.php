<?php

use App\Enums\ReportingLedgerRole;
use App\Models\Addrbook;
use App\Models\ReportingEntity;
use App\Models\ReportingLedgerRole as ReportingLedgerRoleModel;
use App\Models\ReportingWarehouseFulfillment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PermissionGenerator;
use App\Services\Reporting\ChannelPnlService;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->user = User::factory()->create();
    app(PermissionGenerator::class)->generateForModule('Report');
    $this->user->givePermissionTo('report-channel-pnl');
});

function seedChannelPnlScenario(): array
{
    $entityA = ReportingEntity::create(['name' => 'CV Channel A', 'slug' => 'cv-channel-a', 'is_pkp' => true]);
    $entityB = ReportingEntity::create(['name' => 'CV Channel B', 'slug' => 'cv-channel-b', 'is_pkp' => false]);
    $bankA = Addrbook::create(['name' => 'Bank Channel A', 'type' => Addrbook::TYPE_BANK]);
    $bankB = Addrbook::create(['name' => 'Bank Channel B', 'type' => Addrbook::TYPE_BANK]);
    $entityA->banks()->attach($bankA->id, ['is_active' => true]);
    $entityB->banks()->attach($bankB->id, ['is_active' => true]);

    $warehouse = Addrbook::factory()->warehouse()->create(['name' => 'Gudang WTC']);
    $shopee = Addrbook::factory()->customer()->create(['name' => 'Shopee Channel']);
    $tiktok = Addrbook::factory()->customer()->create(['name' => 'TikTok Channel']);
    $walkIn = Addrbook::factory()->customer()->create(['name' => 'Toko Regular']);
    $lender = Addrbook::factory()->customer()->create([
        'name' => 'Pinjaman Internal',
        'is_internal_lending' => true,
    ]);

    ReportingWarehouseFulfillment::create([
        'warehouse_id' => $warehouse->id,
        'customer_id' => $shopee->id,
        'notes' => 'Online from WTC',
    ]);
    ReportingWarehouseFulfillment::create([
        'warehouse_id' => $warehouse->id,
        'customer_id' => $tiktok->id,
    ]);

    $biayaShopee = Addrbook::create(['name' => 'Biaya Shopee', 'type' => Addrbook::TYPE_ACCOUNT]);
    $biayaToko = Addrbook::create(['name' => 'Biaya Toko WTC', 'type' => Addrbook::TYPE_ACCOUNT]);
    $biayaLazada = Addrbook::create(['name' => 'Biaya Lazada', 'type' => Addrbook::TYPE_ACCOUNT]);
    ReportingLedgerRoleModel::create(['customer_id' => $biayaShopee->id, 'role' => ReportingLedgerRole::MarketplaceCost]);
    ReportingLedgerRoleModel::create(['customer_id' => $biayaToko->id, 'role' => ReportingLedgerRole::TokoCost]);
    ReportingLedgerRoleModel::create(['customer_id' => $biayaLazada->id, 'role' => ReportingLedgerRole::MarketplaceCost]);

    return compact(
        'entityA',
        'entityB',
        'bankA',
        'bankB',
        'warehouse',
        'shopee',
        'tiktok',
        'walkIn',
        'lender',
        'biayaShopee',
        'biayaToko',
        'biayaLazada',
    );
}

function createChannelPnlTransaction(array $overrides = []): Transaction
{
    $defaults = [
        'date' => '2026-01-15',
        'type' => Transaction::TYPE_CASH_IN,
        'sender_type' => Addrbook::TYPE_CUSTOMER,
        'sender_id' => Addrbook::factory()->customer()->create()->id,
        'receiver_type' => Addrbook::TYPE_BANK,
        'receiver_id' => Addrbook::factory()->create(['type' => Addrbook::TYPE_BANK])->id,
        'total' => 1_000_000,
        'real_total' => 1_000_000,
        'status' => Transaction::STATUS_COMPLETED,
        'user_id' => test()->user->id,
        'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
    ];

    return Transaction::withoutEvents(
        fn () => Transaction::create(array_merge($defaults, $overrides)),
    );
}

it('renders the channel pnl page for an authorized user', function () {
    $this->actingAs($this->user)
        ->get(route('reports.channel-pnl', ['year' => 2026, 'month' => 1]))
        ->assertOk()
        ->assertSee('Laporan Channel P&L', false)
        ->assertSee('data-testid="channel-pnl-page"', false)
        ->assertSee('data-testid="channel-pnl-export-xlsx"', false)
        ->assertSee('Cash In/Out bank per marketplace/channel', false);
});

it('forbids users without report-channel-pnl permission', function () {
    $other = User::factory()->create();
    expect($other->is_superadmin)->toBeFalse();

    $this->actingAs($other)
        ->get(route('reports.channel-pnl'))
        ->assertForbidden();
});

it('attributes cash-in and marketplace or toko costs to fulfillment channels', function () {
    $data = seedChannelPnlScenario();

    createChannelPnlTransaction([
        'date' => '2026-01-10',
        'sender_id' => $data['shopee']->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 750_000,
        'real_total' => 750_000,
        'invoice' => 'CIN-SHOPEE-1',
    ]);
    createChannelPnlTransaction([
        'date' => '2026-01-11',
        'sender_id' => $data['tiktok']->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 250_000,
        'real_total' => 250_000,
        'invoice' => 'CIN-TIKTOK-1',
    ]);
    createChannelPnlTransaction([
        'date' => '2026-01-12',
        'sender_id' => $data['walkIn']->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 80_000,
        'real_total' => 80_000,
        'invoice' => 'CIN-WALK-1',
    ]);
    createChannelPnlTransaction([
        'date' => '2026-01-13',
        'sender_id' => $data['lender']->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 400_000,
        'real_total' => 400_000,
        'invoice' => 'CIN-LEND-1',
    ]);
    createChannelPnlTransaction([
        'date' => '2026-01-14',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $data['bankA']->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $data['biayaShopee']->id,
        'total' => -30_000,
        'real_total' => -30_000,
    ]);
    createChannelPnlTransaction([
        'date' => '2026-01-15',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $data['bankA']->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $data['biayaToko']->id,
        'total' => -100_000,
        'real_total' => -100_000,
    ]);
    createChannelPnlTransaction([
        'date' => '2026-01-16',
        'type' => Transaction::TYPE_CASH_OUT,
        'sender_type' => Addrbook::TYPE_BANK,
        'sender_id' => $data['bankA']->id,
        'receiver_type' => Addrbook::TYPE_ACCOUNT,
        'receiver_id' => $data['biayaLazada']->id,
        'total' => -12_000,
        'real_total' => -12_000,
    ]);

    $report = app(ChannelPnlService::class)->build(2026, 1, $data['entityA']->id);
    $byName = collect($report['rows'])->keyBy('name');

    expect($byName['Shopee Channel']['pendapatan'])->toBe(750_000.0)
        ->and($byName['Shopee Channel']['marketplace_cost'])->toBe(30_000.0)
        ->and($byName['Shopee Channel']['toko_cost'])->toBe(75_000.0)
        ->and($byName['Shopee Channel']['kontribusi'])->toBe(645_000.0)
        ->and($byName['Shopee Channel']['warehouses'])->toContain('Gudang WTC')
        ->and($byName['TikTok Channel']['pendapatan'])->toBe(250_000.0)
        ->and($byName['TikTok Channel']['marketplace_cost'])->toBe(0.0)
        ->and($byName['TikTok Channel']['toko_cost'])->toBe(25_000.0)
        ->and($byName['Pendapatan tidak terpetakan']['pendapatan'])->toBe(80_000.0)
        ->and($byName['Tidak teralokasi']['marketplace_cost'])->toBe(12_000.0)
        ->and($report['totals']['pendapatan'])->toBe(1_080_000.0)
        ->and(collect($report['drilldown']['pendapatan'])->pluck('invoice')->all())
        ->toContain('CIN-SHOPEE-1')
        ->toContain('CIN-WALK-1')
        ->not->toContain('CIN-LEND-1');

    $this->actingAs($this->user)
        ->get(route('reports.channel-pnl', [
            'year' => 2026,
            'month' => 1,
            'entity' => $data['entityA']->id,
        ]))
        ->assertOk()
        ->assertSee('Shopee Channel', false)
        ->assertSee('TikTok Channel', false)
        ->assertSee('Pendapatan tidak terpetakan', false)
        ->assertSee('Tidak teralokasi', false)
        ->assertDontSee('CIN-LEND-1', false);
});

it('scopes cash to the selected entity and sums konsolidasi', function () {
    $data = seedChannelPnlScenario();

    createChannelPnlTransaction([
        'date' => '2026-01-10',
        'sender_id' => $data['shopee']->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 100_000,
        'real_total' => 100_000,
    ]);
    createChannelPnlTransaction([
        'date' => '2026-01-11',
        'sender_id' => $data['shopee']->id,
        'receiver_id' => $data['bankB']->id,
        'total' => 40_000,
        'real_total' => 40_000,
    ]);

    $service = app(ChannelPnlService::class);
    $onlyA = $service->build(2026, 1, $data['entityA']->id);
    $onlyB = $service->build(2026, 1, $data['entityB']->id);
    $konsolidasi = $service->build(2026, 1, ChannelPnlService::CONSOLIDATED_ENTITY);

    $rev = fn (array $report) => collect($report['rows'])->firstWhere('name', 'Shopee Channel')['pendapatan'] ?? 0;

    expect($rev($onlyA))->toBe(100_000.0)
        ->and($rev($onlyB))->toBe(40_000.0)
        ->and($rev($konsolidasi))->toBe(140_000.0)
        ->and($onlyA['is_consolidated'])->toBeFalse()
        ->and($konsolidasi['is_consolidated'])->toBeTrue()
        ->and($konsolidasi['entity_label'])->toBe('Konsolidasi');
});

it('includes sales_channel customers that are not in fulfillment', function () {
    $data = seedChannelPnlScenario();
    $metro = Addrbook::factory()->customer()->create([
        'name' => 'Metro Dept Store',
        'reporting_role' => 'sales_channel',
    ]);

    createChannelPnlTransaction([
        'date' => '2026-01-09',
        'sender_id' => $metro->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 55_000,
        'real_total' => 55_000,
    ]);

    $report = app(ChannelPnlService::class)->build(2026, 1, $data['entityA']->id);
    $metroRow = collect($report['rows'])->firstWhere('name', 'Metro Dept Store');

    expect($metroRow)->not->toBeNull()
        ->and($metroRow['pendapatan'])->toBe(55_000.0)
        ->and($metroRow['from_fulfillment'])->toBeFalse();
});

it('omits 2024 cash from channel pnl', function () {
    $data = seedChannelPnlScenario();

    createChannelPnlTransaction([
        'date' => '2024-12-15',
        'sender_id' => $data['shopee']->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 99_000,
        'real_total' => 99_000,
    ]);

    $report = app(ChannelPnlService::class)->build(2024, 12, $data['entityA']->id);

    expect($report['rows'])->toBeEmpty()
        ->and($report['totals']['pendapatan'])->toBe(0.0);
});

it('exports channel pnl as csv and xlsx', function () {
    $data = seedChannelPnlScenario();
    createChannelPnlTransaction([
        'date' => '2026-01-08',
        'sender_id' => $data['shopee']->id,
        'receiver_id' => $data['bankA']->id,
        'total' => 75_000,
        'real_total' => 75_000,
    ]);

    $csv = $this->actingAs($this->user)
        ->get(route('reports.channel-pnl', [
            'year' => 2026,
            'month' => 1,
            'entity' => $data['entityA']->id,
            'export' => 'csv',
        ]));

    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv');
    expect($csv->streamedContent())
        ->toContain('Laporan Channel P&L')
        ->toContain('Shopee Channel')
        ->toContain('Kontribusi');

    $xlsx = $this->actingAs($this->user)
        ->get(route('reports.channel-pnl', [
            'year' => 2026,
            'month' => 1,
            'entity' => $data['entityA']->id,
            'export' => 'xlsx',
        ]));

    $xlsx->assertOk();
    expect($xlsx->headers->get('content-type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $tmp = tempnam(sys_get_temp_dir(), 'channel-pnl-xlsx-');
    file_put_contents($tmp, $xlsx->streamedContent());
    $sheet = IOFactory::load($tmp)->getActiveSheet();
    unlink($tmp);

    $values = collect($sheet->toArray())->flatten()->filter();
    expect($values->all())->toContain('Laporan Channel P&L')
        ->and($values->all())->toContain('Shopee Channel');
});
