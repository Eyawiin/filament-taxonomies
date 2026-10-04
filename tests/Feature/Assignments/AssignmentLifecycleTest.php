<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyAssignmentService;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Article;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Fixture;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\SoftOwner;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

it('cascades term and taxonomy deletion to assignments only', function (): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id, $this->second->id]);
    $this->owner->attachTaxonomyTerms($this->other, [$this->foreign->id]);
    $this->second->delete();
    expect($this->owner->fresh()->taxonomyTerms->modelKeys())->toBe([$this->first->id, $this->foreign->id]);
    $this->taxonomy->delete();
    expect($this->owner->fresh()->taxonomyTerms->modelKeys())->toBe([$this->foreign->id]);
});

it('preserves soft deleted assignments and removes them on force deletion', function (): void {
    $owner = SoftOwner::create([]);
    $owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    $owner->delete();
    expect(DB::table('taxonomy_term_assignments')->count())->toBe(1);
    expect(fn () => $owner->attachTaxonomyTerms($this->taxonomy, [$this->second->id]))->toThrow(ModelNotFoundException::class);
    $owner->restore();
    expect($owner->fresh()->taxonomyTerms->modelKeys())->toBe([$this->first->id]);
    $owner->forceDelete();
    expect(DB::table('taxonomy_term_assignments')->count())->toBe(0);
});

it('does not clean assignments when deletion is cancelled', function (): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    Article::deleting(fn () => false);

    try {
        expect($this->owner->delete())->toBeFalse()
            ->and(DB::table('taxonomy_term_assignments')->count())->toBe(1);
    } finally {
        Article::flushEventListeners();
        Article::clearBootedModels();
    }
});

it('supports explicit cleanup for eventless deletion in a consumer transaction', function (): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    DB::transaction(function (): void {
        Article::whereKey($this->owner->id)->delete();
        app(TaxonomyAssignmentService::class)->forgetOwner($this->owner);
    });
    expect(DB::table('taxonomy_term_assignments')->count())->toBe(0);
});

it('rolls back owner deletion and automatic cleanup with the consumer transaction', function (): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);

    try {
        DB::transaction(function (): void {
            $this->owner->delete();

            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    expect(Article::find($this->owner->id))->not->toBeNull()
        ->and(DB::table('taxonomy_term_assignments')->count())->toBe(1);
});
