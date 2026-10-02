<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

$app = require __DIR__ . '/bootstrap.php';
@mkdir(dirname(__DIR__) . '/build', 0755, true);
touch(dirname(__DIR__) . '/build/browser.sqlite');
if (! Schema::hasTable('taxonomies')) {
    foreach (glob(dirname(__DIR__) . '/database/migrations/*.php') as $migration) {
        (require $migration)->up();
    }
}
Taxonomy::unguard();
$taxonomy = Taxonomy::firstOrCreate(['id' => 1], ['name' => 'Browser topics', 'slug' => 'browser-topics']);
foreach ([
    [1, 'Country', null],
    [2, 'Italy', 1],
    [3, 'Rome', 2],
    [4, 'France', 1],
    [5, '<img src=x onerror=alert(1)>', null],
] as [$id, $name, $parent]) {
    $taxonomy->terms()->updateOrCreate(['id' => $id], ['name' => $name, 'slug' => 'term-' . $id, 'parent_id' => $parent, 'position' => $id]);
}
$app->make(Kernel::class)->call('filament:assets');
echo "Browser fixture and published assets ready.\n";
