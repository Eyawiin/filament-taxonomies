<?php

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    @mkdir(dirname(__DIR__) . '/build', 0755, true);
    touch(dirname(__DIR__) . '/build/performance.sqlite');
    $app = require __DIR__ . '/bootstrap.php';
    if (! Schema::hasTable('taxonomies')) {
        foreach (glob(dirname(__DIR__) . '/database/migrations/*.php') as $migration) {
            (require $migration)->up();
        }
    }
    $fixtures = [];
    DB::transaction(function () use (&$fixtures): void {
        DB::table('taxonomy_terms')->delete();
        DB::table('taxonomies')->delete();
        foreach (['broad-100' => [100, 'broad'], 'broad-1000' => [1000, 'broad'], 'mixed-100-depth4' => [100, 'mixed'], 'deep-32' => [32, 'deep']] as $slug => [$size, $shape]) {
            $taxonomy = Taxonomy::create(['name' => $slug, 'slug' => $slug]);
            $ids = [];
            $depths = [];
            for ($index = 0; $index < $size; $index++) {
                $parentIndex = $shape === 'broad' ? intdiv($index - 10, 10) : $index - 1;
                $root = match ($shape) {
                    'broad' => $index < 10,
                    'mixed' => $index % 4 === 0,
                    default => $index === 0,
                };
                $parent = $root ? null : $ids[$parentIndex];
                $depths[$index] = $parent === null ? 1 : $depths[$parentIndex] + 1;
                $ids[] = DB::table('taxonomy_terms')->insertGetId([
                    'taxonomy_id' => $taxonomy->id, 'parent_id' => $parent,
                    'name' => sprintf('Term %04d', $index), 'slug' => 'term-' . $index,
                    'position' => $shape === 'broad' ? $index % 10 : ($root ? $index : 0),
                ]);
            }
            $fixtures[$slug] = [
                'taxonomy_id' => $taxonomy->id, 'size' => $size, 'depth' => max($depths),
                'first_id' => $ids[0], 'last_id' => $ids[$size - 1], 'last_depth' => $depths[$size - 1],
                'last_name' => sprintf('Term %04d', $size - 1),
            ];
        }
        foreach (range(1, 100) as $index) {
            Taxonomy::create(['name' => 'Sidebar ' . $index, 'slug' => 'sidebar-' . $index]);
        }
    });
    file_put_contents(dirname(__DIR__) . '/build/performance-fixtures.json', json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $app->make(Kernel::class)->call('filament:assets');
    echo "Isolated performance fixtures ready.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, (string) $exception . PHP_EOL);
    exit(1);
}
