<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyIdentity;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TaxonomyParentSelect
{
    /**
     * @param  class-string<TaxonomyResource>  $resource
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
            $term = $taxonomy->terms()->findOrFail($term->getKey(), [(new TaxonomyTerm)->qualifyColumn('*')]);
        }

        $treeService = app(TaxonomyTreeService::class);
        $nodes = TaxonomyTreeOptions::flatten(
            TaxonomyIdentity::browserTree($treeService->getTree($taxonomy)),
            static function (TaxonomyTerm $candidate, array $ancestors) use ($term): string {
                if ($term === null) {
                    return '';
                }
                if ($candidate->getKey() === $term->getKey()) {
                    return __('filament-taxonomies::parent-tree.current');
                }

                return in_array((int) $term->getKey(), $ancestors, true) ? __('filament-taxonomies::parent-tree.cycle') : '';
            },
        );
        $availableIds = array_column(array_filter(
            $nodes,
            static fn (array $node): bool => ! $node['disabled'],
        ), 'id');

        return TaxonomyParentField::make('parent_id')
            ->label(__('filament-taxonomies::parent-tree.parent'))
            ->stateCast(new TaxonomyParentIdCast)
            ->nodes($nodes)
            ->nullable()
            ->rules(['integer', 'min:1', Rule::in($availableIds)])
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
