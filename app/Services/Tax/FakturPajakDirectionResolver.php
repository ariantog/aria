<?php

namespace App\Services\Tax;

use App\Models\ReportingEntity;
use App\Support\NpwpNormalizer;

class FakturPajakDirectionResolver
{
    public const DIRECTION_KELUARAN = 'keluaran';

    public const DIRECTION_MASUKAN = 'masukan';

    /**
     * @return array{
     *     direction: string,
     *     reporting_entity_id: int|null,
     *     matched_side: string|null,
     * }
     */
    public function suggest(ParsedFakturPajak $parsed): array
    {
        $entities = ReportingEntity::query()
            ->where('is_active', true)
            ->where('is_pkp', true)
            ->get(['id', 'name', 'npwp']);

        foreach ($entities as $entity) {
            if (NpwpNormalizer::matches($entity->npwp, $parsed->sellerNpwp)) {
                return [
                    'direction' => self::DIRECTION_KELUARAN,
                    'reporting_entity_id' => $entity->id,
                    'matched_side' => 'seller',
                ];
            }

            if (NpwpNormalizer::matches($entity->npwp, $parsed->buyerNpwp)) {
                return [
                    'direction' => self::DIRECTION_MASUKAN,
                    'reporting_entity_id' => $entity->id,
                    'matched_side' => 'buyer',
                ];
            }
        }

        return [
            'direction' => self::DIRECTION_KELUARAN,
            'reporting_entity_id' => $entities->first()?->id,
            'matched_side' => null,
        ];
    }
}
