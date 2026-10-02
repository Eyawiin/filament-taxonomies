<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

function verifyImportedStructure(): void
{
    $taxonomy = Taxonomy::create(['name' => 'Imported structure', 'slug' => 'imported-structure']);
    $root = $taxonomy->terms()->create(['name' => 'Root', 'slug' => 'root']);
    $child = $taxonomy->terms()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root->id]);
    $other = Taxonomy::create(['name' => 'Foreign parent', 'slug' => 'foreign-parent']);
    $foreign = $other->terms()->create(['name' => 'Foreign', 'slug' => 'foreign']);
    $service = new TaxonomyTreeService;
    checkConcurrency($service->diagnoseTree($taxonomy) === [], 'A valid import must have no structural diagnostic.');
    foreach (['cycle' => $root->id, 'missing_or_foreign_parent' => $foreign->id, 'invalid_identity' => 0] as $reason => $parentId) {
        // Only this disposable connection relaxes FK checks for the corrupt-import fixture.
        if ($parentId === 0) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            DB::table('taxonomy_terms')->where('id', $root->id)->update(['parent_id' => $parentId]);
            $before = DB::table('taxonomy_terms')->where('taxonomy_id', $taxonomy->id)->orderBy('id')->get()->toJson();
            $scopes = TaxonomyTerm::getAllGlobalScopes();
            TaxonomyTerm::addGlobalScope('hidden-import-root', fn (Builder $query) => $query->whereKeyNot($root->id));

            try {
                checkConcurrency($service->diagnoseTree($taxonomy) === [
                    ['term_id' => $root->id, 'reason' => $reason],
                    ['term_id' => $child->id, 'reason' => $reason],
                ], 'Diagnostics must include the affected unscoped ancestor chain on MySQL.');
            } finally {
                TaxonomyTerm::setAllGlobalScopes($scopes);
            }
            checkConcurrency($service->getTree($taxonomy) === [], 'Malformed imports must not become roots in the visible tree.');
            checkConcurrency(DB::table('taxonomy_terms')->where('taxonomy_id', $taxonomy->id)->orderBy('id')->get()->toJson() === $before, 'Import diagnostics must not rewrite MySQL records.');
        } finally {
            DB::table('taxonomy_terms')->where('id', $root->id)->update(['parent_id' => null]);
            if ($parentId === 0) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }
    echo "PASS MySQL import diagnostics: cycles, foreign parents, zero-parent orphans and unchanged rows.\n";
}

function verifyUnsignedIdentities(): void
{
    $taxonomy = Taxonomy::create(['name' => 'Integer boundaries', 'slug' => 'integer-boundaries']);
    $child = $taxonomy->terms()->create(['name' => 'Child', 'slug' => 'child']);
    $native = PHP_INT_MAX;
    $outside = '9223372036854775808';
    foreach ([$native, $outside] as $id) {
        DB::table('taxonomy_terms')->insert([
            'id' => $id, 'taxonomy_id' => $taxonomy->id, 'parent_id' => null,
            'name' => 'Imported ' . $id, 'slug' => 'imported-' . $id, 'position' => 1,
        ]);
    }
    DB::table('taxonomy_terms')->where('id', $child->id)->update(['parent_id' => $outside]);
    $before = DB::table('taxonomy_terms')->orderBy('id')->get()->toJson();
    $service = new TaxonomyTreeService;
    $oversized = TaxonomyTerm::where('slug', 'imported-' . $outside)->firstOrFail();
    checkConcurrency($oversized->getRawOriginal('id') === $outside, 'Fixture must retain the unsigned physical identity: ' . var_export($oversized->getRawOriginal('id'), true));

    try {
        $service->setParent($oversized, null);

        throw new RuntimeException('Expected oversized managed identity rejection.');
    } catch (LogicException $exception) {
        checkConcurrency(str_contains($exception->getMessage(), 'identity'), 'Oversized identity must fail before resolving another record.');
    }
    checkConcurrency($service->diagnoseTree($taxonomy) === [
        ['term_id' => $child->id, 'reason' => 'invalid_identity'],
        ['term_id' => $outside, 'reason' => 'invalid_identity'],
    ], 'Unsigned diagnostics must preserve raw IDs and follow raw parent references.');
    checkConcurrency(array_column(array_column($service->getTree($taxonomy), 'term'), 'id') === [$native], 'Unsigned records must not shadow the native maximum tree node.');
    checkConcurrency($service->getDescendantIds(TaxonomyTerm::findOrFail($native)) === [], 'An unsigned parent reference must not alias the native maximum.');

    try {
        $service->setParent($child->fresh(), null);

        throw new RuntimeException('Expected oversized parent rejection.');
    } catch (InvalidTaxonomyParentException) {
    }
    checkConcurrency(DB::table('taxonomy_terms')->orderBy('id')->get()->toJson() === $before, 'Unsigned rejected mutations must leave every physical record unchanged.');

    // Exhausted insertGetId() is also guarded, even when Eloquent has already clamped the key.
    try {
        $service->createTerm($taxonomy, 'Overflow', 'overflow', TaxonomyTerm::findOrFail($native));

        throw new RuntimeException('Expected generated identity exhaustion rejection.');
    } catch (RuntimeException $exception) {
        checkConcurrency(str_contains($exception->getMessage(), 'integer range'), 'Generated unsigned identity must roll back before sibling normalization.');
    }
    checkConcurrency(DB::table('taxonomy_terms')->orderBy('id')->get()->toJson() === $before, 'Generated identity exhaustion must roll back the insert.');
    foreach ([$native, $outside] as $id) {
        DB::table('taxonomies')->insert(['id' => $id, 'name' => 'Owner ' . $id, 'slug' => 'owner-' . $id]);
    }
    $oversizedOwner = Taxonomy::where('slug', 'owner-' . $outside)->firstOrFail();

    try {
        $service->deleteTaxonomy($oversizedOwner);

        throw new RuntimeException('Expected unsigned owner rejection.');
    } catch (LogicException $exception) {
        checkConcurrency(str_contains($exception->getMessage(), 'identity'), 'Unsigned owner must fail before locking another taxonomy.');
    }
    checkConcurrency(DB::table('taxonomies')->where('slug', 'owner-' . $native)->exists()
        && DB::table('taxonomies')->where('slug', 'owner-' . $outside)->exists(), 'Both owner identities must survive rejected deletion.');
    echo "PASS MySQL unsigned identities: raw diagnostics, no aliasing, unchanged rows and exhausted insert rollback.\n";
}
