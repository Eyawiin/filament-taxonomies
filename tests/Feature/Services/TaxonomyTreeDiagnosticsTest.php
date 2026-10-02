<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

it('reports malformed ancestor chains without repairing imported rows', function (string $defect): void {
    $taxonomy = Taxonomy::create(['name' => 'Imported', 'slug' => 'imported']);
    $root = $taxonomy->terms()->create(['name' => 'Root', 'slug' => 'root']);
    $child = $taxonomy->terms()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root->id]);
    $leaf = $taxonomy->terms()->create(['name' => 'Leaf', 'slug' => 'leaf', 'parent_id' => $child->id]);
    $valid = $taxonomy->terms()->create(['name' => 'Valid', 'slug' => 'valid']);
    $foreign = null;
    if ($defect === 'foreign') {
        $other = Taxonomy::create(['name' => 'Other', 'slug' => 'other']);
        $foreign = $other->terms()->create(['name' => 'Foreign', 'slug' => 'foreign']);
    }
    if ($defect === 'missing') {
        DB::statement('PRAGMA defer_foreign_keys = ON');
    }
    DB::table('taxonomy_terms')->where('id', $root->id)->update(['parent_id' => match ($defect) {
        'self cycle' => $root->id,
        'multi-node cycle' => $leaf->id,
        'missing' => 999999,
        'foreign' => $foreign->id,
    }]);
    $before = DB::table('taxonomy_terms')->orderBy('id')->get()->toJson();
    $reason = str_contains($defect, 'cycle') ? 'cycle' : 'missing_or_foreign_parent';
    expect(app(TaxonomyTreeService::class)->diagnoseTree($taxonomy))->toBe([
        ['term_id' => $root->id, 'reason' => $reason],
        ['term_id' => $child->id, 'reason' => $reason],
        ['term_id' => $leaf->id, 'reason' => $reason],
    ])->and(DB::table('taxonomy_terms')->orderBy('id')->get()->toJson())->toBe($before)
        ->and(app(TaxonomyTreeService::class)->getTree($taxonomy)[0]['term']->id)->toBe($valid->id);
})->with(['self cycle', 'multi-node cycle', 'missing', 'foreign']);

it('diagnoses structural imports independently of visibility and includes descendants of a cycle', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Imported', 'slug' => 'imported']);
    $root = $taxonomy->terms()->create(['name' => 'Hidden root', 'slug' => 'root']);
    $child = $taxonomy->terms()->create(['name' => 'Visible child', 'slug' => 'child', 'parent_id' => $root->id]);
    $service = app(TaxonomyTreeService::class);
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('hidden', fn (Builder $query) => $query->whereKeyNot($root->id));

    try {
        expect($service->getTree($taxonomy))->toBe([])
            ->and($service->diagnoseTree($taxonomy))->toBe([]);
        DB::table('taxonomy_terms')->where('id', $root->id)->update(['parent_id' => $root->id]);
        expect($service->diagnoseTree($taxonomy))->toBe([
            ['term_id' => $root->id, 'reason' => 'cycle'],
            ['term_id' => $child->id, 'reason' => 'cycle'],
        ]);
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
});

it('reads a deep valid import once without recursive queries or rewriting legacy positions', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Deep', 'slug' => 'deep']);
    $parent = null;
    foreach (range(1, 1000) as $index) {
        $parent = DB::table('taxonomy_terms')->insertGetId([
            'taxonomy_id' => $taxonomy->id, 'parent_id' => $parent,
            'name' => 'Term ' . $index, 'slug' => 'term-' . $index, 'position' => 7,
        ]);
    }
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        expect(app(TaxonomyTreeService::class)->diagnoseTree($taxonomy))->toBe([])
            ->and(DB::getQueryLog())->toHaveCount(1);
    } finally {
        DB::disableQueryLog();
    }
    expect($taxonomy->terms()->where('position', 7)->count())->toBe(1000);
});

it('does not treat nonpositive imported parents or term identities as root markers', function (bool $existingParent): void {
    $taxonomy = Taxonomy::create(['name' => 'Imported', 'slug' => 'imported']);
    if ($existingParent) {
        DB::table('taxonomy_terms')->insert([
            'id' => 0, 'taxonomy_id' => $taxonomy->id, 'parent_id' => null,
            'name' => 'Invalid identity', 'slug' => 'invalid', 'position' => 0,
        ]);
    } else {
        DB::statement('PRAGMA defer_foreign_keys = ON');
    }
    $child = $taxonomy->terms()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => 0]);
    $before = DB::table('taxonomy_terms')->orderBy('id')->get()->toJson();
    $service = app(TaxonomyTreeService::class);
    expect($service->getTree($taxonomy))->toBe([]);
    $expected = $existingParent ? [['term_id' => 0, 'reason' => 'invalid_identity']] : [];
    $expected[] = ['term_id' => $child->id, 'reason' => 'invalid_identity'];
    expect($service->diagnoseTree($taxonomy))->toBe($expected);
    if ($existingParent) {
        $invalid = TaxonomyTerm::withoutGlobalScopes()->findOrFail(0);
        expect(fn () => $service->setParent($invalid, null))->toThrow(LogicException::class);
    }
    expect(fn () => $service->setParent($child, null))->toThrow(InvalidTaxonomyParentException::class);
    expect(DB::table('taxonomy_terms')->orderBy('id')->get()->toJson())->toBe($before);
})->with(['missing zero parent' => false, 'stored zero identity' => true]);
