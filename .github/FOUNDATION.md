# Foundation contracts and reproductions

Decision date: 2026-10-01. Reviewed source baseline: `5.x`, `2ec2cea`.

This contributor document records F0's decisions and executable reproductions. **F1 implements the management permission and query visibility contract. The remaining target contracts are requirements for following milestones, not guarantees already implemented.** The behavior and test status below distinguish the two. The local, gitignored `ROADMAP.md` holds the complete implementation sequence.

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
| Laravel 11 | 8.2–8.4 | PHP 8.3/8.4, Testbench 9 |
| Laravel 12 | 8.2–8.5 | PHP 8.3/8.4, Testbench 10 |
| Laravel 13 | 8.3–8.5 | PHP 8.3/8.4, Testbench 11 |

The configured PHP matrix runs lowest/stable dependencies on Ubuntu/Windows; static analysis uses stable dependencies. Configuration is not proof that the latest external run passed. PHP 8.2 and 8.5 are outside the current package CI matrix. Solver acceptance and upstream compatibility do not establish package verification. F5 will reconcile the declared range and tested support without casually breaking current consumers.

Laravel 11 reached upstream security end of life on 2026-03-12. Retain its existing compatibility lane during the foundation pass; document it as legacy compatibility. Use maintained Laravel 12/13 for new consumer examples. Any removal needs explicit upgrade/versioning treatment. [Laravel's support table](https://laravel.com/framework/docs/13.x/releases#support-policy) supports these upstream ranges and dates.

Current local evidence: Ubuntu under WSL, PHP 8.3.33, Filament 5.8.4, Laravel 13.33.0, Testbench 11.3.0, Pest 4.7.8, SQLite in memory with foreign keys enabled, Node 24.21.0. Other local PHP/framework/OS combinations were not run for F0.

**Database decision:** SQLite remains the fast functional test backend. The first production concurrency target is MySQL 8.4 with InnoDB on a single default connection. F2 must prove transaction/locking behavior with independent connections; F5 adds that CI lane. This selection is an implementation target, not a newly verified support claim. PostgreSQL, alternate storage engines, multiple connections, and arbitrary raw writers have no concurrency guarantee from this review.

## Hierarchy contracts

### Managed writes

The target invariant boundary is the supported tree service and package management actions. Application authorization belongs at the UI/integration boundary; the domain service enforces hierarchy and persistence rules without assuming an authenticated HTTP request.

Raw SQL, query-builder writes, relationship `create()`, model `save()/delete()`, and third-party imports remain possible. Their database foreign keys/unique constraints still apply, but they do not receive automatic cycle checking, position normalization, or coordinated locking. Do not add global model observers that silently rewrite imports. Importers should use the managed service or explicitly validate their results.

Today, the page directly creates/deletes terms, `setParent()` saves independently, and movement uses another transaction path. F2 must unify those paths before the managed-write guarantees can be advertised.

### Parents, ordering, and mutation results

- Taxonomy/term keys remain positive integers. At the form boundary, null/empty-string parent state means root; valid numeric strings normalize to positive IDs. Zero, negatives, fractions, and malformed IDs must be rejected, not treated as root. Services retain their typed model/ID arguments.
- A parent belongs to the same taxonomy. A term cannot have itself or a descendant as its parent. Reparenting preserves the entire descendant subtree.
- A sibling group is identified by `(taxonomy_id, parent_id)`; `null` means roots. After a managed mutation, affected groups have contiguous integer positions starting at zero.
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

Example: roots `[A, B, C]`, with children `[X, Y]` under B, become roots `[A, C, X, Y]`, positions `[0, 1, 2, 3]`. This deliberately avoids interleaving positions copied from a different group. Database `nullOnDelete()` currently promotes children but does not implement this ordering.

Deleting a taxonomy continues to cascade its terms and preserve other taxonomies. Its managed path must coordinate with F2's taxonomy locking discipline.

### API compatibility and events

Retain existing signatures, return types, and domain exception classes for `canSetParent()`, `setParent()`, `moveTerm()`, `moveRelativeTo()`, `reorderSiblings()`, `getTree()`, `getDescendantIds()`, and `getNextPosition()`. No deprecation is needed for F0.

`setParent()` must continue saving a caller's pending name/slug edits; replacing it with a freshly loaded model must not discard those changes. `getNextPosition()` stays a read helper, not an atomic reservation or concurrent-create API. Future create/delete entry points are additive and must be used by the page.

Existing position writes use bulk updates and do not emit one Eloquent save event per sibling. Preserve/document that behavior; do not imply otherwise. Emit UI success/expansion feedback only after successful writes. F2 must decide stale-model handling and retries inside the coordinated transaction.

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
- The current keyboard reproduction exercises the existing navigation model and real focus helper with minimal scheduling/focus stubs. It does **not** prove real DOM or assistive-technology behavior. Manual reproduction: open an edit form with a parent selected, open the selector, try to reach No parent from search with Tab; the current ancestor handler closes the popup. F4 must test real focus and correct Tab handling.
- Keep the advertised `filament-taxonomies-config` tag. Canonical file/key: `config/filament-taxonomies.php` / `filament-taxonomies`, matching provider/package naming. F5 must align the existing differently named source, merging, publishing, and README together.
- Config tests prove the booted provider's missing registration/merge. They do not replace a fresh Composer consumer installation. The tracked absolute `workbench/storage` link remains a documented F5 portability task.

## Executable reproductions

Required passing tests live in `tests/Feature` and `tests-js/*.test.js`. Pending future contracts live in `tests/Pending` and `tests-js/pending`; they assert the desired behavior and deliberately fail today. They have no skipped tests, inverted bug assertions, or expected-failure flags. The PHPUnit default suite explicitly excludes only `tests/Pending`; an explicit path runs them.

```bash
# Required PHP suite; --no-coverage avoids needing an enabled coverage driver.
composer test -- --no-coverage --ci
npm run test:js

# Pending specifications: nonzero exit is expected until their owner milestone is implemented.
composer test:pending
npm run test:js:pending

# Focus on the next milestone.
vendor/bin/pest --no-coverage tests/Pending/HierarchyOrderingTest.php
```

| Owner | Specification | Cases | Status / reproduction |
| --- | --- | --- | --- |
| F1 — implemented | `tests/Feature/Resources/Taxonomies/TermAuthorizationTest.php` | 17 | Denials, revocation, allowed operations, and matching controls pass |
| F1 — implemented | `tests/Feature/Resources/Taxonomies/TaxonomyVisibilityTest.php` | 10 | Navigation, page/table/field denial, permission changes, and current-user navigation pass |
| F1 — implemented | `tests/Feature/Resources/Taxonomies/TaxonomyQueryVisibilityTest.php` | 19 | Model/resource scopes, hidden/foreign IDs, parent input, and hydrated manage/edit pages pass |
| F1 — implemented | `tests/Feature/Resources/Taxonomies/TaxonomyTermPolicyCompatibilityTest.php` | 17 | Filament defaults, strict mode, Gate callbacks, active guard, and create context pass |
| F1 — implemented | `tests-js/term-movement-permissions.test.js` | 3 | Drag capability changes, read-only destinations, and revocation before submission pass |
| F2 | `tests/Pending/HierarchyOrderingTest.php` | 4 | Service/edit source positions remain `[0,2]`; root/nested promotion ordering or positions fail |
| F3 | `tests/Pending/SlugValidationTest.php` | 4 | Create/edit taxonomy and term throw database uniqueness exceptions instead of field errors |
| F4 | `tests-js/pending/parent-tree.test.js` | 1 | Home/Enter selects the first term instead of clearing |
| F5 | `tests/Pending/ConfigPublishingTest.php` | 2 | Publish registry is empty; config key is absent |

When fixing a milestone, move its PHP tests into the relevant `tests/Feature/Services`, `Resources`, or installation group; move JS tests into `tests-js/*.test.js`. Keep or expand the assertions, run them in required CI, and update this table. The pending directory is temporary executable planning, not a place to leave unresolved requirements after a milestone is called complete. Avoid using `vendor/bin/pest tests` as a passing-gate command: that explicit directory includes pending tests.

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
