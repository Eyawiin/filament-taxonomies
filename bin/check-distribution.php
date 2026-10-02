<?php

use App\Providers\ConsumerPanelProvider;
use Symfony\Component\Process\Process;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$directory = $root . '/build/distribution-' . bin2hex(random_bytes(4));
mkdir($directory, 0755, true);

function runDistribution(array $command, string $directory, ?array $env = null): string
{
    $process = new Process($command, $directory, $env, timeout: 300);
    $process->mustRun(fn ($type, $buffer) => print $buffer);

    return trim($process->getOutput());
}

$revision = in_array('--staged', $argv, true)
    ? runDistribution(['git', 'write-tree'], $root)
    : 'HEAD';
$archive = $directory . '/package.zip';
runDistribution(['git', 'archive', '--worktree-attributes', '--format=zip', '--output=' . $archive, $revision], $root);
$composerArchive = $directory . '/composer-package.zip';
runDistribution(['composer', 'archive', '--format=zip', '--dir=' . $directory, '--file=composer-package', '--no-interaction'], $root);
foreach ([$archive, $composerArchive] as $candidate) {
    $zip = new ZipArchive;
    if ($zip->open($candidate) !== true) {
        throw new RuntimeException("Cannot open the distribution archive: {$candidate}");
    }
    foreach ([
        'composer.json', 'config/filament-taxonomies.php',
        'database/migrations/0001_create_taxonomies_table.php',
        'database/migrations/0002_create_taxonomy_terms_table.php',
        'database/migrations/0003_add_position_to_taxonomy_terms_table.php',
        'resources/views/forms/parent-tree-select.blade.php',
        'resources/views/forms/parent-tree-branch.blade.php',
        'resources/views/components/taxonomy-tree.blade.php',
        'resources/lang/en/parent-tree.php', 'resources/css/taxonomy-controls.css',
        'resources/dist/components/taxonomy-tree.js', 'resources/dist/components/taxonomy-parent-tree.js',
    ] as $required) {
        if ($zip->locateName($required) === false) {
            throw new RuntimeException("Missing runtime distribution file: {$required}");
        }
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        if (preg_match('~^(?:\.github/|workbench/|tests(?:[-/])|bin/|build/|node_modules/|vendor/|\.phpunit(?:[./])|coverage/|playwright-report/|test-results/|stubs/|package(?:-lock)?\.json$|playwright|vite\.config|PROJECT_CONTEXT\.md$|ROADMAP\.md$)~', $name)) {
            throw new RuntimeException("Development file leaked into distribution: {$name}");
        }
    }
    $zip->close();
}
$zip->open($archive);
$package = $directory . '/package';
mkdir($package);
$zip->extractTo($package);
$zip->close();

$consumer = $directory . '/consumer';
runDistribution(['composer', 'create-project', 'laravel/laravel', $consumer, '^13.0', '--no-install', '--no-scripts', '--no-interaction', '--prefer-dist'], $root);
$manifest = json_decode(file_get_contents($consumer . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$manifest['repositories'] = [[
    'type' => 'path', 'url' => $package,
    'options' => ['symlink' => false, 'versions' => ['eyawiin/filament-taxonomies' => '5.x-dev']],
]];
$manifest['require']['eyawiin/filament-taxonomies'] = '5.x-dev';
file_put_contents($consumer . '/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
copy($consumer . '/.env.example', $consumer . '/.env');
file_put_contents($consumer . '/.env', "\nDB_CONNECTION=sqlite\nDB_DATABASE={$consumer}/database/database.sqlite\nSESSION_DRIVER=file\nCACHE_STORE=array\n", FILE_APPEND);
touch($consumer . '/database/database.sqlite');
runDistribution(['composer', 'update', '--no-dev', '--no-interaction', '--prefer-dist'], $consumer);
mkdir($consumer . '/app/Policies', 0755, true);
copy($root . '/tests-distribution/ConsumerTermPolicy.php', $consumer . '/app/Policies/ConsumerTermPolicy.php');
copy($root . '/tests-distribution/ConsumerPanelProvider.php', $consumer . '/app/Providers/ConsumerPanelProvider.php');
$providers = require $consumer . '/bootstrap/providers.php';
$providers[] = ConsumerPanelProvider::class;
file_put_contents($consumer . '/bootstrap/providers.php', '<?php return ' . var_export($providers, true) . ';');
file_put_contents($consumer . '/app/Providers/AppServiceProvider.php', <<<'PHP'
<?php

namespace App\Providers;

use App\Policies\ConsumerTermPolicy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(TaxonomyTerm::class, ConsumerTermPolicy::class);
    }
}
PHP);
runDistribution([PHP_BINARY, 'artisan', 'key:generate', '--no-interaction'], $consumer);
runDistribution([PHP_BINARY, 'artisan', 'filament-taxonomies:install', '--no-interaction'], $consumer);
runDistribution([PHP_BINARY, 'artisan', 'migrate', '--force'], $consumer);
runDistribution([PHP_BINARY, 'artisan', 'vendor:publish', '--tag=filament-taxonomies-views', '--no-interaction'], $consumer);
runDistribution([PHP_BINARY, 'artisan', 'filament:assets'], $consumer);
foreach (['config/filament-taxonomies.php', 'public/css/eyawiin/filament-taxonomies/taxonomy-controls.css', 'public/js/eyawiin/filament-taxonomies/components/taxonomy-parent-tree.js', 'public/js/eyawiin/filament-taxonomies/components/taxonomy-tree.js'] as $published) {
    if (! is_file($consumer . '/' . $published)) {
        throw new RuntimeException("Missing published consumer file: {$published}");
    }
}
runDistribution([PHP_BINARY, $root . '/tests-distribution/consumer-smoke.php', $consumer], $root);
file_put_contents($root . '/build/consumer-path.txt', $consumer);
mkdir($consumer . '/resources/css/filament/admin', 0755, true);
file_put_contents($consumer . '/resources/css/filament/admin/theme.css', "@import '../../../../vendor/filament/filament/resources/css/theme.css';\n@source '../../../../vendor/eyawiin/filament-taxonomies/resources/views/**/*.blade.php';\n");
$routes = $consumer . '/routes/web.php';
file_put_contents($routes, "\n\\Illuminate\\Support\\Facades\\Route::get('/__fixture-state', fn () => \\Eyawiin\\FilamentTaxonomies\\Models\\TaxonomyTerm::orderBy('id')->get(['id', 'taxonomy_id', 'name', 'slug', 'parent_id', 'position']));\n", FILE_APPEND);
runDistribution(['node', 'bin/build-consumer-theme.js'], $root);
runDistribution(['node', 'node_modules/@playwright/test/cli.js', 'test', '--config=playwright.consumer.config.js'], $root);
echo "PASS distribution archive, installation/publishing and clean consumer browser smoke.\n";
