<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyIdentity;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Filament\Resources\Resource as FilamentResource;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TaxonomyParentSelect
{
    /**
     * @param  class-string<FilamentResource>  $resource  The managing resource; usually TaxonomyResource or a subclass.
     */
    public static function make(
        Taxonomy $taxonomy,
        ?TaxonomyTerm $term = null,
        string $resource = TaxonomyResource::class,
    ): TaxonomyParentField {
        abort_if(TaxonomyIdentity::normalize($taxonomy->getRawOriginal($taxonomy->getKeyName())) === null, 404);

        /** @var Taxonomy $taxonomy */
        $taxonomy = $resource::getEloquentQuery()->whereKey($taxonomy->getKey())->firstOrFail();
        abort_unless($resource::canAccess() && $resource::canView($taxonomy), 403);

        if ($term !== null) {
            abort_if(TaxonomyIdentity::normalize($term->getRawOriginal($term->getKeyName())) === null, 404);
            $terms = $taxonomy->terms();
            /** @var TaxonomyTerm $term A scalar key yields one model. */
            $term = $terms->findOrFail($term->getKey(), [$terms->getRelated()->qualifyColumn('*')]);
        }

        return TaxonomyParentField::make('parent_id')
            ->parentOf($taxonomy, $term)
            ->label(__('filament-taxonomies::parent-tree.parent'))
            ->lazyLoadingThreshold(static fn (): int => TaxonomyResource::plugin()->getLazyLoadingThreshold())
            ->stateCast(new TaxonomyParentIdCast)
            ->nullable()
            ->rules(static fn (TaxonomyParentField $component): array => [
                'integer',
                'min:1',
                Rule::in($component->getAvailableIds()),
            ])
            ->exists(
                table: TaxonomyModels::term(),
                column: 'id',
                modifyRuleUsing: static fn (Exists $rule): Exists => $rule->where(
                    'taxonomy_id',
                    $taxonomy->getKey(),
                ),
            );
    }
}
