<?php

use App\Services\Items\SpecialSkuConverterRules;

describe('fabricband family', function () {
    beforeEach(function () {
        $this->rules = new SpecialSkuConverterRules;
    });

    it('parses legacy fabricband sku with size before color', function () {
        $parsed = $this->rules->parseLegacyCode('FABRICBAND-03-LIGHT-BABYBLUE');

        expect($parsed)->toBe([
            'family_id' => 'fabricband',
            'pcode' => 'FABRICBAND-03',
            'size' => 'LIGHT',
            'color' => 'BABYBLUE',
        ]);
    });

    it('builds canonical sku with color before size', function () {
        expect($this->rules->buildCanonicalCode('FABRICBAND-03', 'BABYBLUE', 'LIGHT'))
            ->toBe('FABRICBAND-03-BABYBLUE-LIGHT');
    });

    it('accepts medium and heavy sizes', function () {
        expect($this->rules->parseLegacyCode('FABRICBAND-03-MEDIUM-RED')['size'])->toBe('MEDIUM')
            ->and($this->rules->parseLegacyCode('FABRICBAND-03-HEAVY-NAVY')['size'])->toBe('HEAVY');
    });

    it('rejects canonical-order codes', function () {
        expect($this->rules->parseLegacyCode('FABRICBAND-03-BABYBLUE-LIGHT'))->toBeNull();
    });
});
