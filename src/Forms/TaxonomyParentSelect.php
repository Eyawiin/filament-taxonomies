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
        $nodes = self::treeNodes(
            TaxonomyIdentity::browserTree($treeService->getTree($taxonomy)),
            $term === null ? null : (int) $term->getKey(),
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

    /**
     * @param  array<array{term: TaxonomyTerm, children: array}>  $nodes
     * @param  list<int>  $ancestors
     * @return list<array{id: int, name: string, ancestors: list<int>, hasChildren: bool, disabled: bool, reason: string}>
     */
    private static function treeNodes(
        array $nodes,
        ?int $currentTermId,
        array $ancestors = [],
    ): array {
        $options = [];

        foreach ($nodes as $node) {
            $id = (int) $node['term']->getKey();
            $disabled = $currentTermId !== null && ($id === $currentTermId || in_array($currentTermId, $ancestors, true));
            $options[] = [
                'id' => $id,
                'name' => $node['term']->name,
                'ancestors' => $ancestors,
                'hasChildren' => $node['children'] !== [],
                'disabled' => $disabled,
                'reason' => $id === $currentTermId
                    ? __('filament-taxonomies::parent-tree.current')
                    : ($disabled ? __('filament-taxonomies::parent-tree.cycle') : ''),
            ];
            array_push($options, ...self::treeNodes(
                $node['children'],
                $currentTermId,
                [...$ancestors, $id],
            ));
        }

        return $options;
    }
}
