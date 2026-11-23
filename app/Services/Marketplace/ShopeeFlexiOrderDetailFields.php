<?php

namespace App\Services\Marketplace;

/** Shared get_order_detail optional fields for FLEXI lookup / prefill (aligned with poll ingest). */
final class ShopeeFlexiOrderDetailFields
{
    public const OPTIONAL = 'order_status,item_list,package_list,total_amount,currency,payment_info';
}