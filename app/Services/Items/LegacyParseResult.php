<?php

namespace App\Services\Items;

class LegacyParseResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $failureCode = null,
        public readonly ?string $detail = null,
        public readonly ?string $pcode = null,
        public readonly ?string $typeCode = null,
        public readonly ?string $warnaCode = null,
        public readonly ?string $sizeCode = null,
        public readonly ?string $canonicalCode = null,
        public readonly ?string $groupName = null,
        public readonly ?string $legacyCode = null,
        public readonly bool $codeUnchanged = false,
        public readonly array $snapshot = [],
    ) {}

    public static function failure(string $code, string $detail, array $snapshot = []): self
    {
        return new self(
            success: false,
            failureCode: $code,
            detail: $detail,
            snapshot: $snapshot,
        );
    }

    public static function success(
        string $pcode,
        ?string $typeCode,
        string $warnaCode,
        ?string $sizeCode,
        string $canonicalCode,
        string $groupName,
        ?string $legacyCode = null,
        bool $codeUnchanged = false,
        array $snapshot = [],
    ): self {
        return new self(
            success: true,
            pcode: $pcode,
            typeCode: $typeCode,
            warnaCode: $warnaCode,
            sizeCode: $sizeCode,
            canonicalCode: $canonicalCode,
            groupName: $groupName,
            legacyCode: $legacyCode,
            codeUnchanged: $codeUnchanged,
            snapshot: $snapshot,
        );
    }
}
