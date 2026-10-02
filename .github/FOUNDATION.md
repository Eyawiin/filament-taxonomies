# Foundation contracts and reproductions

Decision date: 2026-10-01. Reviewed source baseline: `5.x`, `2ec2cea`.

This contributor document records F0's decisions and executable reproductions. **F1 implements the management permission and query visibility contract. F2 implements coordinated hierarchy writes, verified on MySQL 8.4/InnoDB. F3 implements scoped slug validation and controlled mutation feedback. F4 implements the internal parent field lifecycle; F5 makes distribution and verification portable. A human screen-reader session and the new remote CI run remain F6 acceptance checks.** The behavior and test status below distinguish completed and pending contracts. The local, gitignored `ROADMAP.md` holds the complete implementation sequence.

## F0 implementation plan

1. Inspect the existing service, actions, migrations, selector, publishing registration, and test harness.
2. Define ordering, deletion, write boundaries, permissions, compatibility, and public API decisions before changing behavior.
3. Preserve established behavior with required tests; express missing guarantees as explicitly runnable pending tests.
4. Execute every reproduction and inspect the actual failure. Keep pending specifications separate from required CI and promote them with their fixes.
5. Record verification and the next milestone. F0 does not implement F1–F5 or establish concurrency/browser/distribution guarantees.

## Compatibility and verification scope

`composer.json` declares PHP `^8.2` and Filament `^5.0`. Installed Filament 5.8.4 requires PHP `^8.2`; its support package accepts Illuminate `^11.28|^12.0|^13.0`.

| Framework | Upstream PHP range | Package CI configuration |
| --- | --- | --- |
| Laravel 11 | 8.2–8.4 | PHP 8.2/8.3/8.4, Testbench 9 |
| Laravel 12 | 8.2–8.5 | PHP 8.2/8.3/8.4, Testbench 10 |
| Laravel 13 | 8.3–8.5 | PHP 8.3/8.4, Testbench 11 |

The configured PHP matrix runs lowest/stable dependencies on Ubuntu/Windows; static analysis uses stable dependencies. Configuration is not proof that the latest external run passed. PHP 8.2 is configured for Laravel 11/12; PHP 8.5 is outside the current package CI matrix. Solver acceptance and upstream compatibility do not establish package verification. F5 retains the declared range and adds valid PHP 8.2 lanes; the new remote jobs remain unexecuted until push.

Laravel 11 reached upstream security end of life on 2026-03-12. Retain its existing compatibility lane during the foundation pass; document it as legacy compatibility. Use maintained Laravel 12/13 for new consumer examples. Any removal needs explicit upgrade/versioning treatment. [Laravel's support table](https://laravel.com/framework/docs/13.x/releases#support-policy) supports these upstream ranges and dates.

Current local evidence: Ubuntu under WSL, PHP 8.3.33, Filament 5.8.4, Laravel 13.33.0, Testbench 11.3.0, Pest 4.7.8, SQLite in memory with foreign keys enabled, Node 24.21.0. Other local PHP/framework/OS combinations were not run for F0.

**Database decision:** SQLite remains the fast functional test backend. The first production concurrency target is MySQL 8.4 with InnoDB on a single default connection. F2 verifies transaction/locking behavior with independent processes on MySQL 8.4.11; F5 adds that CI lane. Verification is limited to the recorded local environment and scenarios. PostgreSQL, alternate storage engines, multiple connections, and arbitrary raw writers have no concurrency guarantee from this review.

## Hierarchy contracts

### Managed writes

The invariant boundary is the supported tree service and package management actions. Application authorization belongs at the UI/integration boundary; the domain service enforces hierarchy and persistence rules without assuming an authenticated HTTP request.

Raw SQL, query-builder writes, relationship `create()`, model `save()/delete()`, and third-party imports remain possible. Their database foreign keys/unique constraints still apply, but they do not receive automatic cycle checking, position normalization, or coordinated locking. Do not add global model observers that silently rewrite imports. Importers should use the managed service or explicitly validate their results.

All package hierarchy writes now use the service: creation, edit/reparenting, explicit/relative movement, sibling reordering, term deletion, and taxonomy deletion. Each operation acquires the owning taxonomy row lock before current structural reads and writes. The page/table rechecks scoped records and policy responses inside that lock boundary.

### Parents, ordering, and mutation results

- Taxonomy/term keys remain positive integers. At the form boundary, null/empty-string parent state means root; valid numeric strings normalize to positive IDs. Zero, negatives, fractions, and malformed IDs must be rejected, not treated as root. Services retain their typed model/ID arguments.
- A parent belongs to the same taxonomy. A term cannot have itself or a descendant as its parent. Reparenting preserves the entire descendant subtree.
- A sibling group is identified by `(taxonomy_id, parent_id)`; `null` means roots. After a structural managed mutation, affected groups have contiguous integer positions starting at zero. Metadata-only edits preserve existing positions, including legacy gaps.
- Preserve existing order in each unaffected group. Read ties deterministically by `position`, then `name`, then `id`; ties are compatibility handling for existing data, not the managed final state.
- Creating appends to the chosen sibling group. Changing parent through edit/`setParent()` appends to the destination and normalizes the source.
- Keeping the same parent preserves the term's position while saving pending name/slug edits.
- `moveTerm()` positions refer to the destination list with the moving term excluded. The valid insertion range is zero through that list's length, inclusive.
- Before/after uses the target's sibling group. Inside appends to the target's children, including when already a child. Arrows operate only within the current sibling group.
- Invalid, foreign, missing, or stale IDs must not turn into a root selection or a partial mutation. Expected invalid inputs produce controlled errors; transaction failures roll back all affected writes.
- No new unique sibling-position constraint is chosen: nullable roots and temporary reorder collisions need separate cross-engine design.

### Deletion decision

Preserve immediate-child promotion to root, including deletion of a nested term. The chosen order is:

1. Keep surviving root terms in their existing order.
2. Append the deleted term's immediate children in their previous sibling order.
3. Normalize roots and, for nested deletion, the deleted term's former sibling group.
4. Preserve deeper descendants under their existing parents.

Example: roots `[A, B, C]`, with children `[X, Y]` under B, become roots `[A, C, X, Y]`, positions `[0, 1, 2, 3]`. This deliberately avoids interleaving positions copied from a different group. Database `nullOnDelete()` promotes children; the managed service implements this ordering within the same transaction.

Deleting a taxonomy continues to cascade its terms and preserve other taxonomies. Its managed service and native table action acquire the same taxonomy lock.

### API compatibility and events

Retain existing signatures, return types, and domain exception classes for `canSetParent()`, `setParent()`, `moveTerm()`, `moveRelativeTo()`, `reorderSiblings()`, `getTree()`, `getDescendantIds()`, and `getNextPosition()`. No deprecation is needed for F0.

`setParent()` must continue saving a caller's pending name/slug edits; replacing it with a freshly loaded model must not discard those changes. `getNextPosition()` stays a read helper, not an atomic reservation or concurrent-create API. Additive `createTerm()`, `deleteTerm()`, and `deleteTaxonomy()` entry points are used by the page/table.

Existing position writes use bulk updates and do not emit one Eloquent save event per sibling. Preserve/document that behavior; do not imply otherwise. Tree expansion events are registered after successful writes and dispatched after the outer database transaction commits. Explicit source create/save/delete uses Eloquent events; sibling maintenance and FK promotion/cascade do not emit individual sibling/child saves or deletes. Consumer observers must defer external effects until commit.

## Management permissions and visibility

The initial product scope is **global taxonomies**, without an advertised tenant-ownership schema. Honor application visibility scopes, record permissions, and taxonomy membership at every supported query/write boundary. Being in a tenant-enabled panel alone must not be advertised as tested tenant isolation.

Permission mapping (implemented in F1):

| Operation | Required policy boundary |
| --- | --- |
| Resource listing / Manage Terms access | `Taxonomy::viewAny`; Manage Terms also requires `Taxonomy::view` on its record |
| Create taxonomy, edit taxonomy, delete taxonomy | Existing standard Filament taxonomy CRUD abilities |
| Create term | `TaxonomyTerm::create`; pass taxonomy context for consumers that require it |
| Edit or reparent a term | `TaxonomyTerm::update` on the source |
| Arrow, positional, or relative movement | `TaxonomyTerm::update` on the source, plus visible/valid destination |
| Delete term | `TaxonomyTerm::delete` on the term |

Each term operation additionally requires access to its owning taxonomy. Taxonomy delete permission never substitutes for term delete permission. Cross-taxonomy moves stay forbidden regardless of policy approval.

Use the active panel's guard and Laravel/Filament authorization mechanisms. Retain Filament's policy-free behavior for existing consumers: absent policy/method permits operations in normal mode, subject to Gate before callbacks; strict mode must report missing policy/methods. Explicit denials must apply on execution and direct Livewire requests, not just control visibility. Recheck permission on submission even if a modal was mounted while the operation was allowed. Consumers wanting a read-only installation should define all the used abilities. F1 tests missing policies/methods, strict mode, Gate before denials/Response metadata, additional create context, and use of the active panel guard.

Navigation, page resolution, options, and mutation lookup must honor the same visible-taxonomy boundary. Tests of term membership already exist; they are not evidence of policy enforcement. F1 adds custom-operation checks at mounting/execution and direct movement endpoints. Navigation uses the resource query and record-view permission; Manage Terms and Edit Taxonomy restore their resource query scope after Livewire hydration. Parent options and mutation lookups use scoped same-taxonomy relationships. Taxonomy CRUD retains Filament's standard ability mapping; a view policy controls Manage Terms, while query scopes control taxonomy list membership. [Filament's authorization guidance](https://filamentphp.com/docs/5.x/resources/overview#authorization) and installed `get_authorization_response()` inform this contract.

## Validation, keyboard, and installation decisions

- Taxonomy slug uniqueness is global; term slug uniqueness is within a taxonomy. Exclude the current record on edit. Keep database uniqueness authoritative. An unchanged slug and the same term slug in another taxonomy remain valid.
- Do not introduce restrictive slug formatting. Expected uniqueness/parent mistakes belong on the relevant field/action; infrastructure failures must not be swallowed.
- Root/no-parent is an explicit clear choice. The target keyboard traversal includes it before term choices: Home then Enter clears the parent. Search, branch expansion, and disabled nodes must not make clearing pointer-only.
- The F4 keyboard reproduction is promoted into required Node tests. Real Chromium tests additionally verify focus, root clearing and Tab behavior. Actual assistive-technology verification remains an F6 acceptance gate.
- Keep the advertised `filament-taxonomies-config` tag. Canonical file/key: `config/filament-taxonomies.php` / `filament-taxonomies`, matching provider/package naming. F5 aligns the source, merging, publishing and README.
- Required config tests and a fresh copied-archive consumer prove registration, merging and publishing. Workbench storage is generated as ordinary ignored directories.

## Executable reproductions

Required passing tests live in `tests/Feature`, `tests-js/*.test.js` and the focused browser harnesses. All F0 specifications have been promoted; the pending directories/scripts and PHPUnit exclusion have been removed. Historical evidence below preserves their original failures.

```bash
# Required PHP suite; --no-coverage avoids needing an enabled coverage driver.
composer test -- --no-coverage --ci
npm run test:js

# Focused browser and clean-consumer gates (see setup in README).
npm run test:browser
composer test:distribution

# Required F3 form validation regressions.
vendor/bin/pest --no-coverage tests/Feature/Resources/Taxonomies/SlugValidationTest.php

# Explicit MySQL gate; see tests-concurrency/README.md for server setup.
composer test:concurrency
```

| Owner | Specification | Cases | Status / reproduction |
| --- | --- | --- | --- |
| F1 — implemented | `tests/Feature/Resources/Taxonomies/TermAuthorizationTest.php` | 17 | Denials, revocation, allowed operations, and matching controls pass |
| F1 — implemented | `tests/Feature/Resources/Taxonomies/TaxonomyVisibilityTest.php` | 10 | Navigation, page/table/field denial, permission changes, and current-user navigation pass |
| F1 — implemented | `tests/Feature/Resources/Taxonomies/TaxonomyQueryVisibilityTest.php` | 19 | Model/resource scopes, hidden/foreign IDs, parent input, and hydrated manage/edit pages pass |
| F1 — implemented | `tests/Feature/Resources/Taxonomies/TaxonomyTermPolicyCompatibilityTest.php` | 17 | Filament defaults, strict mode, Gate callbacks, active guard, and create context pass |
| F1 — implemented | `tests-js/term-movement-permissions.test.js` | 3 | Drag capability changes, read-only destinations, and revocation before submission pass |
| F2 — implemented | `tests/Feature/Resources/Taxonomies/HierarchyOrderingTest.php` | 4 | Service/edit source normalization and root/nested promotion order pass |
| F3 — implemented locally | `tests/Feature/Resources/Taxonomies/*ValidationTest.php` and focused conflict/feedback groups | 70 | Scoped uniqueness, edit exemptions, stale parents, retries, action identities, and expected errors pass |
| F4 — implemented | `tests-js/parent-tree-root.test.js` | 1 | Home/Enter clears the parent; real browser focus also passes |
| F5 — implemented | `tests/Feature/ConfigPublishingTest.php` | 2 | Publish registry and merged config pass; fresh consumer verified |

Future reproductions should be promoted with their owning fix and run in required CI. Keep tests behavior-focused; do not hide unresolved requirements using skipped tests or expected-failure assertions.

## F0 verification (historical baseline)

On the environment above:

- Required PHP suite: **112 passed / 439 assertions**; includes five new preservation/constraint tests.
- Required JS suite: **11 passed**.
- Pending PHP specifications: **20 failed for their intended gaps**.
- Pending JS specification: **1 failed for the intended keyboard gap**.

Review round: action tests now cover permission revocation after mounting; deletion tests include multiple surviving roots with names that differ from position order; parent changes have a metadata/subtree preservation test. The read-only taxonomy fixture explicitly denies create/update/delete. Full PHP formatting, JS formatting, Composer validation, and whitespace checks passed.

No concurrency run, browser accessibility run, external CI check, or clean-distribution install is claimed. Those are concrete F2/F4/F5 gates. The F1 completion evidence below supersedes the permission/visibility reproduction status.

## F1 implementation and completion evidence — 2026-10-01

Implementation plan after reviewing F0 and the roadmap:

1. Integrate native Filament authorization responses for term create/update/delete;
   forward the owning taxonomy to policy-backed creation.
2. Authorize owning-taxonomy access and each custom mutation, including direct
   move/drop calls. Resolve terms/parents through scoped taxonomy relationships.
3. Apply resource queries and record permissions to navigation/Manage Terms links,
   parent options, and record resolution. Restore query scopes after hydration.
4. Reflect term updates in movement controls, promote the ten F0 regressions,
   add compatibility/scope/forged-ID tests, and rebuild distributed assets.

All custom term action callbacks explicitly authorize before writing. Native
Filament action authorization handles hidden controls and mounting/submission
checks; execution callbacks also enforce the boundary. Missing/scoped-out records
use framework missing-record/404 behavior; explicit policy denials use authorization
responses or the framework's forbidden response. UI hiding is not the write guard.

The small authorization adapter is reusable by future integration code, but does
not define an assignment permission API. The service and existing public mutation
signatures remain unchanged. Optional resource arguments on form/field/table
configuration forward an extended resource's scope rather than hardcoding the base
resource. There is no cross-user query cache or new tenant schema.

Review found and reproduced the native taxonomy edit page's scope bypass after
hydration; its record is now resolved through the resource query before Filament's
standard update check. This also preserves dirty form state in the normal allowed
edit path.

Verification on the local environment recorded above:

- Required PHP: **175 passed / 679 assertions**, including 63 F1 cases.
- Required JavaScript: **14 passed**, including three drag-adapter cases.
- PHPStan level 4: no errors; full Pint: 70 files pass.
- Changed JavaScript passes Prettier; Composer strict validation and whitespace checks pass.
- JavaScript distribution build, workbench asset publishing, and theme build pass.
- Remaining explicit pending PHP: **10 failures** (F2: four, F3: four, F5: two).
  The separate F4 keyboard specification remains pending.

The drag tests execute our real adapter with stubbed engine callbacks and DOM
surfaces. They do not establish full browser or assistive-technology behavior.
The external dependency/OS matrix, production locking, and tenant isolation have
not been reverified here. Next milestone: F2's unified atomic hierarchy writes.

### F1 review round

Reviewed the complete runtime diff, source and destination policy boundaries,
resource/global query scopes, action execution, Livewire hydration, recursive
controls, generated JavaScript, documentation, and every promoted/new test.
No runtime correction was needed. Added three regressions proving source-only
movement permissions with a visible read-only destination and denied term
deletion despite allowed taxonomy deletion.

Rechecked the native authorization helper against Filament 5.0's source; the
integration exists in the declared minimum Filament version. This source check
does not replace running the full dependency/OS CI matrix.

The full required suite passes with 175 PHP tests / 679 assertions and 14 JS
tests. PHPStan, full Pint, changed JS Prettier, strict Composer validation,
whitespace checks, and a fresh distribution build pass. Known F2–F5 pending
requirements remain separate. The review is complete and the F1 changes are
approved for commit/push by the user.

## F2 implementation and verification — 2026-10-01

The implementation plan was reviewed against F0/F1, installed framework code,
all managed write paths, and the roadmap before implementation:

1. Lock the owning taxonomy on the default connection before current structural
   reads, identity/membership checks, cycle checks, and destination decisions.
2. Reuse one ordering implementation for create, edit/reparent, explicit/relative
   moves, reorder, promotion, and cascade; preserve existing API signatures.
3. Recheck integration visibility and authorization while holding that lock.
4. Promote F0 ordering regressions and add rollback/stale/scope/observer tests.
5. Prove contention using independent processes on MySQL, then run the full gates.

### Persistence, visibility, and compatibility decisions

- Every operation runs in a transaction with **one attempt**. Automatic retries
  are deliberately absent: replaying consumer callbacks/observers could duplicate
  external effects. Callers may retry an entire operation with fresh inputs after
  handling a deadlock/timeout; exceptions are not swallowed.
- Lock acquisition begins with exactly one owning taxonomy. Subsequent locking
  reads use current database state even inside an existing repeatable-read
  transaction. Nested page/service calls retain the same outer lock/commit.
  Consumer transactions spanning multiple taxonomies must coordinate their own
  ascending taxonomy lock order; no multi-taxonomy mutation API is promised.
- Taxonomy and term models must use the **single default connection**. Unsupported
  connections/changed owning-model identities are rejected before mutation. Parent,
  target, and source existence/membership are checked again after waiting.
- Scoped input lookups and resource/policy checks remain authoritative. Internal
  structural reads and bulk maintenance bypass term global scopes, restricted to
  the locked taxonomy, so hidden ancestors cannot conceal a cycle and hidden
  siblings cannot acquire colliding positions. Hidden labels are never returned
  as UI options. Full explicit reordering rejects groups with hidden siblings.
- Passed parent/position attributes are not trusted as current state. Pending
  dirty name/slug edits are intentional input and still persist. Returned source
  models retain caller identity and receive fresh attributes after service success.
  An eventual rollback of a consumer's outer transaction still requires refreshing
  its in-memory models, as with ordinary Eloquent writes.
- Sibling ties use the database's position/name/id ordering and collation.
  Metadata-only unchanged-parent edits preserve stored positions; structural
  operations normalize affected groups. An inside drop on an existing parent
  appends the source.
- Relevant cyclic/missing/foreign ancestor chains cause domain failure, without
  repair. Deletion rejects raw foreign children that would otherwise be promoted
  across taxonomies. Unrelated malformed imported components are not repaired or
  claimed valid. Whole-taxonomy deletion may remove malformed own terms.
- Save cancellation throws and rolls back; delete cancellation returns false
  without position maintenance. Post-delete observer failure rolls back cascades.
  There is no new schema constraint or observer rewriting raw imports.

### Verification

Required PHP regressions cover F0 ordering, current/stale inputs, same-parent and
inside behavior, cross-connection rejection, hidden ancestor/sibling integrity,
foreign/malformed structure, mid-maintenance rollback, observer cancellation,
taxonomy cascade rollback, authorization after entering the lock transaction,
and expansion only after outer commit (discarded on rollback).

The explicit **composer test:concurrency** gate runs actual package migrations in
a uniquely named disposable database. Independent PHP processes preload stale
models; stdin/JSON acknowledgements coordinate execution. Six conflicting cases
observe actual InnoDB lock waits before release. An additional case demonstrates
progress on a different taxonomy while the first taxonomy lock is held.

MySQL **8.4.11 / InnoDB / REPEATABLE READ**: seven scenarios pass — competing
creates, creates under an old snapshot, fresh no-op return under an old snapshot,
collectively cyclic reparent attempts, deleted target, taxonomy cascade versus
create, and independent taxonomy progress. See tests-concurrency/README.md.

SQLite remains the functional test backend. The MySQL lane is explicit and not
yet automated in CI (F5). PostgreSQL, alternate engines/connections, arbitrary
writers bypassing this lock discipline, and all external dependency/OS matrix
combinations remain unverified. This is F2 evidence, not foundation acceptance.

Implementation-round local gates: **210 required PHP tests / 813 assertions**, **14 JS tests**,
**seven MySQL concurrency scenarios**; PHPStan level 4, full Pint (78 files),
strict Composer validation, and whitespace checks pass. Four F0 ordering cases
were promoted; 31 additional required F2 cases were added. The remaining explicit
pending PHP failures are six (F3: four; F5: two). F4's JS keyboard case remains
pending. No browser/build changes were required for this PHP-only milestone.
The following review round supersedes the implementation-round status.

### F2 review round

Reviewed the full service/page/table diff, current-read and transaction boundaries,
F0 compatibility, F1 scope/policy behavior, all added/promoted tests, the MySQL
process harness, and contract documentation. Three runtime findings were
reproduced with failing behavioral tests and fixed immediately:

- Keyed reorder input could persist array keys as positions, including nonnumeric
  keys. Reordering now derives consecutive positions from the input values.
- A visibility scope could duplicate joined rows and make the visible row count
  match a group containing hidden terms. Reordering now compares the complete
  unique visible ID set, using a qualified key column. Both hidden and fully
  visible duplicated-row scenarios are covered.
- The managed native taxonomy delete action left its original record marked as
  existing. It now updates that record's existence state after successful managed
  deletion, preserving native state for consumer after hooks.

The concurrency harness now checks worker exit status against its reported result
and cleans up an already-started first worker if the second worker cannot start.
Seven coordinated MySQL scenarios pass with these checks enabled.

Final review gates: **215 required PHP tests / 825 assertions**, **14 JavaScript
tests**, and **seven MySQL 8.4.11/InnoDB scenarios** pass. PHPStan level 4,
full Pint (78 files), strict Composer validation, and whitespace checks pass.
Five review regressions were added; all 36 new F2 required cases and four
promoted F0 cases pass. The remaining F3/F4/F5 specifications and the F5
automated database lane retain their documented scope.

No unresolved F2 finding remains. The user authorized commit/push once the review
and corrections passed; that condition is satisfied.

### F2 CI compatibility correction — 2026-10-02

The pushed F2 revision (f012ec9) failed all 16 Laravel 11/12 matrix jobs;
the eight Laravel 13 jobs passed. Both duplicated-join visibility cases used
Eloquent's newer orWhereKey() helper in their test scope. Laravel 11/12
reported an undefined method before either behavioral assertion ran.

The test now uses orWhere() with the model's qualified key column.
Both visibility cases and their assertions remain unchanged; runtime code,
dependencies, and CI configuration are unchanged.

The original failure was reproduced in isolated Laravel 11/12 installs using
the workflow's prefer-lowest dependency commands. With the correction, each
full suite passes: 215 tests / 819 assertions. The current Laravel 13 suite
passes: 215 tests / 825 assertions. All local runs used PHP 8.3 on Ubuntu WSL.
Changed-file Pint and whitespace checks pass. These local runs do not establish
the PHP 8.4 or Windows results; the new GitHub matrix will verify those.

## F3 implementation and verification — 2026-10-02

### Implementation plan and critical decisions

Reviewed F0's validation/constraint contracts, F1's scoped authorization,
F2's current-read/rollback boundary, native Filament validation/persistence hooks,
Laravel database exception diagnostics, and the four pending slug reproductions.
The original four cases were rerun: each failed with a database uniqueness
exception before implementation.

1. Use native unique rules: global taxonomy slugs; term slugs within the owning
   taxonomy. Ignore only the server-resolved edited model. Check hidden rows too,
   matching the database constraint; keep all migrations unchanged.
2. Keep current parent membership/cycle validation in F2's service. At the form
   boundary, accept nullable/empty root state and positive integer/string IDs,
   reject malformed input, and resolve the scoped parent again under the lock.
3. Convert parent domain failures to the mounted form's parent_id field.
   Missing/scoped source records and denied authorization retain F1 behavior.
   Expected move errors use the move error bag; expected deletion failures use
   native failure notifications. The service retains domain/database exceptions.
4. Convert only recognized SQLite/MySQL slug constraint failures to the relevant
   slug field. Match the written table and exact driver constraint diagnostics,
   never interpolated SQL/user bindings. Unknown indices, driver formats, other
   tables, primary-key failures, and unexpected persistence failures propagate.
5. Promote the four F0 slug cases and organize focused coverage by slug
   validation, late conflicts/retries, diagnostic recognition, parent input,
   and action feedback. Reuse the existing disposable MySQL gate.

Implementation review reproduced boolean-to-term coercion by Filament's default
select cast. The internal TaxonomyParentIdCast preserves invalid types as invalid
field input rather than silently selecting a term. Positive IDs are deliberately
normalized at resolveParent; root clearing no longer depends on empty().
Integer-valued JSON numbers reach PHP as integers; fractional numbers, booleans,
arrays, zero, negatives, overflow, and malformed strings are rejected.
No new slug formatting restrictions or automatic observer retry were introduced.
Package-wide message localization remains F4/F5; slug conflicts reuse Laravel's
translated unique validation message.

### Verification and scope

- Required PHP: **267 passed / 1145 assertions**, PHP 8.3 / Laravel 13 / SQLite.
  Four pending slug cases promoted; 48 additional F3 cases required.
- Full required JavaScript: **14 passed**.
- PHPStan level 4: no errors; full Pint: **84 files pass**.
- Composer strict validation and whitespace checks pass.
- MySQL **8.4.11 / InnoDB / REPEATABLE READ**: existing seven contention scenarios
  pass, including six observed lock waits. Four additional actual driver
  constraint checks pass (taxonomy/term create/edit), with field association and
  unchanged stored data. These four are constraint checks, not concurrency cases.
- Native stateCast/StateCast/Select/unique integration was checked against official
  Filament v5.0.0 source. This does not establish the full minimum-dependency matrix.
- Corrected retries after actual post-validation SQLite conflicts pass. The tests
  force the conflict through a saving observer; they do not claim parallel
  Filament requests. Post-validation parent invalidation uses a query hook and
  proves rollback/current checks, not independent-process parent contention.
- Remaining explicit pending PHP: **two F5 config failures**, rerun and confirmed.
  The F4 keyboard-root JS specification remains pending.
- The task's disposable MySQL container/data were removed. No hierarchy service,
  schema, dependency, workflow, JavaScript, or generated asset change.
- Local implementation verification is complete. The separate user-requested
  milestone review round and F3 commit/push have not happened yet. F4 is next
  after that review.

Recognized race-error translation deliberately covers the package's standard
SQLite/MySQL tables and constraints. Custom names/prefixes and other engine
diagnostics remain original database exceptions rather than guessed field errors.
Browser/assistive technology verification remains F4; external CI for the
uncommitted F3 revision is unverified.

### F3 review round — 2026-10-02

Reviewed the full runtime/form/action/view diff, promoted/new tests, MySQL runner,
F0 contracts, F1 authorization/scoped identity boundaries, and F2 current-read/
rollback behavior. The review found one additional runtime issue:

- Action-record lookup still cast arbitrary input to int. Numeric suffixes,
  fractions, booleans, and arrays could resolve to an existing term rather than
  fail. Reproduced eight failing cases with the original cast. Both action source
  and form parent lookup now reuse positive integer/string normalization. Invalid
  sources retain the missing-record response; invalid parents retain field errors.
  Valid string IDs and the independent policy/visibility boundaries still work.

Added 16 action-identity regressions and two cross-endpoint movement retry cases.
The latter already passed and required no runtime change. The Livewire test
client must have exception handling configured before construction; the new
missing-record tests follow the existing suite's setup. No other F3 finding
remains unresolved.

Final review gates:

- Current PHP 8.3 / Laravel 13.33.0 / Filament 5.8.4 / Livewire 4.4.6:
  **285 tests / 1200 assertions pass**.
- Isolated workflow-style prefer-lowest installs on PHP 8.3/Ubuntu WSL:
  **285 tests / 1194 assertions pass** in each Laravel 11/12 lane.
  Actual resolved versions: Laravel 11.x-dev/Testbench 9.13.0 and Laravel
  12.69.0/Testbench 10.2.0; both use Filament 5.7.6/Livewire 4.3.4.
  These results establish those installs, not every minimum allowed version.
- Required JS: **14 pass**. PHPStan level 4, full Pint (**85 files**), strict
  Composer validation, and whitespace checks pass.
- MySQL 8.4.11/InnoDB: **seven concurrency scenarios plus four slug constraint
  checks pass**. Six actual lock waits are observed. A temporary-container
  readiness error was resolved by waiting for its final TCP listener, not the
  initialization socket; no package change was needed.
- Disposable MySQL server/data and both isolated dependency copies removed.
  Main dependencies and workbench storage remain intact.
- Four F0 slug cases promoted; 66 additional F3 cases required (70 total F3 cases).
  Intentional later specifications remain two PHP F5 config cases and the F4
  keyboard-root JS case. Localization and browser accessibility remain F4/F5.
- F3 review is complete and local gates pass. Following the user's established
  milestone procedure, proceed to commit/push on 5.x. Remote CI for that revision
  is a separate verification result to record after pushing.


## F4 implementation and review — 2026-10-02

The internal parent selector now uses TaxonomyParentField (native Field lifecycle),
not Select. TaxonomyParentSelect remains the scoped factory; nullable integer,
scoped membership and unavailable self/descendant validation remain required.
This is an internal parent-only API, not the future assignment field.

Root participates in tree keyboard navigation. Opening exposes the current
selection; focus and selection remain independent. Search/collapse recover visible
focus; unavailable branches can be traversed and expanded. Whole disabled and
read-only controls cannot select/expand. Reactive configuration is read from a
morphed data attribute; listeners/observer are disposed on Alpine destruction.
Nested groups and sibling metadata expose hierarchy; unavailable nodes have
reasons and no aria-selected. Labels are escaped, translated per key with fallback,
and missing selections are described as unavailable rather than root.
Popup height/direction adapts to available space, with logical RTL indentation,
dark colors and reduced motion. Escape is intercepted only while the popup is open.

Review corrected per-key translation fallback, closed-popup Escape swallowing
the enclosing modal's Escape, and focus left inside a reactively blocked popup.
Fixture startup initially inherited normal workbench providers: two identified
task-created records (taxonomy 6, term 18; 05:54:55 timestamps) were removed after
read-only identity verification. The final fixture excludes workbench providers
and refuses any database except build/browser.sqlite.

Verification: full pre-final PHP suite 285/1200 passed; the additional translated
Field regression passes (5 parent-field cases / 34 assertions). Node: 18 pass,
including the promoted F0/F4 root regression. Chromium: seven real Filament/
Livewire cases, including two fields/repeater, reactive updates, validation,
modal remounts, keyboard clearing, unavailable states, escaped labels and narrow
RTL/dark layout. Axe and accessibility snapshots check hierarchy semantics.
PHPStan level 4, Pint and whitespace checks pass; compiled asset rebuilt.

Limit: no actual NVDA/VoiceOver session is available in this execution environment.
Automated accessibility checks are not claimed as that evidence. The concrete
human acceptance checklist is in tests-browser/README.md and remains required
for F6 acceptance. F4 implementation/review is complete, with that manual
accessibility gate outstanding. F5 begins next. Both commits stay local per user.

## F5 implementation and review — 2026-10-02

### Reviewed plan and decisions

Align the existing config/provider/publish tag; retain an optional empty reserved
config without invented settings. Generate ordinary ignored workbench storage
before discovery and commit the portable Testbench definition. Preserve existing
workbench data by running migrations rather than rebuilding the database.
Remove nonfunctional scaffold surfaces and document the compatibility effects.

Export runtime files and verify a real copied archive in a fresh Laravel consumer.
Keep compiled assets committed; compare rebuilt bytes from locked Node dependencies.
Add focused browser/consumer and MySQL lanes alongside the PHP matrix, including
PHP 8.2 with Laravel 11/12. Use read-only formatting checks. Improve touched node
shape types and remove the empty PHPStan baseline.

Critical plan review kept browser tools development-only, the internal parent
field separate from future assignment APIs, and database fixtures disposable.
Every database/browser gate must fail when unavailable or broken; no silent skips
or zero-status smoke failures. Verification uses published assets and the installed
archive, not a symlink to the source checkout.

### Changes

- Canonical config/filament-taxonomies.php is registered, merged and publishable.
  The installer publishes config/migrations and offers migration execution.
  Both F0 config specifications are required; no pending foundation tests remain.
- Ordinary workbench/storage directories are generated and ignored. The tracked
  absolute symlink is removed. Committed testbench.yaml runs migrations without
  db-wipe/migrate-fresh and uses the workbench storage path.
- Removed the fake command, empty facade target/alias and empty testing mixin.
  Runtime migrations/views/translations/CSS/compiled JavaScript ship; test harnesses,
  workbench, local documents, Node tooling and development scripts are export-ignored.
- Added byte-reproducible bundle, Node, real parent-field browser, fresh consumer
  and MySQL workflows. PHP tests configure 32 valid combinations; PHPStan retains
  six stable PHP 8.3/8.4 lanes. Formatting cannot commit/push changes.
- Documentation records actual configuration, support lanes, Filament 5 theme
  sources, asset publication after updates and removed scaffold behavior.

### Review findings corrected

The fresh-consumer harness used an incorrect drop-position enum argument and
placed the Vite manifest under a duplicated public path. Both were corrected.
An uncaught smoke error could be printed by Laravel's handler while returning
status zero; the entire smoke run now catches Throwable and explicitly exits one.
Browser assertions now follow the actual movement permission contract and native
alertdialog deletion semantics. Repeated mouse-driven movement is verified after
Livewire rerenders.

Final Composer archive inspection found ignored local build/dependency/cache files
were still exported. Explicit export-ignore rules now exclude those paths and the
distribution gate checks both Git and Composer archives before consumer installation.

Workflow review corrected stale badge destinations, uploads both browser trace
directories, removed a nonexistent PHP 8.2 exclusion from the PHPStan matrix, and
made the existing release changelog workflow's required credential persistence
explicit with its scoped security-audit annotation. Its release behavior is retained.

### Local verification

- Current PHP 8.3.33 / Laravel 13.33.0 / Filament 5.8.4 / Livewire 4.4.6:
  **288 required tests / 1210 assertions pass**.
- Node 24.21.0: **18 tests pass**; all three committed bundles reproduce byte for byte.
- Chromium: **seven parent-field browser tests pass** after provider cleanup.
- Both Git and Composer archives pass runtime-content/exclusion inspection. The
  Composer archive contains 52 entries, excluding populated build/vendor/Node/cache
  directories. The strengthened distribution gate and fresh-consumer browser pass.
- Staged archive creates a fresh Laravel 13.34.0 / Filament 5.9.0 / Livewire 4.4.7
  consumer with copied package contents. The real installer/publish tags, migration,
  config, CSS and both Alpine assets work. CLI managed create/reparent/reorder/delete
  promotion and taxonomy CRUD/policies pass. The native browser scenario passes
  term create/edit/delete, parent selection, denied controls and two drags separated
  by Livewire updates.
- Fresh Ubuntu checkout Composer install/discovery and config regressions pass.
  Native Windows PHP 8.3.35 storage preparation passes against a fresh checkout.
  This is not a claim of a full native Windows dependency/test run.
- MySQL 8.4.11/InnoDB/REPEATABLE READ: **seven independent-process concurrency
  scenarios and four actual slug constraint cases pass**; six real lock waits.
  The task's temporary MySQL container was removed.
- PHPStan level 4, Pint (94 files), strict Composer validation, workflow actionlint
  1.7.12 and offline zizmor 1.30.1 pass. Zizmor retains existing scoped suppressions.
  No unreviewed new workflow finding remains.

F4 is locally committed in 8e2e44f. F5 implementation/review is complete and ready
for its local commit. Neither milestone is pushed, as requested. New remote CI
lanes are configured and locally exercised as described; their GitHub run and
the actual human screen-reader checklist remain F6 acceptance work. PHP 8.2
functional lanes were not executed locally. No future assignment API is introduced.
