<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Filament\Forms\Components\Select;
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
    ): Select {
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
        $options = array_column($nodes, 'name', 'id');

        return Select::make('parent_id')
            ->label('Parent')
            ->stateCast(new TaxonomyParentIdCast)
            ->view('filament-taxonomies::forms.parent-tree-select')
            ->viewData(['nodes' => $nodes])
            ->options($options)
            ->disableOptionWhen(
                static fn ($value): bool => in_array((int) $value, $disabledIds, true),
            )
            ->optionsLimit(max(50, count($options)))
            ->searchable()
            ->native(false)
            ->nullable()
            ->rules(['integer', 'min:1'])
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
                    ? 'Current term'
                    : ($disabled ? 'Would create a cycle' : ''),
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
