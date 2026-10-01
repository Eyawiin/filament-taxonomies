<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/** @internal Preserve invalid input for field validation instead of silently coercing it. */
class TaxonomyParentIdCast implements StateCast
{
    public function get(mixed $state): mixed
    {
        return $this->set($state);
    }

    public function set(mixed $state): mixed
    {
        if ($state === null || $state === '') {
            return null;
        }

        return is_int($state) || is_string($state) ? $state : '__invalid_parent_id__';
    }
}
