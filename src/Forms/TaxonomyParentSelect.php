<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rules\Exists;

class TaxonomyParentSelect
{
    public static function make(
        Taxonomy $taxonomy,
        ?TaxonomyTerm $term = null,
    ): Select {
        $query = TaxonomyTerm::query()
            ->where('taxonomy_id', $taxonomy->getKey())
            ->orderBy('name');

        if ($term !== null) {
            $excludedIds = [
                (int) $term->getKey(),
                ...app(TaxonomyTreeService::class)->getDescendantIds($term),
            ];

            $query->whereNotIn('id', $excludedIds);
        }

        $options = $query
            ->pluck('name', 'id')
            ->mapWithKeys(
                static fn (mixed $name, mixed $id): array => [
                    (int) $id => (string) $name,
                ],
            )
            ->all();

        return Select::make('parent_id')
            ->label('Parent')
            ->options($options)
            ->searchable()
            ->native(false)
            ->nullable()
            ->placeholder('No parent (root term)')
            ->exists(
                table: TaxonomyTerm::class,
                column: 'id',
                modifyRuleUsing: static function (Exists $rule) use ($taxonomy): Exists {
                    return $rule->where(
                        'taxonomy_id',
                        $taxonomy->getKey(),
                    );
                },
            );
    }
}
