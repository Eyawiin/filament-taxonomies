# P1 explicit assignment contract

Implemented and reviewed on 2026-10-04, based on the foundation through `85088b4`.
See [usage and upgrade instructions](../../README.md#assigning-terms-to-eloquent-models).

## Design and reused boundaries

`HasTaxonomies` is an Eloquent trait, not a Filament field. `taxonomyTerms` is a
polymorphic relation; `termsForTaxonomy` supplies an explicit taxonomy boundary.
Taxonomy model/int references use stored IDs; strings use exact slugs. Term inputs
are distinct positive native integer IDs or lossless integer strings. Assignment
never expands the hierarchy. Native `with` and `whereHas` cover demonstrated reads.

`TaxonomyAssignmentService` reuses foundation identity normalization and taxonomy
locking, then locks a fresh scoped owner row. Selected and existing records use
current reads; input validation precedes deletions/inserts. No automatic retries.
One sync replaces only one taxonomy; hidden existing assignments cause rejection.
Authentication/authorization belongs to the application calling this API.

The real pivot migration gives each owner/type/term tuple a unique constraint,
indexes owner and term lookups, cascades term deletion, and stores owner keys as
strings so integer/UUID/ULID consumers share one table. MySQL ASCII binary columns
preserve exact alias/key identities. Owner keys, morph types, connection limits,
model-event cleanup and eventless deletion responsibilities are documented in README.

These choices follow Eloquent's [polymorphic relationships and morph maps](https://laravel.com/docs/13.x/eloquent-relationships#custom-polymorphic-types)
and [model deletion/event behavior](https://laravel.com/docs/13.x/eloquent#deleting-models).
The package adds stronger validation/locking through managed methods; direct
relation or raw writes retain native behavior and bypass that boundary.

## Review findings fixed

- Union types could coerce malformed taxonomy references; use mixed input with
  deliberate runtime validation, retaining the intended public types in PHPDoc.
- Existing scoped-out selections must not be silently removed by sync.
- Joined term visibility could duplicate relationship rows; distinct reads and
  counts now have SQLite/MySQL verification. Consumer owner joins still need
  qualified owner-table projection, as normal Eloquent queries do.
- Cached loaded assignments need invalidation after managed mutation/cleanup.
- Soft-delete detection must use the actual trait, and PHPStan must analyse the
  trait in real consumer contexts. Dedicated owner fixtures provide that coverage.

## Verification

Final local gates passed; detailed results follow.
New remote CI is unverified until P1 is committed/pushed. Actual human F6
screen-reader evidence remains unverified; the user approved proceeding to P1.

Final key review reproduced rejection of Laravel's generated lowercase ULIDs.
Validation now accepts valid ULIDs in either case and preserves exact stored
bytes. Fixtures use native HasUuids/HasUlids; generated-key regressions and the
MySQL/copied-consumer checks exercise those actual defaults.

### Final local verification — 2026-10-04

- PHP 8.3.33 / Laravel 13.33.0 / Filament 5.8.4: **406 tests / 1,543 assertions** pass, including **47 P1 cases / 101 assertions**.
- Isolated Laravel 11 prefer-lowest install on PHP 8.3: all **47 P1 cases / 101 assertions** pass, including native generated UUID/ULID keys. Laravel 12, PHP 8.2/8.4 and Windows P1 execution remain remote matrix work; no new remote result is claimed.
- MySQL 8.4.11 / InnoDB / REPEATABLE READ: all seven foundation scenarios, four slug checks, joined visibility/import/unsigned-ID checks, **four new assignment races with observed waits**, generated string-key round trips, distinct joined reads/counts, case-sensitive aliases and owner cleanup pass. Each run uses its own random database and drops it; the task's container is removed.
- Final reviewed working snapshot exports through both Git and Composer archives. A fresh Laravel 13 consumer installs the copied package, publishes/runs all four migrations, exercises independent integer owner types and native generated UUID/ULID owners, scoped sync, query and cleanup, then passes the existing native CRUD/parenting/repeated-drag/policy browser smoke.
- PHPStan level 4 passes (trait analysed in five real fixture owner types); full Pint **123 files**, strict Composer validation and whitespace gates pass.
- No new dependency or JavaScript/bundle change. No workbench/user data migrated. An isolated Git index produced the test archive; the user's index remains empty. P1 stays uncommitted/unpushed. Remote CI and actual human screen-reader evidence are unverified for P1; the latter remains the accepted foundation limitation.

P1 implementation, critical review, corrected regressions and local gates are complete. P2 reusable assignment fields are the next milestone after accepting this backend.

### Workbench database repair and manual P1 test — 2026-10-04

A user request to open /admin exposed a missing vendor/orchestra/testbench-core/
laravel/database/database.sqlite. WorkbenchServiceProvider had pointed at the
disposable Testbench skeleton, whose normal purge removes that database.

Development SQLite now lives at gitignored workbench/database/database.sqlite.
The preparation script preserves an existing durable file, snapshots an existing
legacy database with VACUUM INTO (including committed WAL data), and rejects
invalid destinations/corrupt legacy data without replacing them. Failed temporary
snapshots are removed. Composer clear prepares before purging the old skeleton;
build prepares before migration. The unused vendor create-sqlite recipe is removed.
The old vendor database was already absent in this workspace; the new durable
file was initialized and ordinary migrations ran, including P1. No old database
was wiped and no recovery of absent historical records is claimed.

The existing workbench User includes HasTaxonomies for manual testing; README
contains tested Tinker setup/attach/read/clear/cleanup steps. A real CLI example
passed and its demo data was rolled back. After the focused tests, both /admin/
and /admin/taxonomies returned HTTP 200 from the user's existing server. Four
new preparation regressions verify idempotence/data preservation, committed WAL
snapshot, corrupt-source cleanup and invalid destination rejection. The 47 P1
cases and all four regressions pass (51 cases / 113 assertions). Final full-suite
results follow below. Changes remain uncommitted.

Final workbench-repair gates: **410 PHP tests / 1,555 assertions**, PHPStan level 4,
Pint **124 files**, strict Composer validation and whitespace checks pass.
After the full suite, /admin still returns HTTP 200 and the durable development
database is untouched by tests (zero taxonomy/term/user/assignment demo records).
The user can refresh the existing server; no restart was required. The file is
ignored by Git and the index remains empty. P1 and these development fixes remain
uncommitted/unpushed. The previous missing vendor file had no recoverable SQLite
copy among the checked project files; no historical data restoration is claimed.

## Commit status

P1 and the persistent workbench repair were committed/pushed on `5.x` in `c366c23`
on 2026-10-04 before P2 implementation, at the user's request. Earlier local-only
status notes describe the verification point preceding that commit.
