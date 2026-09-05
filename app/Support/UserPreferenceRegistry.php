<?php

namespace App\Support;

use App\Enums\AddrbookType;
use App\Models\Addrbook;

class UserPreferenceRegistry
{
    public const APPEARANCE_SLUG = 'ui.appearance';

    public const FONT_SIZE_SLUG = 'ui.font_size';

    public const FAVORITES_SLUG = 'sidebar.favorites';

    public const FAVORITES_MAX = 5;

    /**
     * @return list<string>
     */
    public static function appearanceOptions(): array
    {
        return ['light', 'dark', 'system'];
    }

    /**
     * @return list<string>
     */
    public static function fontSizeOptions(): array
    {
        return ['small', 'default', 'large'];
    }

    /**
     * @return array<string, string>
     */
    public static function fontSizePixels(): array
    {
        return [
            'small' => '13px',
            'default' => '14px',
            'large' => '16px',
        ];
    }

    /**
     * Transaction-default preference slugs and their addrbook type constraints.
     *
     * @return array<string, array{label: string, hint: string, group: string, types: list<int>, nullable: bool}>
     */
    public static function transactionDefaults(): array
    {
        return [
            'transactions.default_supplier_id' => [
                'label' => 'Default supplier',
                'hint' => 'Sender on Buy transactions.',
                'group' => 'Buy',
                'types' => [AddrbookType::Supplier->value],
                'nullable' => true,
            ],
            'transactions.default_warehouse_id' => [
                'label' => 'Default warehouse',
                'hint' => 'Receiver on Buy; sender on Sell, Move, and Return Supplier.',
                'group' => 'Warehouse',
                'types' => [AddrbookType::Warehouse->value],
                'nullable' => true,
            ],
            'transactions.default_move_receiver_id' => [
                'label' => 'Default move destination',
                'hint' => 'Receiver on Move transactions.',
                'group' => 'Warehouse',
                'types' => [AddrbookType::Warehouse->value, AddrbookType::VirtualWarehouse->value],
                'nullable' => true,
            ],
            'transactions.default_customer_id' => [
                'label' => 'Default customer / reseller',
                'hint' => 'Receiver on Sell transactions.',
                'group' => 'Sell',
                'types' => [AddrbookType::Customer->value, AddrbookType::Reseller->value],
                'nullable' => true,
            ],
            'transactions.default_cash_in_bank_id' => [
                'label' => 'Default bank (Cash In)',
                'hint' => 'Bank account that receives cash in.',
                'group' => 'Cash',
                'types' => [AddrbookType::Bank->value],
                'nullable' => true,
            ],
            'transactions.default_cash_out_bank_id' => [
                'label' => 'Default bank (Cash Out)',
                'hint' => 'Bank account that pays cash out.',
                'group' => 'Cash',
                'types' => [AddrbookType::Bank->value],
                'nullable' => true,
            ],
            'transactions.default_transfer_from_id' => [
                'label' => 'Default transfer from',
                'hint' => 'Source account on Transfer transactions.',
                'group' => 'Transfer',
                'types' => Addrbook::transferAccountTypes(),
                'nullable' => true,
            ],
            'transactions.default_transfer_to_id' => [
                'label' => 'Default transfer to',
                'hint' => 'Destination account on Transfer transactions.',
                'group' => 'Transfer',
                'types' => Addrbook::transferAccountTypes(),
                'nullable' => true,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function transactionDefaultFieldMap(): array
    {
        $map = [];
        foreach (array_keys(self::transactionDefaults()) as $slug) {
            $map[$slug] = str_replace('transactions.', '', $slug);
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public static function lookupTypes(): array
    {
        return [
            'supplier',
            'warehouse',
            'move_receiver',
            'customer',
            'bank',
            'transfer_account',
        ];
    }

    /**
     * @return list<int>
     */
    public static function lookupAddrbookTypes(string $type): array
    {
        return match ($type) {
            'supplier' => [AddrbookType::Supplier->value],
            'warehouse' => [AddrbookType::Warehouse->value],
            'move_receiver' => [AddrbookType::Warehouse->value, AddrbookType::VirtualWarehouse->value],
            'customer' => [AddrbookType::Customer->value, AddrbookType::Reseller->value],
            'bank' => [AddrbookType::Bank->value],
            'transfer_account' => Addrbook::transferAccountTypes(),
            default => [],
        };
    }
}
