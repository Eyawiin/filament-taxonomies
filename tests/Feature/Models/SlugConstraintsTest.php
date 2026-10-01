<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Database\UniqueConstraintViolationException;

it('enforces taxonomy slug uniqueness in the database', function (): void {
    Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);

    expect(fn () => Taxonomy::create(['name' => 'Other topics', 'slug' => 'topics']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('enforces term slug uniqueness within a taxonomy', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    $taxonomy->terms()->create(['name' => 'Existing', 'slug' => 'shared']);

    expect(fn () => $taxonomy->terms()->create(['name' => 'Duplicate', 'slug' => 'shared']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows the same term slug in different taxonomies', function (): void {
    $first = Taxonomy::create(['name' => 'Topics', 'slug' => 'topics']);
    $second = Taxonomy::create(['name' => 'Places', 'slug' => 'places']);
    $firstTerm = $first->terms()->create(['name' => 'First', 'slug' => 'shared']);
    $secondTerm = $second->terms()->create(['name' => 'Second', 'slug' => 'shared']);

    expect($firstTerm->fresh()->slug)->toBe($secondTerm->fresh()->slug)
        ->and($firstTerm->fresh()->taxonomy_id)->not->toBe($secondTerm->fresh()->taxonomy_id);
});
