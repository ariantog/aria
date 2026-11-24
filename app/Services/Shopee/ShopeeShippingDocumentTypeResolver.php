<?php

namespace App\Services\Shopee;

/**
 * Picks shipping_document_type from Shopee get_shipping_document_parameter (thermal vs normal AWB).
 */
class ShopeeShippingDocumentTypeResolver
{
    public const TYPE_THERMAL = 'THERMAL_AIR_WAYBILL';

    public const TYPE_NORMAL = 'NORMAL_AIR_WAYBILL';

    public function defaultType(): string
    {
        $type = trim((string) config('services.shopee_admin.shipping_document_type', self::TYPE_NORMAL));

        return $type !== '' ? $type : self::TYPE_NORMAL;
    }

    public function isThermalType(string $type): bool
    {
        return strtoupper(trim($type)) === self::TYPE_THERMAL;
    }

    /**
     * @param  array<string, mixed>  $parameterRow
     */
    public function pickFromParameterRow(array $parameterRow): string
    {
        $suggested = trim((string) ($parameterRow['suggest_shipping_document_type'] ?? ''));
        $selectable = $this->normalizeSelectableList($parameterRow);

        if ($suggested !== '' && ($selectable === [] || in_array($suggested, $selectable, true))) {
            return $suggested;
        }

        if (in_array(self::TYPE_THERMAL, $selectable, true)) {
            return self::TYPE_THERMAL;
        }

        if (in_array(self::TYPE_NORMAL, $selectable, true)) {
            return self::TYPE_NORMAL;
        }

        foreach ($selectable as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return $this->defaultType();
    }

    /**
     * @param  array<string, mixed>  $parameterRow
     * @return list<string>
     */
    private function normalizeSelectableList(array $parameterRow): array
    {
        $raw = $parameterRow['selectable_shipping_document_type']
            ?? $parameterRow['selectable_shipping_document_type_list']
            ?? $parameterRow['selectable_shipping_document_types']
            ?? [];

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($v) => trim((string) $v),
            $raw,
        ))));
    }
}