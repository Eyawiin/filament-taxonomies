# MySQL hierarchy concurrency gate

Run from the repository root after Composer dependencies are installed:

    TAXONOMY_MYSQL_HOST=127.0.0.1 \
    TAXONOMY_MYSQL_PORT=3306 \
    TAXONOMY_MYSQL_USER=your_test_user \
    TAXONOMY_MYSQL_PASSWORD=your_test_password \
    composer test:concurrency

Use a disposable MySQL **8.4** server with InnoDB and performance_schema enabled.
The test user needs CREATE/DROP DATABASE, access to its test tables, and SELECT on
performance_schema.data_lock_waits and performance_schema.threads. These are test
infrastructure privileges, not package runtime requirements. No database name is
accepted from the environment: the runner creates a random
filament_taxonomies_f2_<12 hex characters> database, runs the actual package
migrations there, disconnects workers, and drops that database in finally.

The runner fails when its server/privileges/version are unavailable; it never
silently skips. It is independent of PHPUnit's SQLite discovery and does not read
the workbench .env. F5 will automate this explicit gate in CI.

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
