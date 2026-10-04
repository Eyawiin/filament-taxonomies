<?php

use App\Policies\ConsumerTermPolicy;
use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Gate;

function requireConsumer(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

try {
    $consumer = $argv[1];
    require $consumer . '/vendor/autoload.php';
    $app = require $consumer . '/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    requireConsumer(is_array(config('filament-taxonomies')), 'Package config did not merge.');
    requireConsumer(str_starts_with((new ReflectionClass(Taxonomy::class))->getFileName(), $consumer . '/vendor/eyawiin/filament-taxonomies/'), 'Consumer must load its copied distribution, not the source checkout.');
    Gate::policy(TaxonomyTerm::class, ConsumerTermPolicy::class);

    $service = app(TaxonomyTreeService::class);
    $taxonomy = Taxonomy::create(['name' => 'Consumer topics', 'slug' => 'consumer-topics']);
    $root = $service->createTerm($taxonomy, 'Root', 'root');
    $child = $service->createTerm($taxonomy, 'Child', 'child', $root);
    $other = $service->createTerm($taxonomy, 'Other', 'other');
    $locked = $service->createTerm($taxonomy, 'Locked', 'locked');
    requireConsumer(Gate::allows('update', $root) && Gate::denies('update', $locked), 'Consumer policy integration failed.');
    $child->name = 'Edited child';
    $service->setParent($child, $other);
    requireConsumer($child->fresh()->name === 'Edited child' && $child->fresh()->parent_id === $other->id, 'Edit/reparent failed.');
    $service->moveRelativeTo($other, $root, TaxonomyTermDropPosition::Before);
    requireConsumer($other->fresh()->position === 0, 'Consumer move failed.');
    $service->deleteTerm($other);
    requireConsumer($child->fresh()->parent_id === null, 'Consumer deletion/promotion failed.');
    $service->deleteTerm($child);
    requireConsumer(! TaxonomyTerm::whereKey($child->id)->exists(), 'Term deletion failed.');
    $taxonomy->update(['name' => 'Edited taxonomy']);
    requireConsumer($taxonomy->fresh()->name === 'Edited taxonomy', 'Taxonomy edit failed.');
    $service->deleteTaxonomy($taxonomy);
    requireConsumer(! Taxonomy::whereKey($taxonomy->id)->exists(), 'Taxonomy deletion failed.');

    require __DIR__ . '/assignment-smoke.php';
    verifyConsumerAssignments();

    // Fresh browser smoke data; not consumer/user data.
    $taxonomy = Taxonomy::create(['name' => 'Browser consumer', 'slug' => 'browser-consumer']);
    $service->createTerm($taxonomy, 'Alpha', 'alpha');
    $service->createTerm($taxonomy, 'Beta', 'beta');
    $service->createTerm($taxonomy, 'Locked', 'locked');
    echo "PASS clean consumer config, CRUD, parenting, movement, promotion and policy.\n";

} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
