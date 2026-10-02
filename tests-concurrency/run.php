<?php

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Forms\TaxonomySlugValidation;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/Worker.php';

function checkConcurrency(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function observeLockWait(PDO $admin, int $waitingConnection, int $blockingConnection): void
{
    $statement = $admin->prepare('
        SELECT COUNT(*) FROM performance_schema.data_lock_waits w
        JOIN performance_schema.threads requester ON requester.THREAD_ID = w.REQUESTING_THREAD_ID
        JOIN performance_schema.threads blocker ON blocker.THREAD_ID = w.BLOCKING_THREAD_ID
        WHERE requester.PROCESSLIST_ID = ? AND blocker.PROCESSLIST_ID = ?
    ');
    $deadline = microtime(true) + 10;
    do {
        $statement->execute([$waitingConnection, $blockingConnection]);
        if ((int) $statement->fetchColumn() > 0) {
            return;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException('InnoDB did not report the expected lock wait between independent connections.');
}

function competingWrites(PDO $admin, string $database, array $first, array $second, bool $snapshot = false): array
{
    $a = new ConcurrencyWorker($database, $first[0], $first[1], pause: true);
    $b = null;

    try {
        $b = new ConcurrencyWorker($database, $second[0], $second[1], snapshot: $snapshot);
        $readyA = $a->await('ready');
        $readyB = $b->await('ready');
        checkConcurrency($readyA['connection'] !== $readyB['connection'], 'Workers must use independent database connections.');
        $a->send('go');
        $a->await('locked');
        $b->send('go');
        $b->await('started');
        observeLockWait($admin, $readyB['connection'], $readyA['connection']);
        $a->send('continue');

        return [$a->await('result'), $b->await('result')];
    } finally {
        $a->stop();
        $b?->stop();
    }
}

$admin = null;
$database = null;
$created = false;
$capsule = null;

try {
    $config = concurrencyDatabaseConfig();
    $admin = new PDO(
        'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4',
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10],
    );
    $version = (string) $admin->query('SELECT VERSION()')->fetchColumn();
    checkConcurrency(str_starts_with($version, '8.4.'), 'This gate requires MySQL 8.4; received ' . $version);
    $database = 'filament_taxonomies_f2_' . bin2hex(random_bytes(6));
    // Never select, migrate, or drop a consumer's database.
    $admin->exec('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $capsule = bootConcurrencyDatabase($database);
    foreach (glob(dirname(__DIR__) . '/database/migrations/*.php') as $migration) {
        (require $migration)->up();
    }
    $engines = DB::select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', [$database]);
    checkConcurrency(count($engines) === 2 && count(array_filter($engines, fn ($row) => $row->ENGINE !== 'InnoDB')) === 0, 'Both actual package tables must use InnoDB.');
    checkConcurrency(DB::selectOne('SELECT @@transaction_isolation AS isolation')->isolation === 'REPEATABLE-READ', 'Expected repeatable-read isolation.');
    echo 'MySQL ' . $version . ' / InnoDB / REPEATABLE READ / independent PHP processes' . "\n";

    foreach ([false, true] as $snapshot) {
        $tax = Taxonomy::create(['name' => 'Create', 'slug' => $snapshot ? 'snapshot' : 'create']);
        [$a, $b] = competingWrites(
            $admin,
            $database,
            ['create', ['taxonomy' => $tax->id, 'name' => 'Zulu', 'slug' => 'zulu']],
            ['create', ['taxonomy' => $tax->id, 'name' => 'Alpha', 'slug' => 'alpha']],
            $snapshot
        );
        checkConcurrency($a['ok'] && $b['ok'], 'Both competing creates must succeed: ' . json_encode([$a, $b]));
        $terms = $tax->terms()->orderBy('position')->get();
        checkConcurrency($terms->pluck('name')->all() === ['Zulu', 'Alpha'] && $terms->pluck('position')->all() === [0, 1], 'Concurrent creates must reserve distinct consecutive terminal positions.');
        echo 'PASS concurrent creates' . ($snapshot ? ' with an existing RR snapshot' : '') . " (observed lock wait)\n";
    }

    $tax = Taxonomy::create(['name' => 'No-op', 'slug' => 'noop']);
    $term = $tax->terms()->create(['name' => 'Old', 'slug' => 'old', 'position' => 7]);
    [$a, $b] = competingWrites(
        $admin,
        $database,
        ['rename', ['taxonomy' => $tax->id, 'source' => $term->id, 'name' => 'Fresh']],
        ['noop', ['taxonomy' => $tax->id, 'source' => $term->id]],
        snapshot: true
    );
    checkConcurrency(
        $a['ok'] && $b['ok'] && $b['name'] === 'Fresh' && $b['position'] === 7,
        'A no-op must return current metadata and preserve stored position under an old RR snapshot: ' . json_encode([$a, $b])
    );
    echo "PASS no-op returns current metadata under an existing RR snapshot (observed lock wait)\n";

    $tax = Taxonomy::create(['name' => 'Cycle', 'slug' => 'cycle']);
    $left = $tax->terms()->create(['name' => 'Left', 'slug' => 'left', 'position' => 0]);
    $right = $tax->terms()->create(['name' => 'Right', 'slug' => 'right', 'position' => 1]);
    [$a, $b] = competingWrites(
        $admin,
        $database,
        ['reparent', ['taxonomy' => $tax->id, 'source' => $left->id, 'target' => $right->id]],
        ['reparent', ['taxonomy' => $tax->id, 'source' => $right->id, 'target' => $left->id]]
    );
    checkConcurrency($a['ok'] && ! $b['ok'] && $b['exception'] === InvalidTaxonomyParentException::class, 'The second cyclic reparent must fail: ' . json_encode([$a, $b]));
    checkConcurrency($left->fresh()->parent_id === $right->id && $right->fresh()->parent_id === null && $right->fresh()->position === 0, 'The rejected reparent must not leave a cycle or partial order.');
    echo "PASS competing reparent operations cannot collectively create a cycle (observed lock wait)\n";

    $tax = Taxonomy::create(['name' => 'Deleted target', 'slug' => 'deleted-target']);
    $source = $tax->terms()->create(['name' => 'Source', 'slug' => 'source', 'position' => 0]);
    $target = $tax->terms()->create(['name' => 'Target', 'slug' => 'target', 'position' => 1]);
    [$a, $b] = competingWrites(
        $admin,
        $database,
        ['delete-term', ['taxonomy' => $tax->id, 'source' => $target->id]],
        ['drop', ['taxonomy' => $tax->id, 'source' => $source->id, 'target' => $target->id]]
    );
    checkConcurrency($a['ok'] && ! $b['ok'] && $b['exception'] === ModelNotFoundException::class, 'A removed drop target must fail cleanly: ' . json_encode([$a, $b]));
    checkConcurrency($source->fresh()->parent_id === null && $source->fresh()->position === 0 && TaxonomyTerm::find($target->id) === null, 'A stale drop must leave no partial mutation.');
    echo "PASS deleted target is rechecked after waiting (observed lock wait)\n";

    $tax = Taxonomy::create(['name' => 'Deleted taxonomy', 'slug' => 'deleted-taxonomy']);
    $root = $tax->terms()->create(['name' => 'Existing', 'slug' => 'existing']);
    $child = $tax->terms()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $root->id]);
    $tax->terms()->create(['name' => 'Grandchild', 'slug' => 'grandchild', 'parent_id' => $child->id]);
    $otherCount = TaxonomyTerm::where('taxonomy_id', '!=', $tax->id)->count();
    [$a, $b] = competingWrites(
        $admin,
        $database,
        ['delete-taxonomy', ['taxonomy' => $tax->id]],
        ['create', ['taxonomy' => $tax->id, 'name' => 'Late', 'slug' => 'late']]
    );
    checkConcurrency($a['ok'] && ! $b['ok'] && $b['exception'] === ModelNotFoundException::class, 'Creation must not resurrect a removed taxonomy: ' . json_encode([$a, $b]));
    checkConcurrency(Taxonomy::find($tax->id) === null && TaxonomyTerm::where('taxonomy_id', $tax->id)->count() === 0 && TaxonomyTerm::count() === $otherCount, 'Taxonomy deletion must preserve other taxonomy terms.');
    echo "PASS taxonomy deletion cooperates with concurrent creation (observed lock wait)\n";

    $held = Taxonomy::create(['name' => 'Held', 'slug' => 'held']);
    $free = Taxonomy::create(['name' => 'Free', 'slug' => 'free']);
    $a = new ConcurrencyWorker($database, 'create', ['taxonomy' => $held->id, 'name' => 'Held', 'slug' => 'held'], pause: true);
    $b = null;

    try {
        $b = new ConcurrencyWorker($database, 'create', ['taxonomy' => $free->id, 'name' => 'Free', 'slug' => 'free']);
        $a->await('ready');
        $b->await('ready');
        $a->send('go');
        $a->await('locked');
        $b->send('go');
        checkConcurrency($b->await('result')['ok'], 'Another taxonomy must be writable while the first lock is held.');
        $a->send('continue');
        checkConcurrency($a->await('result')['ok'], 'The held taxonomy must complete after release.');
    } finally {
        $a->stop();
        $b?->stop();
    }
    echo "PASS independent taxonomies progress while another taxonomy is locked\n";
    echo "7 concurrency scenarios passed.\n";

    // The full form/retry path is exercised in Pest. Here use real MySQL errors
    // to prove the diagnostic matcher recognizes the actual package constraints.
    $translator = new Translator(new ArrayLoader, 'en');
    $translator->addLines(['validation.unique' => 'The :attribute has already been taken.'], 'en');
    $capsule->getContainer()->instance('translator', $translator);
    $capsule->getContainer()->instance('validator', new Factory($translator, $capsule->getContainer()));
    $tax = Taxonomy::create(['name' => 'Validation', 'slug' => 'mysql-validation']);
    $other = Taxonomy::create(['name' => 'Subject', 'slug' => 'mysql-subject']);
    $existing = $tax->terms()->create(['name' => 'Existing', 'slug' => 'existing']);
    $subject = $tax->terms()->create(['name' => 'Subject', 'slug' => 'subject', 'position' => 1]);
    $beforeTaxonomies = Taxonomy::orderBy('id')->get()->toArray();
    $beforeTerms = TaxonomyTerm::orderBy('id')->get()->toArray();
    $service = new TaxonomyTreeService;

    foreach (['taxonomies', 'taxonomy_terms'] as $table) {
        foreach (['create', 'edit'] as $operation) {
            try {
                try {
                    if ($table === 'taxonomies') {
                        $operation === 'create'
                            ? Taxonomy::create(['name' => 'Changed', 'slug' => $tax->slug])
                            : $other->update(['name' => 'Changed', 'slug' => $tax->slug]);
                    } elseif ($operation === 'create') {
                        $service->createTerm($tax, 'Changed', $existing->slug);
                    } else {
                        $subject->fill(['name' => 'Changed', 'slug' => $existing->slug]);
                        $service->setParent($subject, null);
                    }
                } catch (UniqueConstraintViolationException $exception) {
                    TaxonomySlugValidation::report($exception, $table, 'data.slug');
                }

                throw new RuntimeException('Expected a database slug conflict.');
            } catch (ValidationException $exception) {
                checkConcurrency($exception->errors() === ['data.slug' => ['The slug has already been taken.']], 'MySQL slug conflict must retain its field association.');
            }
            checkConcurrency(Taxonomy::orderBy('id')->get()->toArray() === $beforeTaxonomies && TaxonomyTerm::orderBy('id')->get()->toArray() === $beforeTerms, 'A slug conflict must leave all stored data unchanged.');
            echo 'PASS MySQL slug constraint feedback: ' . $table . ' ' . $operation . "\n";
        }
    }
    echo "4 MySQL slug constraint cases passed.\n";
    require __DIR__ . '/joined-visibility.php';
    verifyJoinedVisibility();
    require __DIR__ . '/import-diagnostics.php';
    verifyImportedStructure();
    verifyUnsignedIdentities();

} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . "\n");
    $failed = true;
} finally {
    if ($created) {
        $capsule?->getDatabaseManager()->disconnect();
        $admin->exec('DROP DATABASE ' . $database);
    }
}
exit(isset($failed) ? 1 : 0);
