<?php

use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/bootstrap.php';

function signalWorker(string $event, array $data = []): void
{
    echo json_encode(['event' => $event, ...$data], JSON_THROW_ON_ERROR) . "\n";
    flush();
}

function awaitCommand(string $command): void
{
    if (trim((string) fgets(STDIN)) !== $command) {
        throw new RuntimeException('The coordinator did not send ' . $command);
    }
}

try {
    [$database, $operation, $encoded, $pause, $snapshot] = array_slice($argv, 1);
    bootConcurrencyDatabase($database);
    $args = json_decode($encoded, true, flags: JSON_THROW_ON_ERROR);
    $connection = DB::connection();
    if ($snapshot === '1') {
        $connection->beginTransaction();
    }
    // Deliberately preload models before either write, including an old RR snapshot.
    $taxonomy = Taxonomy::findOrFail($args['taxonomy']);
    $source = isset($args['source']) ? TaxonomyTerm::findOrFail($args['source']) : null;
    $target = isset($args['target']) ? TaxonomyTerm::findOrFail($args['target']) : null;
    if ($snapshot === '1') {
        TaxonomyTerm::all();
    }
    $connectionId = (int) $connection->selectOne('SELECT CONNECTION_ID() AS id')->id;
    signalWorker('ready', ['connection' => $connectionId]);

    $hold = $pause === '1';
    $connection->listen(function (QueryExecuted $event) use (&$hold): void {
        if ($hold && str_contains($event->sql, chr(96) . 'taxonomies' . chr(96)) && str_contains(strtolower($event->sql), 'for update')) {
            $hold = false;
            signalWorker('locked');
            awaitCommand('continue');
        }
    });
    awaitCommand('go');
    signalWorker('started');
    $service = new TaxonomyTreeService;
    if ($operation === 'rename') {
        $source->name = $args['name'];
    }
    $result = match ($operation) {
        'create' => $service->createTerm($taxonomy, $args['name'], $args['slug']),
        'rename', 'noop' => $service->setParent($source, null),
        'reparent' => $service->setParent($source, $target),
        'drop' => $service->moveRelativeTo($source, $target, TaxonomyTermDropPosition::Inside),
        'delete-term' => $service->deleteTerm($source),
        'delete-taxonomy' => $service->deleteTaxonomy($taxonomy),
        default => throw new RuntimeException('Unknown worker operation'),
    };
    if ($snapshot === '1') {
        $connection->commit();
    }
    signalWorker('result', ['ok' => true, 'id' => $result instanceof TaxonomyTerm ? $result->id : null,
        'name' => $result instanceof TaxonomyTerm ? $result->name : null,
        'position' => $result instanceof TaxonomyTerm ? $result->position : null]);
} catch (Throwable $exception) {
    if (isset($connection) && $connection->transactionLevel() > 0) {
        $connection->rollBack();
    }
    signalWorker('result', ['ok' => false, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
    exit(1);
}
