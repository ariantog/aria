<?php

namespace Database\Seeders;

use App\Enums\AddrbookType;
use App\Models\Addrbook;
use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds a small, realistic dataset so the preview has something to show:
 * banks, warehouses, customers, suppliers, items and a spread of transactions.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Guard: this seeder writes display-only transaction rows that deliberately bypass
        // TransactionService (no inventory movement, no ledger entries). Those rows are fine for a
        // preview database but would corrupt real accounting data, so never run outside local/dev.
        if (app()->isProduction()) {
            $this->command->error('DemoDataSeeder refuses to run in production.');

            return;
        }

        $userId = DB::table('users')->where('username', 'superadmin')->value('id');

        // ---- Address book entries -------------------------------------------------
        $book = [];
        $entries = [
            ['BCA Operasional',        AddrbookType::Bank->value],
            ['Mandiri Payroll',        AddrbookType::Bank->value],
            ['Kas Tunai',              AddrbookType::Bank->value],
            ['Gudang Pusat',           AddrbookType::Warehouse->value],
            ['Gudang Cabang Bandung',  AddrbookType::Warehouse->value],
            ['Toko Maju Jaya',         AddrbookType::Customer->value],
            ['CV Sinar Abadi',         AddrbookType::Customer->value],
            ['PT Sumber Tekstil',      AddrbookType::Supplier->value],
            ['PT Benang Nusantara',    AddrbookType::Supplier->value],
            ['Reseller Online',        AddrbookType::Reseller->value],
            ['Beban Operasional',      AddrbookType::Account->value],
            ['Pendapatan Lain',        AddrbookType::Account->value],
        ];

        // firstOrCreate, not updateOrCreate: never overwrite a contact that already exists,
        // in case these demo names collide with real data.
        foreach ($entries as $i => [$name, $type]) {
            $addrbook = Addrbook::firstOrCreate(
                ['name' => $name],
                [
                    'type'    => $type,
                    'ppn'     => $type === AddrbookType::Supplier->value,
                    // Deterministic, so re-running produces identical data.
                    'phone'   => '08'.str_pad((string) (1000000 + $i), 10, '0', STR_PAD_LEFT),
                    'address' => 'Jl. Contoh No. '.($i + 1).', Jakarta',
                ]
            );
            $book[$name] = $addrbook->id;
        }

        $locationId = DB::table('locations')->orderBy('id')->value('id');
        if ($locationId) {
            foreach (array_values($book) as $addrbookId) {
                Addrbook::find($addrbookId)?->locations()->syncWithoutDetaching([$locationId]);
            }
        }

        // ---- Items ----------------------------------------------------------------
        $items = [
            ['Kaos Polos Hitam L',    'KPH-L',  85000,  55000],
            ['Kaos Polos Putih M',    'KPP-M',  85000,  55000],
            ['Kemeja Flanel Merah',   'KFM-01', 195000, 120000],
            ['Celana Chino Navy 32',  'CCN-32', 245000, 155000],
            ['Jaket Bomber Olive',    'JBO-01', 350000, 220000],
            ['Topi Baseball Hitam',   'TBH-01', 75000,  40000],
            ['Kaos Kaki Pack 3',      'KK-P3',  45000,  22000],
            ['Hoodie Abu-abu XL',     'HAX-XL', 275000, 175000],
        ];

        $itemIds = [];
        foreach ($items as $i => [$name, $code, $price, $cost]) {
            $itemIds[] = Item::firstOrCreate(
                ['code' => $code],
                [
                    'name'  => $name,
                    'pcode' => $code,
                    'price' => $price,
                    'cost'  => $cost,
                    'qty'   => 25 * ($i + 2), // deterministic
                    'type'  => 1,
                ]
            )->id;
        }

        // ---- Transactions ---------------------------------------------------------
        if (Transaction::count() > 0) {
            $this->command->info('Transactions already exist — skipping transaction seed.');

            return;
        }

        $plan = [
            // [type, sender, receiver, invoice prefix]
            [Transaction::TYPE_BUY,      'PT Sumber Tekstil',   'Gudang Pusat',           'PO'],
            [Transaction::TYPE_BUY,      'PT Benang Nusantara', 'Gudang Pusat',           'PO'],
            [Transaction::TYPE_SELL,     'Gudang Pusat',        'Toko Maju Jaya',         'INV'],
            [Transaction::TYPE_SELL,     'Gudang Pusat',        'CV Sinar Abadi',         'INV'],
            [Transaction::TYPE_SELL,     'Gudang Cabang Bandung', 'Reseller Online',      'INV'],
            [Transaction::TYPE_MOVE,     'Gudang Pusat',        'Gudang Cabang Bandung',  'MV'],
            [Transaction::TYPE_TRANSFER, 'BCA Operasional',     'Mandiri Payroll',        'TRF'],
            [Transaction::TYPE_TRANSFER, 'BCA Operasional',     'Kas Tunai',              'TRF'],
            [Transaction::TYPE_CASH_IN,  'Toko Maju Jaya',      'BCA Operasional',        'CI'],
            [Transaction::TYPE_CASH_IN,  'CV Sinar Abadi',      'BCA Operasional',        'CI'],
            [Transaction::TYPE_CASH_OUT, 'BCA Operasional',     'PT Sumber Tekstil',      'CO'],
            [Transaction::TYPE_CASH_OUT, 'Kas Tunai',           'Beban Operasional',      'CO'],
            [Transaction::TYPE_ADJUST,   'Beban Operasional',   'Kas Tunai',              'ADJ'],
            [Transaction::TYPE_RETURN,   'Toko Maju Jaya',      'Gudang Pusat',           'RET'],
            [Transaction::TYPE_RETURN_SUPPLIER, 'Gudang Pusat', 'PT Sumber Tekstil',      'RTS'],
        ];

        // Resolve each contact's addrbook type id — the app stores the *type id* in the morph
        // column (see the morph map in AppServiceProvider), not a class name.
        // `type` is cast to the AddrbookType enum, so take the backing value.
        $typeOf = Addrbook::whereIn('id', array_values($book))
            ->pluck('type', 'id')
            ->map(fn ($t) => (string) ($t instanceof AddrbookType ? $t->value : $t));

        $seq = 1;
        foreach ($plan as $i => [$type, $senderName, $receiverName, $prefix]) {
            // Spread over the last ~60 days
            $date = now()->subDays(60 - ($i * 4))->startOfDay();

            // Deterministic amounts so re-seeding a wiped DB reproduces the same figures.
            $totalItems = 2 + ($i * 3) % 39;
            $subtotal   = (5 + ($i * 17) % 115) * 100_000;
            $discount   = (int) round($subtotal * ((($i * 3) % 11) / 100));
            $afterDisc  = $subtotal - $discount;
            $tax        = in_array($type, [Transaction::TYPE_BUY, Transaction::TYPE_RETURN_SUPPLIER], true)
                ? (int) round($afterDisc * 0.11)
                : 0;
            $grand = $afterDisc + $tax;

            $senderId   = $book[$senderName];
            $receiverId = $book[$receiverName];

            $row = [
                'date' => $date->toDateString(),
                'type' => $type,
                'due' => $date->copy()->addDays(30)->toDateString(),
                'sender_type' => $typeOf[$senderId] ?? '',
                'sender_id' => $senderId,
                'receiver_type' => $typeOf[$receiverId] ?? '',
                'receiver_id' => $receiverId,
                'invoice' => sprintf('%s-%s-%04d', $prefix, $date->format('Ym'), $seq++),
                'description' => 'Sample seeded '.strtolower($prefix).' transaction for preview.',
                'submit_type' => Transaction::SUBMIT_TYPE_MANUAL,
                'discount' => $discount,
                'adjustment' => 0,
                'ppn' => $tax,
                'total' => Transaction::signedAmount($type, $grand),
                'total_items' => $totalItems,
                'sender_balance' => (10 + $i * 7) * 100_000,
                'receiver_balance' => (20 + $i * 5) * 100_000,
                'status' => 1,
                'user_id' => $userId,
            ];

            if (Schema::hasColumn('transactions', 'discount_percent')) {
                $row['discount_percent'] = $subtotal > 0 ? round($discount / $subtotal * 100, 2) : 0;
            }

            Transaction::create($row);
        }

        $this->command->info('Demo data seeded: '.count($book).' contacts, '.count($itemIds).' items, '.count($plan).' transactions.');
    }
}
