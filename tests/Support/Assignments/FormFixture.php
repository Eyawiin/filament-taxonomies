<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support\Assignments;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Workbench\Database\Seeders\DemoSeeder;

class FormFixture
{
    /** @return array{topics: Taxonomy, levels: Taxonomy, grammar: TaxonomyTerm, algebra: TaxonomyTerm, a1: TaxonomyTerm} */
    public static function create(): array
    {
        Filament::setCurrentPanel('admin');
        if (! Schema::hasTable('decks')) {
            (require dirname(__DIR__, 3) . '/workbench/database/migrations/2026_10_04_000000_create_decks_table.php')->up();
        }
        app(DemoSeeder::class)->run();
        $topics = Taxonomy::where('slug', 'demo-topics')->firstOrFail();
        $levels = Taxonomy::where('slug', 'demo-levels')->firstOrFail();

        return ['topics' => $topics, 'levels' => $levels,
            'grammar' => $topics->terms()->where('slug', 'grammar')->firstOrFail(),
            'algebra' => $topics->terms()->where('slug', 'algebra')->firstOrFail(),
            'a1' => $levels->terms()->where('slug', 'a1')->firstOrFail()];
    }

    public static function createCollectionSchema(): void
    {
        Schema::create('assignment_collections', function (Blueprint $table): void {
            $table->id();
            $table->json('rows')->nullable();
        });
        Schema::table('decks', fn (Blueprint $table) => $table->unsignedBigInteger('collection_id')->nullable());
        app('view')->addNamespace('assignment-test', dirname(__DIR__) . '/views');
    }
}
