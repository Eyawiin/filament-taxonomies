# F0–F5 foundation review loops

Date: 2026-10-02. Baseline: 5.x at 40141f6, following local F4 8e2e44f.
Scope: the requested critical review/fix loops for each completed foundation
milestone. Changes remain uncommitted and unpushed. This review phase does not
declare every F6 acceptance item complete.

## Procedure

Re-read each milestone's decisions, implementation and behavior-focused tests.
Reproduce suspected bugs before fixing them. Review the revised implementation
again with another angle; reset the clean-pass count when a new finding appears.
Stop each milestone only after two consecutive passes with no further actionable
finding. Passing checks support the particular scenarios exercised; review passes
are not a proof that no undiscovered bugs exist.

## Loop ledger

| Milestone | Pass | Reviewed scope | Result |
| --- | --- | --- | --- |
| F0 | 1 | Hierarchy, deletion, event/write boundaries, policy defaults, compatibility and migrations against the recorded decisions | No new finding |
| F0 | 2 | Contract-to-regression mapping, promoted specifications, discovery/exclusions and raw-write limitations | No new finding; clean streak 2 |
| F1 | 1 | Action execution, direct endpoints, resource access, record view, active guard and contextual create policy | No new finding |
| F1 | 2 | Permission revocation, hydrated records, model/resource scopes, hidden/foreign IDs and rendered controls | No new finding; clean streak 2 |
| F2 | 1 | Reorder inputs and persisted source/parent/target identities across all managed entry points | Findings: unchecked ID casts and missing destination identity checks; fixed |
| F2 | 2 | Traversal over malformed cycles and visibility joins that duplicate rows | Findings: source included in its own descendants and repeated IDs; fixed |
| F2 | 3 | Ordering, metadata preservation, deletion promotion/cascade, canceled observers and rollback | No new finding |
| F2 | 4 | Current reads, default-connection discipline, independent-process contention and after-commit behavior | No new finding; initial clean streak 2 |
| F2 | 5 | Combined gate and regression quality | PHPStan found the overly narrow reorder PHPDoc; corrected. Strengthened array/root collision and unsaved-parent-metadata assertions |
| F2 | 6 | Final service diff, validation boundary typing, unchanged identities, advisory behavior and stale-model compatibility | No new finding |
| F2 | 7 | Final adversarial regression assertions, source/destination group integrity and static/functional verification | No new finding; final clean streak 2 |
| F3 | 1 | Raw Livewire movement inputs alongside action/form input normalization | Finding: PHP int coercion and unhandled argument TypeErrors; fixed |
| F3 | 2 | Scoped uniqueness, unchanged edit slugs, hidden constraint rows, post-validation conflicts, and narrow driver diagnostics | No new finding |
| F3 | 3 | Retry/error clearing, malformed arguments, rollback, source/destination visibility and valid integer strings | No new finding; clean streak 2 |
| F4 | 1 | Tree search and horizontal keyboard navigation | Finding: ArrowRight could enter an unrelated matching branch; fixed |
| F4 | 2 | Selection/focus separation, root clearing, unavailable branches, RTL, translations, escaped labels and nested semantics | No new finding |
| F4 | 3 | Reactive state/configuration, modal remounts, disposal, multiple instances and browser regression assertions | No new finding; clean streak 2 |
| F5 | 1 | Canonical config, provider/install commands, ordinary ignored storage, export contents and removed scaffold references | No new finding |
| F5 | 2 | Matrix validity, read-only formatting, byte-reproducible assets, workflow validation and fresh archive consumer | No new finding; clean streak 2 |

The final combined regression suite also rechecks F0/F1 authorization and hierarchy
contracts after the F2/F3/F4 changes. Existing manual/remote/scale acceptance gaps
are tracked below; they are not silently treated as completed checks.

## Fixes and reproductions

### F2: malformed reorder IDs

Casting list values to int accepted a suffixed or fractional ID. Nonempty arrays
can also cast to root ID 1. Reordering now validates positive integer/int-string
values before writes; malformed inputs raise InvalidTaxonomyOrderException.
Keyed arrays and valid integer strings remain accepted.

### F2: changed destination identities

Owners were checked for persistence and unchanged identity/taxonomy, but parent
and target inputs were not. A changed key could silently select another parent.
The same identity guard now applies to every resolved term and relative target.
Unsaved name/slug edits on a destination are not saved. Existing persisted stale
models still resolve current database state under the lock.

The initial identity/coercion reproduction produced 17 failing cases. Required
TaxonomyManagedIdentityTest covers malformed IDs, all five destination entry
points, all three identity changes, valid strings and pending-parent metadata.

### F2: descendant traversal

The visited set started empty, so a cycle returned the source as a descendant.
Visibility joins could repeat IDs. Seed the visited set with the source and
deduplicate each discovered level. Three required query cases cover self/multi-node
cycles and a duplicate-row join. The two cycle cases failed before the fix.
Traversal never rewrites imported malformed records.

### F3: direct movement arguments

Typed int Livewire parameters allowed PHP to truncate fractions or coerce booleans
before package validation. Arrays/null could produce unhandled TypeErrors.
The HTTP/Livewire methods now receive mixed inputs and explicitly normalize them.
Invalid IDs retain the missing-record response; invalid positions/placements
produce the existing movement error paths. Domain service signatures are unchanged.

The initial movement reproduction produced 21 failing cases. Required
TaxonomyMovementInputTest has 25 cases, including string IDs/zero positions and
non-string drop placements. Expansion compares resolved numeric parent keys.

### F4: horizontal navigation during search

The next flattened result could be an unrelated sibling when a branch's children
did not match. ArrowRight now chooses a visible immediate child, otherwise stays
on the current branch. A failing Node reproduction and required browser scenario
cover this case. Browser key steps await actual focus, avoiding fixture timing
assumptions. The shipped Alpine component was rebuilt.

## Verification

- Required PHP: 335 tests / 1320 assertions pass. Final full run is recorded in
  build/f6-php-review-final.log.
- F0 focused regressions: 11 cases / 56 assertions; F1 policy/scope and atomic
  integration selection: 68 cases / 258 assertions.
- Service suite: 111 cases pass. The final focused identity/query selection:
  26 cases / 60 assertions.
- F3 validation/feedback selection: 79 cases / 398 assertions.
- Node 24.21.0: 19 cases pass. All three bundles reproduce byte for byte.
- Chromium: eight parent-field cases pass, including axe/nested accessibility
  snapshots; no actual human screen-reader session was performed.
- MySQL 8.4.11 / InnoDB / REPEATABLE READ: seven independent-process scenarios
  and four actual slug constraint cases pass; six observed lock waits. The
  task-created server was removed.
- Both archive formats pass runtime file/exclusion checks. A copied-archive
  Laravel 13.34.0 / Filament 5.9.0 / Livewire 4.4.7 consumer passes installation,
  migrations, config/views/assets, managed CLI operations and the native browser
  CRUD/parent/policy/repeated-drag scenario.
- PHPStan level 4, Pint (96 files), strict Composer validation, actionlint 1.7.12
  and offline zizmor 1.30.1 pass. Existing scoped zizmor suppressions remain.
- Main environment: Ubuntu WSL, PHP 8.3.33, Laravel 13.33.0, Filament 5.8.4,
  Livewire 4.4.6. No new full PHP 8.2 or native Windows matrix result is claimed.
  F5's prior native Windows storage and clean Ubuntu setup evidence is unchanged.

## Remaining F6 acceptance work

- Real human screen-reader acceptance checklist in tests-browser/README.md.
- GitHub execution of the new CI lanes, after separately authorized push.
- F6's measured size/depth/query/render/search/sidebar budgets and raw-import
  orphan/cycle diagnostics. This request implemented the F0–F5 review loops;
  it did not establish those additional performance/diagnostic guarantees.

No migration, dependency, authorization default, external UI package, commit or
push was introduced in this review phase.

## Second independent review campaign — 2026-10-02

Requested after the first campaign. Reviewed the current working tree, including
all earlier fixes, against the same roadmap/contracts. New findings reset the
affected milestone's clean streak. The following are fresh review passes; the
first campaign's passing results are not reused as the second campaign's passes.

### Second loop ledger

| Milestone | Pass | Reviewed scope | Result |
| --- | --- | --- | --- |
| F0 | 1 | Managed versus raw writes, promotion order, pending metadata, cancellation/events and transaction ownership | No new finding |
| F0 | 2 | Roadmap-to-contract/specification mapping, migrations, discovery, random order and fixture/scope isolation | No new finding; clean streak 2 |
| F1 | 1 | Joined visibility scopes, record projections, route binding, parent options and execution lookup | Finding: ambiguous columns and overwritten identities; fixed |
| F1 | 2 | Joined scopes that multiply rows, tree identity uniqueness and navigation/table counts | Finding: inflated visible-term counts; fixed |
| F1 | 3 | Native access responses, active guard, contextual creation, revoked permissions and scoped hydrated records | No new finding |
| F1 | 4 | Joined native taxonomy CRUD, scoped term forms, custom route keys and shared aggregate callbacks | No new finding; initial clean streak 2 |
| F1 | 5 | Compatibility of the new resource projection with native model-configured aggregates | Finding introduced by pass 1: select replaced existing aggregates; fixed and clean streak reset |
| F1 | 6 | Additive projection, preserved aggregates, joined identities, policy defaults and record visibility | No new finding; focused 51-case group passes |
| F1 | 7 | Final native CRUD/cascade, complete randomized PHP suite and current copied-archive consumer | No new finding; final clean streak 2 |
| F2 | 1 | Fresh source/parent/target and owning-taxonomy identities under joined scopes | Shared F1 finding also affected mutations; service query boundaries fixed |
| F2 | 2 | Sibling normalization, stale metadata, hidden structural rows, event cancellation and rollback | No new finding |
| F2 | 3 | Current locked reads, actual MySQL contention, old snapshots and joined managed movement | No new finding; clean streak 2 |
| F3 | 1 | Raw ID/position/placement validation, state casting, uniqueness and deliberately narrow diagnostics | No new finding |
| F3 | 2 | Post-validation parent invalidation, retries, field feedback, authorization/missing-record boundaries and real MySQL slug errors | No new finding; clean streak 2 |
| F4 | 1 | Pointer disclosure followed by keyboard navigation and Tab exit | Finding: focus remained on the disclosure button; fixed |
| F4 | 2 | Read-only trigger semantics and reactive availability | Finding: read-only trigger did not announce its unavailable state; fixed |
| F4 | 3 | Selection versus focus, root clearing, search, disabled branches, RTL and reactive configuration | No new finding |
| F4 | 4 | Real browser focus/keyboard input, nested accessibility snapshots, axe, remounts and published component coherence | No new finding; clean streak 2 |
| F5 | 1 | Provider/config, non-destructive workbench setup, export exclusions, guard paths and failure exit status | No new finding |
| F5 | 2 | Final Git/Composer archives, fresh consumer installation/CLI/browser, bundle reproducibility and workflow gates | No new finding; clean streak 2 |

### New reproductions and fixes

**Joined columns and identities (F1/F2).** A valid visibility join to another
table with id/name/slug columns made descendant plucks ambiguous. Default
select-star projections also replaced a term's ID with the joined taxonomy ID:
tree construction recursed indefinitely and relative movement reported a false
cycle. Native taxonomy route binding used an ambiguous unqualified key.

Package-owned queries now qualify their columns and load the owning model's
columns. The taxonomy route-binding hook qualifies both ordinary and custom route
keys. Resource projections are additive, retaining native model aggregates.
Tree/navigation collections deduplicate repeated identities; scopes remain active.
The initial query reproduction had two failures (ambiguous ID and recursion);
the managed-write and taxonomy-page reproductions also failed before the fix.
Two post-lock query-hook tests now match the selected table and transaction
boundary rather than depending on the incidental select-star projection.

**Distinct visible counts (F1).** One visible term produced badge 2 when its
visibility join returned two rows. A shared native aggregate callback now counts
distinct qualified term keys in both navigation and the table. A hidden term
remains excluded. The regression failed before this correction.

**Preserved native aggregates (F1).** Final review caught the new resource select
guard dropping a consumer model's withCount result. A real model/resource
subclass reproduction returned null instead of 2. Using addSelect preserves
existing projections while adding taxonomy columns. This finding reset F1's
clean streak; two further clean passes followed.

**Disclosure focus (F4).** Chromium showed that clicking a branch's disclosure
left DOM focus on the button while the roving tree item changed. The click now
returns focus to that branch item before subsequent keyboard input. The required
browser reproduction failed before the fix and now passes.

**Read-only semantics (F4).** The trigger rejected opening but was announced as
an available button. It now exposes aria-disabled for blocked fields. Read-only
triggers remain focusable; native disabled triggers remain disabled. The reactive
browser case checks the advertised state and actual pointer/keyboard rejection.
Playwright input deliberately bypasses its aria-disabled actionability filter to
exercise that rejection; normal enabled input paths retain ordinary actionability.

### Final second-campaign verification

- **344 PHP tests / 1376 assertions pass**, random seed 1790929812.
  Final log: build/f6-second-php-final.log. Nine new required cases are added over
  the first campaign; joined resource cases have their own logical test file.
- **19 Node tests pass**; all three bundles reproduce byte for byte.
- **Nine parent-field Chromium cases pass**, including the new disclosure
  regression and strengthened read-only semantics, axe and accessibility snapshots.
  Log: build/f6-second-parent-browser.log.
- **MySQL 8.4.11/InnoDB/REPEATABLE READ**: seven independent-process scenarios,
  six observed waits, four actual slug constraint cases and the new joined-scope
  functional SQL check pass. Log: build/f6-second-mysql.log. The task's server was
  removed. The new SQL check is required in the existing MySQL workflow.
- Both archive formats pass runtime-content/exclusion checks. A new copied-archive
  consumer passes actual install, migrations/publishing, CLI managed operations
  and the native CRUD/parent/policy/repeated-drag browser scenario after the final
  projection fix. Versions: Laravel 13.34.0, Filament 5.9.0, Livewire 4.4.7.
  Log: build/f6-second-distribution-final.log.
- PHPStan level 4, read-only Pint (98 files), strict Composer validation,
  actionlint 1.7.12 and offline zizmor 1.30.1 pass. The 24 existing scoped security
  suppressions are unchanged.
- Main local environment remains PHP 8.3.33 / Laravel 13.33.0 / Filament 5.8.4 /
  Livewire 4.4.6 on Ubuntu WSL. No new PHP 8.2/native Windows/full remote matrix
  or human screen-reader result is claimed.

Each milestone finishes with two consecutive passes without further actionable
findings in its reviewed scope. All review changes remain unstaged, uncommitted
and unpushed; the two previous local milestone commits remain unpushed. The
remaining full F6 acceptance items listed above are still open.
