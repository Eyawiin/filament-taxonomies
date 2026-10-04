<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Article;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Fixture;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\UlidOwner;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\UuidOwner;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Video;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    Fixture::createSchema();
    $this->owner = Article::create(['name' => 'First']);
    $this->taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    $this->first = $this->taxonomy->terms()->create(['name' => 'First', 'slug' => 'first']);
    $this->second = $this->taxonomy->terms()->create(['name' => 'Second', 'slug' => 'second', 'parent_id' => $this->first->id]);
    $this->other = Taxonomy::create(['name' => 'Other', 'slug' => 'other']);
    $this->foreign = $this->other->terms()->create(['name' => 'Foreign', 'slug' => 'foreign']);
});

it('attaches explicitly and idempotently without selecting relatives', function (): void {
    $this->owner->attachTaxonomyTerms('topics', [$this->second->id]);
    $this->owner->load('taxonomyTerms');
    $this->owner->attachTaxonomyTerms($this->taxonomy->id, [(string) $this->second->id]);
    expect($this->owner->relationLoaded('taxonomyTerms'))->toBeFalse()
        ->and($this->owner->taxonomyTerms->modelKeys())->toBe([$this->second->id])
        ->and(DB::table('taxonomy_term_assignments')->count())->toBe(1);
});

it('syncs and clears one taxonomy while preserving every other taxonomy', function (): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id, $this->second->id]);
    $this->owner->attachTaxonomyTerms($this->other, [$this->foreign->id]);
    $this->owner->syncTaxonomyTerms('topics', [$this->second->id]);
    expect($this->owner->termsForTaxonomy($this->taxonomy)->pluck('taxonomy_terms.id')->all())->toBe([$this->second->id]);
    $this->owner->syncTaxonomyTerms($this->taxonomy, []);
    expect($this->owner->taxonomyTerms->modelKeys())->toBe([$this->foreign->id]);
});

it('detaches only requested terms and accepts an empty selection', function (): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id, $this->second->id]);
    $this->owner->detachTaxonomyTerms($this->taxonomy, []);
    $this->owner->detachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    $this->owner->detachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    expect($this->owner->taxonomyTerms->modelKeys())->toBe([$this->second->id]);
});

it('separates different owner types with the same numeric ID and uses enforced morph aliases', function (): void {
    $map = Relation::morphMap();
    $required = Relation::requiresMorphMap();

    try {
        Relation::enforceMorphMap(['article' => Article::class, 'video' => Video::class], false);
        $video = Video::create(['id' => $this->owner->id]);
        $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
        $video->attachTaxonomyTerms($this->taxonomy, [$this->second->id]);
        expect($this->owner->taxonomyTerms->modelKeys())->toBe([$this->first->id])
            ->and($video->taxonomyTerms->modelKeys())->toBe([$this->second->id])
            ->and(DB::table('taxonomy_term_assignments')->pluck('assignable_type')->sort()->values()->all())->toBe(['article', 'video']);
        $this->owner->delete();
        expect($video->fresh()->taxonomyTerms->modelKeys())->toBe([$this->second->id]);
    } finally {
        Relation::morphMap($map, false);
        Relation::requireMorphMap($required);
    }
});

it('supports mixed UUID and ULID owner keys and native eager loading and term queries', function (): void {
    $uuid = UuidOwner::create(['id' => '21e0d449-98d5-4a07-a3ce-c881f0065e49']);
    $ulid = UlidOwner::create(['id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']);
    foreach ([$uuid, $ulid, $this->owner] as $owner) {
        $owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
        expect($owner->fresh()->taxonomyTerms->modelKeys())->toBe([$this->first->id]);
    }
    expect(UuidOwner::with('taxonomyTerms')->first()->taxonomyTerms->modelKeys())->toBe([$this->first->id])
        ->and(Article::whereHas('taxonomyTerms', fn ($query) => $query->where('taxonomy_terms.id', $this->first->id))->count())->toBe(1);
});

it('resolves numeric strings as slugs rather than IDs', function (): void {
    $numeric = Taxonomy::create(['name' => 'Numeric slug', 'slug' => (string) $this->taxonomy->id]);
    $term = $numeric->terms()->create(['name' => 'Numeric', 'slug' => 'numeric']);
    $this->owner->attachTaxonomyTerms((string) $this->taxonomy->id, [$term->id]);
    expect($this->owner->termsForTaxonomy((string) $this->taxonomy->id)->pluck('taxonomy_terms.id')->all())->toBe([$term->id]);
});

it('enforces pivot uniqueness even for raw duplicate inserts', function (): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    DB::table('taxonomy_term_assignments')->insert((array) DB::table('taxonomy_term_assignments')->first());
})->throws(UniqueConstraintViolationException::class);

it('accepts Laravel-generated UUID and ULID identities without changing their representation', function (string $class): void {
    $owner = $class::create([]);
    $key = $owner->getRawOriginal('id');
    $owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    expect($owner->fresh()->taxonomyTerms->modelKeys())->toBe([$this->first->id])
        ->and(DB::table('taxonomy_term_assignments')->value('assignable_id'))->toBe($key);
})->with(['uuid' => [UuidOwner::class], 'ulid' => [UlidOwner::class]]);
