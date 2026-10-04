<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyAssignmentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Article;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Fixture;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\UlidOwner;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\UuidOwner;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Video;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

function verifyAssignments(PDO $admin, string $database): void
{
    Fixture::createSchema();
    $owner = Article::create([]);
    $taxonomy = Taxonomy::create(['name' => 'Assignments', 'slug' => 'assignments']);
    $a = $taxonomy->terms()->create(['name' => 'A', 'slug' => 'a']);
    $b = $taxonomy->terms()->create(['name' => 'B', 'slug' => 'b']);
    $other = Taxonomy::create(['name' => 'Preserved', 'slug' => 'assignment-preserved']);
    $foreign = $other->terms()->create(['name' => 'Foreign', 'slug' => 'foreign']);
    $owner->attachTaxonomyTerms($other, [$foreign->id]);
    $args = ['taxonomy' => $taxonomy->id, 'owner' => $owner->id];
    [$first, $second] = competingWrites(
        $admin,
        $database,
        ['assign-attach', $args + ['terms' => [$a->id]]],
        ['assign-attach', $args + ['terms' => [$a->id]]],
        snapshot: true
    );
    checkConcurrency($first['ok'] && $second['ok'] && $owner->termsForTaxonomy($taxonomy)->count() === 1, 'Concurrent attachment must be idempotent under an old snapshot.');
    echo "PASS assignment attach is idempotent under an old RR snapshot (observed lock wait)\n";
    [$first, $second] = competingWrites(
        $admin,
        $database,
        ['assign-sync', $args + ['terms' => [$a->id]]],
        ['assign-sync', $args + ['terms' => [$b->id]]],
        snapshot: true
    );
    checkConcurrency($first['ok'] && $second['ok'] && $owner->termsForTaxonomy($taxonomy)->pluck('taxonomy_terms.id')->all() === [$b->id], 'Later serialized sync must replace the current selection.');
    checkConcurrency($owner->termsForTaxonomy($other)->pluck('taxonomy_terms.id')->all() === [$foreign->id], 'Sync must preserve assignments in other taxonomies.');
    echo "PASS conflicting assignment sync preserves other taxonomies (observed lock wait)\n";
    [$first, $second] = competingWrites(
        $admin,
        $database,
        ['delete-term', $args + ['source' => $a->id]],
        ['assign-attach', $args + ['terms' => [$a->id]]],
        snapshot: true
    );
    checkConcurrency($first['ok'] && ! $second['ok'] && $second['exception'] === InvalidTaxonomyAssignmentException::class, 'Deleted terms must be rechecked after waiting.');
    checkConcurrency($owner->termsForTaxonomy($taxonomy)->pluck('taxonomy_terms.id')->all() === [$b->id], 'Rejected stale attachment must preserve current assignments.');
    echo "PASS stale assignment rejects a deleted term (observed lock wait)\n";
    $deleting = Article::create([]);
    $deleting->attachTaxonomyTerms($taxonomy, [$b->id]);
    [$first, $second] = competingWrites(
        $admin,
        $database,
        ['delete-owner', ['taxonomy' => $taxonomy->id, 'owner' => $deleting->id]],
        ['assign-attach', ['taxonomy' => $taxonomy->id, 'owner' => $deleting->id, 'terms' => [$b->id]]],
        snapshot: true
    );
    checkConcurrency($first['ok'] && ! $second['ok'] && $second['exception'] === ModelNotFoundException::class, 'Removed owners must be rechecked after waiting.');
    checkConcurrency(DB::table('taxonomy_term_assignments')->where('assignable_type', $deleting->getMorphClass())->where('assignable_id', $deleting->id)->count() === 0, 'Owner deletion must not leave or resurrect assignments.');
    echo "PASS stale assignment rejects a deleted owner (observed lock wait)\n";
    $scopes = TaxonomyTerm::getAllGlobalScopes();
    TaxonomyTerm::addGlobalScope('assignment-join', fn ($query) => $query
        ->join('taxonomies', 'taxonomies.id', '=', 'taxonomy_terms.taxonomy_id')
        ->crossJoin(DB::raw('(select 1 as copy union all select 2 as copy) as assignment_copies')));

    try {
        checkConcurrency($owner->termsForTaxonomy($taxonomy)->count() === 1 && $owner->termsForTaxonomy($taxonomy)->get()->modelKeys() === [$b->id], 'Joined assignment reads and counts must deduplicate terms.');
    } finally {
        TaxonomyTerm::setAllGlobalScopes($scopes);
    }
    foreach ([UuidOwner::create([]), UlidOwner::create([])] as $stringOwner) {
        $stringOwner->attachTaxonomyTerms($taxonomy, [$b->id]);
        checkConcurrency($stringOwner->fresh()->taxonomyTerms->modelKeys() === [$b->id], 'MySQL string owner keys must round-trip through the actual pivot.');
        DB::transaction(fn () => $stringOwner->delete());
    }
    // Morph types remain case-sensitive even on a case-insensitive consumer database.
    $video = Video::create(['id' => $owner->id]);
    DB::table('taxonomy_term_assignments')->insert([
        ['assignable_type' => 'Owner', 'assignable_id' => (string) $owner->id, 'taxonomy_term_id' => $b->id],
        ['assignable_type' => 'owner', 'assignable_id' => (string) $owner->id, 'taxonomy_term_id' => $b->id],
    ]);
    checkConcurrency(DB::table('taxonomy_term_assignments')->where('assignable_type', 'Owner')->count() === 1, 'Morph aliases must have case-sensitive identities.');
    $video->attachTaxonomyTerms($taxonomy, [$b->id]);
    DB::transaction(fn () => $owner->delete());
    checkConcurrency($video->fresh()->taxonomyTerms->modelKeys() === [$b->id], 'Owner cleanup must preserve another owner type.');
    echo "PASS MySQL assignment identity and owner cleanup constraints\n";
}
