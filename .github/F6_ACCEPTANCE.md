# F6 foundation acceptance

Recorded 2026-10-02 on `5.x`: checkpoint `6a606cb` contains both F0–F5 review campaigns
and the F6 implementation. The subsequent F0–F6 review corrections are included
in this refreshed evidence. **Local implementation and verification are complete.
Full F6 acceptance remains open for human screen-reader evidence and the new
remote CI matrix.** The subsequent review corrections are uncommitted; no push occurred.

Related evidence: [foundation contracts](FOUNDATION.md),
[two review campaigns](F6_REVIEW.md), [raw performance samples and final source
fingerprints](f6-performance.json), [measurement reproductions](../tests-performance/README.md).

## Plan and critical review

Measure native management, parent selection, serialization, descendants and
sidebar on guarded fixtures. Optimize only demonstrated costs, preserve scoped
authorization under the mutation lock, add read-only import diagnostics, derive
practical budgets, verify distribution and update the roadmap/context.

The plan rejects arbitrary schema caps, cross-user/persistent caches, generic
repositories/DTOs, an external select package and timing assertions on shared CI.
A larger interactive editor needs separately designed rendering/search work.

## Environment and method

AMD Ryzen 5 2600, six cores / 12 logical CPUs; Ubuntu under WSL2, Linux
6.18.40.1-microsoft-standard-WSL2; PHP 8.3.33, Laravel 13.33.0, Filament 5.8.4,
Livewire 4.4.6, Testbench 11.3.0, SQLite 3.46.1, Node 24.21.0; Chromium
153.0.8010.12 at 1280×720. Xdebug is off. The single PHP development server uses
an isolated Testbench app with `app.debug=true`, not a production load setup.

Server reads discard one warm-up and retain three samples. Page values below
are medians of three loads with ordinary browser caching; each search/navigation
action has one sample including Playwright input/assertion overhead. “DOM ready”
means the expected row count/control is present, not full interaction-to-paint.
SQL headers count after bootstrap; server time includes bootstrap/rendering.
HTML is decoded response size; tree/config bytes are JSON, not gzip or Livewire
snapshot sizes.

Broad fixtures: ten roots, depth two at 100 terms and three at 1,000. The combined
practical fixture has 25 four-level chains (100 terms); the narrow stress fixture
is one 32-level chain. Root is level one. There are 100 additional sidebar
taxonomies. Baseline has three fixture taxonomies, final has four: 106 versus 107
rendered sidebar items including native navigation. Auto-increment IDs differ
and also affect encoded byte counts. These differences and small samples preclude
exact speedup/statistical claims.

Final server/browser measurements ran after the complete F0–F6 review fixes without competing
test/build jobs. The artifact fingerprints identify the measured runtime source.

## Final measurements

Paired page values mean **management / parent field**. MB is decimal.

| Fixture | SQL queries | Server ms | DOM ready ms | HTML MB | DOM elements |
| --- | --- | --- | --- | --- | --- |
| broad-100 | 3 / 6 | 273 / 88 | 1356 / 503 | 1.45 / 0.45 | 4811 / 1686 |
| broad-1000 | 3 / 6 | 2086 / 216 | 11335 / 1452 | 11.99 / 1.98 | 37481 / 6376 |
| mixed-100-depth4 | 3 / 6 | 267 / 91 | 1474 / 520 | 1.44 / 0.46 | 5009 / 1745 |
| deep-32 | 3 / 6 | 147 / 83 | 794 / 412 | 0.65 / 0.35 | 2429 / 1389 |

| Fixture | Matching search ms | No-match ms | Clear search ms | End navigation ms |
| --- | --- | --- | --- | --- |
| broad-100 | 138 | 132 | 157 | 74 |
| broad-1000 | 612 | 483 | 665 | 244 |
| mixed-100-depth4 | 129 | 139 | 133 | 80 |
| deep-32 | 95 | 113 | 94 | 64 |

Home restores root focus after End. No-match leaves root available; matching
leaves preserve ancestors. Sidebar item counts remain complete across all pages.
Required visibility tests separately cover record policies, joined scopes and
badges. Native browser tests cover pointer/keyboard state, reactive configuration,
modal lifecycle, repeated/multiple fields and narrow/RTL presentation.

| Fixture | Tree queries / JSON bytes | Parent config queries / JSON bytes | Sidebar queries / ms | Diagnostics queries / ms |
| --- | --- | --- | --- | --- |
| broad-100 | 1 / 15772 | 3 / 10625 | 1 / 12.2 | 1 / 0.3 |
| broad-1000 | 1 / 158782 | 3 / 108675 | 1 / 11.6 | 1 / 1.5 |
| mixed-100-depth4 | 1 / 15713 | 3 / 10801 | 1 / 11.8 | 1 / 0.3 |
| deep-32 | 1 / 5015 | 3 / 6969 | 1 / 11.6 | 1 / 0.2 |

The edited term is the first root, exercising disabled descendants. Diagnostics
on these valid fixtures return an empty array. Public `getDescendantIds()`
retains scoped per-level queries: 2 / 3 / 4 / 32 in the final fixture order.
It is no longer needed to disable options in an already loaded parent tree.

## Changes justified by evidence

- Row action rendering previously resolved each term twice: **203 / 2,003 / 67**
  page queries for broad-100 / broad-1000 / deep-32. Final management uses **three**.
  Native action invocation clones with mounting arguments, then applies a policy
  response for the loaded visible row. Original mounting/submission callbacks
  still resolve fresh scoped records; mutations recheck permissions under lock.
- Parent configuration previously required **5 / 6 / 35** queries; now **three**
  regardless of depth. Self/descendant flags follow loaded ancestor paths.
  Available-ID validation and fresh service validation remain intact.
- At 1,000 terms parent DOM readiness moved from about **4.37 s to 1.45 s**.
  Matching/no-match/clear search moved from **1.60 / 1.23 / 4.42 s** to approximately
  **0.61 / 0.48 / 0.67 s**. Component-local memoization and set lookups avoid
  repeated full-tree scans per visible node. Search, expansion and configuration
  changes invalidate reuse; separate instances have separate caches.
- Management at 1,000 terms remains about **11.3 s** (baseline 11.7 s), with roughly
  37,500 DOM elements and 12 MB decoded HTML. Query reduction cannot eliminate
  its complete-tree rendering cost.
- Sidebar already uses one query and approximately 11–13 ms for 104 taxonomies.
  Measurements do not justify another cache/index. Custom policies/scopes can
  add SQL; deterministic regression budgets describe the default package path.

## Practical scope and budgets

Plan around **100 terms per taxonomy, up to four levels, and approximately 100
sidebar taxonomies** on the measured desktop setup. The combined 100-term,
four-level fixture is exercised explicitly. This is conservative operating
guidance, not a database limit, enforced cap or guarantee for every device/theme.
Long labels, narrow screens, remote databases and policy queries need consumer
measurements.

1,000 terms is a stress case, outside the responsive editor claim. A 32-level
chain completes mechanically but indentation is unsuitable for normal editing.
Larger/deeper interactive trees need separately designed rendering/search.
No arbitrary-depth, 10,000-term, mobile-performance, PostgreSQL or multi-connection
concurrency guarantee is added. The standalone diagnostic's valid 1,000-level
test does not establish a corresponding UI depth limit.

## Raw-import contract

`TaxonomyTreeService::diagnoseTree($taxonomy)` makes one unscoped structural read,
without writing. Results are ordered by ID and contain `term_id` and `reason`:

- `cycle`: the ancestor chain reaches a cycle.
- `missing_or_foreign_parent`: it reaches a parent outside this taxonomy's stored
  rows. The cases are combined without querying/exposing another taxonomy.
- `invalid_identity`: a term or ancestor reference has a nonpositive or unrepresentable native integer key. Oversized IDs
  remain exact decimal strings in results.

Affected descendants are reported too. Callers must authorize inspection of the
**entire taxonomy** before exposing results: scoped-out rows are deliberately
included. The owning taxonomy must be persisted on the supported default
connection. This is a snapshot diagnostic, not an import transaction, permission
checker, automatic repair, slug validator or position normalizer. Normal scoped UI
queries never call it.

Normal trees contain rooted visible branches only. Hidden ancestors are not
misdiagnosed as structurally missing. Orphans/cycles stay omitted; nonpositive
identities are omitted, and a separate root key prevents zero-parent promotion
or zero-ID root recursion. Managed operations reject nonpositive source/destination
and ancestor identities. Empty display now says “No terms are available in this
tree,” without asserting that no stored rows exist.

SQLite tests cover self/multi-node cycles, affected descendants, missing/foreign
parents, hidden valid ancestors, a valid 1,000-level import, zero-parent orphans,
stored zero identities, rejected malformed mutations and unchanged data.
MySQL also verifies cycle/foreign/zero-parent imports, hidden structural inspection
and unchanged rows, including unsigned integer boundaries.

The browser supports positive term IDs through 9,007,199,254,740,991. Backend
managed IDs use PHP’s positive native integer range. Unsupported imported
branches are omitted from browser controls; they are never promoted to roots.
Generated term keys at the native ceiling are rolled back, guarding driver
overflow. Larger browser identities require future lossless string transport.

## Review findings fixed

1. Harness schema initialization, tree traversal order and Chromium body-cache
   limits were corrected before measurements. Decoded-byte headers avoid inspector
   cache eviction. Measurement/setup exceptions explicitly return exit one.
2. Manual action cloning omitted Filament's invoked arguments. The fresh consumer
   reproduced a row-edit 404. Native invocation is restored, with a rendered-handler
   regression proving each edit/delete button retains its own term ID.
3. Root sentinel zero promoted corrupt zero-parent rows and could recurse on a
   zero-ID root. The root key is separate and diagnostics report invalid identities.
4. A positive child below a zero-ID ancestor could be reparented. Its regression
   failed before the ancestor guard and passes afterward without stored changes.

5. PHP casts and JavaScript Number conversion could alias imported large IDs.
   Raw identity validation now precedes managed lookup; structural snapshots and
   diagnostics preserve physical keys. MySQL verifies unsigned imports, parent
   references, unchanged neighbours and generated-ID overflow rollback. Browser
   term controls enforce JavaScript’s exact integer range and omit unsupported
   branches instead of serializing rounded IDs. A Livewire regression rejects a
   rounded ID and checks that the neighbouring record stays unchanged.

Final review covers cache lifetime/invalidation, native mounting, fresh scoped
authorization, locks/rollback, diagnostic termination, escaped labels, archive
exclusions and disposable fixture boundaries. Two subsequent review passes found no further actionable issue:
the first checked raw key preservation, affected sibling/ancestor validation,
rollback and scoped lookup; the second checked native action mounting,
cache invalidation, browser identity transport, fixture isolation and archive
contents. The parent factory also validates the owning raw taxonomy identity
before lookup, preventing an unsigned owner from aliasing a supported record.

## Local verification

| Gate | Result |
| --- | --- |
| PHP | **359 cases / 1,442 assertions**, PHP 8.3 / Laravel 13 |
| Node | **21 cases**, including visibility reuse/invalidation |
| Parent browser | **9 native Chromium cases**, axe/AX snapshots; not human AT |
| Scale browser | All four isolated fixtures pass; raw samples retained |
| MySQL | **7 independent-process scenarios**, six observed waits, **4 slug constraints**, joined visibility and import diagnostics |
| Distribution | Git + Composer archives; copied fresh consumer CLI and native CRUD/parent/policy/repeated-drag browser scenario |
| Static/style/build | PHPStan level 4; Pint **108 files**; strict Composer; all three bundles reproduce |
| Workflows | actionlint 1.7.12; offline zizmor 1.30.1, no findings, 24 existing scoped suppressions |

Logs: ignored `build/f6-acceptance-*.log` and `build/performance-*.json`.
Archives used the temporarily staged working tree; the index was restored.
The task-owned MySQL container was removed. Fixtures did not use workbench data.
The final PHP/static/style and fresh-consumer gates ran after all review fixes.

## Remaining acceptance gates

- [ ] Actual NVDA/Firefox or VoiceOver/Safari session using the
  [human checklist](../tests-browser/README.md), with versions/findings/fixes
  recorded. Chromium, axe and AX snapshots cannot close this item.
- [ ] After publication is authorized, inspect the new GitHub matrix on the accepted
  commit: lowest/stable dependencies, PHP 8.2 and native Windows included.
  Local WSL testing and workflow linting do not establish that evidence.

F4/F5 and checkpoint 6a606cb are three existing unpushed commits. Subsequent
review corrections remain uncommitted. P1 assignment/public-field work remains behind F6 acceptance
unless the user explicitly changes its scope.


## Whole-foundation review follow-up

The [review ledger](F6_REVIEW.md) records two final clean passes per F0–F6
milestone and four additional corrected failures: joined pagination, ambiguous
table search/sorting, reader global-search destinations, and deferred parent
initialization after destruction. Measurements above include those corrections.

Each isolated Laravel 11/12 prefer-lowest install passes 359 PHP tests / 1,436
assertions on PHP 8.3/WSL. The current Laravel 13 stack passes 359 / 1,442.
Laravel 11's tested dependency set reports 19 advisory entries across four
packages; the current lock and tested Laravel 12 set report none in this dated
check. Legacy compatibility remains explicit, and maintained versions should be
used for new consumers. No new PHP 8.2/native Windows or human AT evidence is
claimed. Review logs use build/f0-f6-review-*.
