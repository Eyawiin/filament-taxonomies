<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** @internal Validate empty selections as well as selected IDs. */
class TaxonomySelectionRule implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private readonly TaxonomySelect $field) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->field->lockFormTaxonomies();
        if (! $this->field->acceptsSelection($value)) {
            $fail(__('filament-taxonomies::assignment-tree.invalid'));
        }
    }
}
