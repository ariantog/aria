<?php

namespace App\Models\Concerns;

trait MapsProductionColumns
{
    /**
     * Config key under production_schema.columns (e.g. "transactions").
     */
    protected static function productionColumnKey(): string
    {
        throw new \LogicException(static::class.' must implement productionColumnKey().');
    }

    protected function productionColumnMap(): array
    {
        if (! config('production_schema.enabled')) {
            return [];
        }

        return config('production_schema.columns.'.static::productionColumnKey(), []);
    }

    protected function toProductionColumn(string $attribute): string
    {
        return $this->productionColumnMap()[$attribute] ?? $attribute;
    }

    protected function fromProductionColumn(string $column): string
    {
        $flipped = array_flip($this->productionColumnMap());

        return $flipped[$column] ?? $column;
    }

    public function getAttribute($key)
    {
        $column = $this->toProductionColumn($key);

        if ($column !== $key && array_key_exists($column, $this->attributes)) {
            return parent::getAttribute($column);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        $column = $this->toProductionColumn($key);

        if ($column !== $key) {
            return parent::setAttribute($column, $value);
        }

        return parent::setAttribute($key, $value);
    }

    public function qualifyColumn($column)
    {
        if (is_string($column)) {
            $column = $this->toProductionColumn($column);
        }

        return parent::qualifyColumn($column);
    }
}
