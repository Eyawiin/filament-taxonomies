<?php

use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

function verifyJoinedVisibility(): void
{
    $taxonomy = Taxonomy::create(['name' => 'Joined visibility', 'slug' => 'joined-visibility']);
    $root = $taxonomy->terms()->create(['name' => 'Root', 'slug' => 'root', 'position' => 0]);
    $source = $taxonomy->terms()->create(['name' => 'Source', 'slug' => 'source', 'parent_id' => $root->id]);
    $child = $taxonomy->terms()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $source->id]);
    $target = $taxonomy->terms()->create(['name' => 'Target', 'slug' => 'target', 'position' => 1]);
    $hidden = $taxonomy->terms()->create(['name' => 'Hidden', 'slug' => 'hidden', 'position' => 2]);
    $taxonomyScopes = Taxonomy::getAllGlobalScopes();
    $termScopes = TaxonomyTerm::getAllGlobalScopes();
    Taxonomy::addGlobalScope('permissions', function (Builder $query) use ($taxonomy): void {
        $query->crossJoin(DB::raw("(select 777 as id, 'Permission' as name, 'permission' as slug) as permissions"))
            ->crossJoin(DB::raw('(select 1 as copy union all select 2 as copy) as permission_copies'))
            ->whereKey($taxonomy->id);
    });
    TaxonomyTerm::addGlobalScope('permissions', function (Builder $query) use ($hidden, $taxonomy): void {
        $query->join('taxonomies as visible_taxonomies', 'visible_taxonomies.id', '=', 'taxonomy_terms.taxonomy_id')
            ->crossJoin(DB::raw('(select 1 as copy union all select 2 as copy) as permissions'))
            ->where('taxonomy_terms.taxonomy_id', $taxonomy->id)
            ->whereKeyNot($hidden->id);
    });

    try {
        $query = TaxonomyResource::getEloquentQuery()
            ->withCount(['terms' => TaxonomyResource::countDistinctTerms(...)]);
        checkConcurrency($query->toBase()->getCountForPagination() === 1, 'Joined taxonomy pagination must count each physical record once.');
        checkConcurrency((clone $query)->get()->count() === 1, 'Joined taxonomy rows must not duplicate list entries.');
        $record = DB::transaction(fn () => $taxonomy->resolveRouteBindingQuery($query, $taxonomy->slug, 'slug')->lockForUpdate()->firstOrFail());
        checkConcurrency($record->id === $taxonomy->id && $record->name === $taxonomy->name && (int) $record->terms_count === 4, 'Joined taxonomy records and distinct visible counts must retain their identity.');
        $service = new TaxonomyTreeService;
        $tree = $service->getTree($record);
        checkConcurrency(count($tree) === 2 && $tree[0]['term']->id === $root->id && count($tree[0]['children']) === 1, 'Joined tree rows must retain term identity and appear only once.');
        $ids = $service->getDescendantIds($root);
        sort($ids);
        checkConcurrency($ids === [$source->id, $child->id], 'Joined descendant queries must return the visible term keys.');
        checkConcurrency($service->getNextPosition($taxonomy->id, $root->id) === 1, 'Joined next-position queries must use term positions.');
        $source->name = 'Updated source';
        $service->moveRelativeTo($source, $target, TaxonomyTermDropPosition::Before);
        checkConcurrency($source->id !== $root->id && $source->parent_id === null && $source->position === 1, 'Joined managed writes must move the actual source.');
    } finally {
        Taxonomy::setAllGlobalScopes($taxonomyScopes);
        TaxonomyTerm::setAllGlobalScopes($termScopes);
    }
    checkConcurrency($root->fresh()->name === 'Root' && $source->fresh()->name === 'Updated source' && $child->fresh()->parent_id === $source->id, 'Joined writes must preserve other identities and the source subtree.');
    checkConcurrency($taxonomy->terms()->whereNull('parent_id')->orderBy('position')->pluck('id')->all() === [$root->id, $source->id, $target->id, $hidden->id], 'Joined movement must normalize all stored siblings, including hidden ones.');
    echo "PASS MySQL joined visibility: identities, distinct counts, reads and managed movement.\n";
}
