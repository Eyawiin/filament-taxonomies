<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyAssignmentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Article;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\Fixture;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\UuidOwner;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
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

it('rejects invalid term IDs atomically', function ($bad): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    expect(fn () => $this->owner->syncTaxonomyTerms($this->taxonomy, [$this->second->id, $bad]))->toThrow(InvalidTaxonomyAssignmentException::class);
    expect($this->owner->taxonomyTerms->modelKeys())->toBe([$this->first->id]);
})->with([true, false, 0, -1, 1.5, '1x', '9223372036854775808', null, [[]]]);

it('rejects duplicate foreign deleted or hidden terms without changing assignments', function (string $case): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    $ids = match ($case) {
        'duplicate' => [$this->second->id, (string) $this->second->id],
        'foreign' => [$this->foreign->id],
        default => [$this->second->id],
    };
    if ($case === 'deleted') {
        $this->second->delete();
    }
    if ($case === 'hidden') {
        TaxonomyTerm::addGlobalScope('assignment-hidden', fn ($query) => $query->where('taxonomy_terms.id', '!=', $this->second->id));
    }

    try {
        expect(fn () => $this->owner->syncTaxonomyTerms($this->taxonomy, $ids))->toThrow(InvalidTaxonomyAssignmentException::class);
        expect($this->owner->taxonomyTerms->modelKeys())->toBe([$this->first->id]);
    } finally {
        TaxonomyTerm::clearBootedModels();
    }
})->with(['duplicate', 'foreign', 'deleted', 'hidden']);

it('refuses to silently remove an existing hidden assignment during sync', function (): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    TaxonomyTerm::addGlobalScope('hidden', fn ($query) => $query->where('taxonomy_terms.id', '!=', $this->first->id));

    try {
        expect(fn () => $this->owner->syncTaxonomyTerms($this->taxonomy, [$this->second->id]))->toThrow(InvalidTaxonomyAssignmentException::class);
        $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->second->id]);
        expect(DB::table('taxonomy_term_assignments')->count())->toBe(2);
    } finally {
        TaxonomyTerm::clearBootedModels();
    }
});

it('rejects owners with unsaved changed missing or scoped identities', function (string $case): void {
    $owner = $this->owner;
    if ($case === 'new') {
        $owner = new Article;
    }
    if ($case === 'changed') {
        $owner->id = 55;
    }
    if ($case === 'missing') {
        Article::whereKey($owner->id)->delete();
    }
    if ($case === 'scoped') {
        Article::addGlobalScope('hidden', fn ($query) => $query->whereRaw('1 = 0'));
    }

    try {
        expect(fn () => $owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]))->toThrow(in_array($case, ['missing', 'scoped']) ? ModelNotFoundException::class : InvalidTaxonomyAssignmentException::class);
        expect(DB::table('taxonomy_term_assignments')->count())->toBe(0);
    } finally {
        Article::clearBootedModels();
    }
})->with(['new', 'changed', 'missing', 'scoped']);

it('rejects unsupported string keys and a separate connection', function (): void {
    $owner = UuidOwner::create(['id' => 'arbitrary-key']);
    expect(fn () => $owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]))->toThrow(InvalidTaxonomyAssignmentException::class);
    config(['database.connections.assignment_other' => config('database.connections.testing')]);
    $this->owner->setConnection('assignment_other');
    expect(fn () => $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]))->toThrow(InvalidTaxonomyAssignmentException::class);
    expect(DB::table('taxonomy_term_assignments')->count())->toBe(0);
});

it('rejects changed missing and scoped taxonomies', function (string $case): void {
    if ($case === 'changed') {
        $this->taxonomy->id = 55;
    }
    if ($case === 'missing') {
        Taxonomy::whereKey($this->taxonomy->id)->delete();
    }
    if ($case === 'scoped') {
        Taxonomy::addGlobalScope('hidden', fn ($query) => $query->whereRaw('1 = 0'));
    }

    try {
        expect(fn () => $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]))->toThrow($case === 'changed' ? InvalidTaxonomyAssignmentException::class : ModelNotFoundException::class);
        expect(DB::table('taxonomy_term_assignments')->count())->toBe(0);
    } finally {
        Taxonomy::clearBootedModels();
    }
})->with(['changed', 'missing', 'scoped']);

it('rolls back deletion when insertion fails during replacement', function (): void {
    $this->owner->attachTaxonomyTerms($this->taxonomy, [$this->first->id]);
    DB::unprepared("CREATE TRIGGER reject_assignment BEFORE INSERT ON taxonomy_term_assignments BEGIN SELECT RAISE(ABORT, 'forced insertion failure'); END");
    expect(fn () => $this->owner->syncTaxonomyTerms($this->taxonomy, [$this->second->id]))->toThrow(QueryException::class);
    expect($this->owner->taxonomyTerms->modelKeys())->toBe([$this->first->id]);
});

it('rejects malformed taxonomy references before PHP can coerce them', function ($value): void {
    expect(fn () => $this->owner->attachTaxonomyTerms($value, [$this->first->id]))->toThrow(InvalidTaxonomyAssignmentException::class);
    expect(DB::table('taxonomy_term_assignments')->count())->toBe(0);
})->with([true, false, 1.5, 0, -1, null, [[]], '']);

it('preserves identities and deduplicates assigned terms under joined visibility scopes', function (): void {
    Taxonomy::addGlobalScope('joined', function ($query): void {
        $query->crossJoin(DB::raw("(select 991 as id, 'Imposter' as name, 'imposter' as slug) as taxonomy_permission"));
    });
    Article::addGlobalScope('joined', fn ($query) => $query->crossJoin(DB::raw("(select 992 as id, 'Imposter' as name) as owner_permission")));
    TaxonomyTerm::addGlobalScope('joined', function ($query): void {
        $query->join('taxonomies', 'taxonomies.id', '=', 'taxonomy_terms.taxonomy_id')
            ->crossJoin(DB::raw('(select 1 as copy union all select 2 as copy) as term_permissions'));
    });

    try {
        $this->owner->attachTaxonomyTerms('topics', [$this->first->id, $this->second->id]);
        $this->owner->syncTaxonomyTerms($this->taxonomy, [$this->second->id]);
        expect($this->owner->termsForTaxonomy('topics')->get()->modelKeys())->toBe([$this->second->id]);
        expect($this->owner->taxonomyTerms->first()->name)->toBe('Second')
            ->and($this->owner->termsForTaxonomy('topics')->count())->toBe(1)
            ->and(Article::with('taxonomyTerms')->first(['assignment_articles.*'])->taxonomyTerms->modelKeys())->toBe([$this->second->id]);
    } finally {
        Taxonomy::clearBootedModels();
        TaxonomyTerm::clearBootedModels();
        Article::clearBootedModels();
    }
});

it('rechecks taxonomy visibility after reference resolution', function (): void {
    $hidden = false;
    DB::listen(function ($event) use (&$hidden): void {
        if (! $hidden && str_contains($event->sql, 'from "taxonomies"')) {
            $hidden = true;
            Taxonomy::addGlobalScope('late-hidden', fn ($query) => $query->whereRaw('1 = 0'));
        }
    });

    try {
        expect(fn () => $this->owner->attachTaxonomyTerms('topics', [$this->first->id]))->toThrow(ModelNotFoundException::class);
        expect(DB::table('taxonomy_term_assignments')->count())->toBe(0);
    } finally {
        Taxonomy::clearBootedModels();
    }
});
