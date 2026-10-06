# MySQL hierarchy concurrency gate

Run from the repository root after Composer dependencies are installed:

    TAXONOMY_MYSQL_HOST=127.0.0.1 \
    TAXONOMY_MYSQL_PORT=3306 \
    TAXONOMY_MYSQL_USER=your_test_user \
    TAXONOMY_MYSQL_PASSWORD=your_test_password \
    composer test:concurrency

In PowerShell, set the variables first:

    $env:TAXONOMY_MYSQL_HOST = '127.0.0.1'
    $env:TAXONOMY_MYSQL_USER = 'your_test_user'
    $env:TAXONOMY_MYSQL_PASSWORD = 'your_test_password'
    composer test:concurrency

Use a disposable MySQL **8.4** server with InnoDB and performance_schema enabled.
Other MySQL 8 servers, such as Laravel Herd's MySQL 8.0 service, are rejected
unless `TAXONOMY_MYSQL_ALLOW_UNVERIFIED_VERSION=1` is also set. The runner then
prints a warning and the result is informational; CI keeps the verified 8.4 gate.
The test user needs CREATE/DROP DATABASE, access to its test tables, and SELECT on
performance_schema.data_lock_waits and performance_schema.threads. These are test
infrastructure privileges, not package runtime requirements. No database name is
accepted from the environment: the runner creates a random
filament_taxonomies_f2_<12 hex characters> database, runs the actual package
migrations there, disconnects workers, and drops that database in finally.

The runner fails when its server/privileges/version are unavailable; it never
silently skips. It is independent of PHPUnit's SQLite discovery and does not read
the workbench .env. F5 automates this explicit gate in CI.

Seven scenarios use separate PHP processes/connections and explicit stdin/JSON
coordination. Reported worker results must match their process exit status.
Six conflicting cases must show a database-reported InnoDB lock
wait before the first operation is released. Polling sleeps only service the
transport/observation deadline; elapsed time is never a passing assertion.

- Simultaneous creates receive distinct contiguous positions.
- Creation remains correct inside an existing repeatable-read snapshot.
- Metadata-only no-op returns current values under an old snapshot.
- Competing reparent operations cannot collectively introduce a cycle.
- A target removed by the first writer fails the waiting drop without partial writes.
- Taxonomy deletion cascades its subtree and prevents a waiting create.
- Another taxonomy can complete while the first taxonomy's lock is held.

Default Pest tests cover functional ordering, hidden structural ancestors,
stale inputs, cancelled observers, rollback after partial updates/cascades, fresh
authorization, and commit-dependent expansion events. This gate complements them.

The same disposable database also verifies F3's narrow slug error translation
against actual MySQL driver diagnostics: taxonomy and taxonomy-scoped term
duplicates on creation and edit (four cases). These are constraint checks, not
additional contention scenarios. They assert the slug field association and
unchanged stored rows. The runner supplies a minimal translator for this check;
Pest verifies the full Filament form, standard validation messages, and retry
behavior. Unknown constraints remain unmodified exceptions.
The disposable database also checks joined visibility scopes on MySQL: preserved
term/taxonomy identities, distinct visible counts, deduplicated tree/descendant
reads, and subtree-preserving managed movement with hidden sibling maintenance.
This is a functional SQL check, separate from the seven contention scenarios.

The F6 read-only import check also verifies cycles, foreign parents and zero-parent
orphans without changing stored rows, including scoped-out structural ancestors.
Only its disposable connection temporarily relaxes FK checks for a deliberately
malformed zero-parent fixture; checks are restored immediately. This does not
change package runtime FK settings or add a contention scenario.

The final disposable import checks also exercise unsigned IDs above PHP’s native
integer ceiling: diagnostics retain exact physical keys, managed operations
reject aliases, oversized parent/owner references cannot select neighbouring
records, and generated-ID exhaustion rolls back. These fixtures run last because
explicit large IDs advance the disposable tables’ auto-increment counters.

## Assignment verification (P1)

The runner also migrates the real assignment pivot and uses two independent
processes to verify idempotent concurrent attachment, conflicting scoped sync
under an existing repeatable-read snapshot, and attachment waiting on term/owner
deletion. All four scenarios require observed InnoDB lock waits. Additional
checks exercise case-sensitive morph identity and owner cleanup isolation.
All fixture owner tables are created only in the runner's random disposable DB.
