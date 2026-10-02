<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

try {
    $app = require __DIR__ . '/bootstrap.php';
    Filament::setCurrentPanel('admin');
    $fixtures = json_decode(file_get_contents(dirname(__DIR__) . '/build/performance-fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
    $report = ['environment' => ['php' => PHP_VERSION, 'laravel' => app()->version(), 'database' => DB::selectOne('select sqlite_version() as version')->version, 'xdebug_mode' => getenv('XDEBUG_MODE')], 'fixtures' => []];
    $service = app(TaxonomyTreeService::class);
    foreach ($fixtures as $slug => $fixture) {
        $taxonomy = Taxonomy::findOrFail($fixture['taxonomy_id']);
        $term = TaxonomyTerm::findOrFail($fixture['first_id']);
        foreach ([
            'tree' => fn () => $service->getTree($taxonomy),
            'descendants' => fn () => $service->getDescendantIds($term),
            'diagnostics' => fn () => $service->diagnoseTree($taxonomy),
            'parent_configuration' => fn () => TaxonomyParentSelect::make($taxonomy, $term)->container(Schema::make())->getTreeConfiguration(),
            'sidebar' => fn () => array_map(fn ($item) => [$item->getLabel(), $item->getBadge(), $item->getUrl()], TaxonomyResource::getNavigationItems()),
        ] as $operation => $run) {
            $samples = [];
            for ($iteration = 0; $iteration < 4; $iteration++) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $start = hrtime(true);
                $result = $run();
                $elapsed = (hrtime(true) - $start) / 1e6;
                $queries = count(DB::getQueryLog());
                DB::disableQueryLog();
                if ($iteration > 0) {
                    $samples[] = ['ms' => $elapsed, 'queries' => $queries, 'bytes' => strlen(json_encode($result, JSON_THROW_ON_ERROR))];
                }
            }
            $report['fixtures'][$slug][$operation] = $samples;
        }
    }
    $label = $argv[1] ?? 'current';
    if (! preg_match('/^[a-z0-9-]+$/', $label)) {
        throw new RuntimeException('Invalid report label.');
    }
    file_put_contents(dirname(__DIR__) . '/build/performance-' . $label . '-server.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, (string) $exception . PHP_EOL);
    exit(1);
}
