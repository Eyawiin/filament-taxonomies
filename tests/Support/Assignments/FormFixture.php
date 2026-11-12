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
    /** @return array{themes: Taxonomy, brands: Taxonomy, liloAndStitch: TaxonomyTerm, wolves: TaxonomyTerm, riderBackRed: TaxonomyTerm} */
    public static function create(): array
    {
        Filament::setCurrentPanel('admin');
        if (! Schema::hasTable('decks')) {
            (require dirname(__DIR__, 3) . '/workbench/database/migrations/2026_10_04_000000_create_decks_table.php')->up();
        }
        app(DemoSeeder::class)->run();
        $themes = Taxonomy::where('slug', 'themes')->firstOrFail();
        $brands = Taxonomy::where('slug', 'brands')->firstOrFail();

        return ['themes' => $themes, 'brands' => $brands,
            'liloAndStitch' => $themes->terms()->where('slug', 'lilo-and-stitch')->firstOrFail(),
            'wolves' => $themes->terms()->where('slug', 'wolves')->firstOrFail(),
            'riderBackRed' => $brands->terms()->where('slug', 'bicycle-rider-back-red')->firstOrFail()];
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
