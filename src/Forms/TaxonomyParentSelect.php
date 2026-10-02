<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
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
        /** @var Taxonomy $taxonomy */
        $taxonomy = $resource::getEloquentQuery()->whereKey($taxonomy->getKey())->firstOrFail();
        abort_unless($resource::canAccess() && $resource::canView($taxonomy), 403);

        if ($term !== null) {
            $term = $taxonomy->terms()->findOrFail($term->getKey());
        }

        $treeService = app(TaxonomyTreeService::class);
        $disabledIds = $term === null ? [] : [
            (int) $term->getKey(),
            ...$treeService->getDescendantIds($term),
        ];
        $nodes = self::treeNodes(
            $treeService->getTree($taxonomy),
            $disabledIds,
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
     * @param  list<int>  $disabledIds
     * @param  list<int>  $ancestors
     * @return list<array{id: int, name: string, ancestors: list<int>, hasChildren: bool, disabled: bool, reason: string}>
     */
    private static function treeNodes(
        array $nodes,
        array $disabledIds,
        ?int $currentTermId,
        array $ancestors = [],
    ): array {
        $options = [];

        foreach ($nodes as $node) {
            $id = (int) $node['term']->getKey();
            $disabled = in_array($id, $disabledIds, true);
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
                $disabledIds,
                $currentTermId,
                [...$ancestors, $id],
            ));
        }

        return $options;
    }
}
