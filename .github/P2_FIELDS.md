# P2 — Reusable taxonomy assignment fields

## Implementation plan and review decisions

1. Reuse the strengthened parent selector's PHP projection, Blade view, Alpine
   navigation and published asset. Keep parent labels/cycle rules intact.
2. Provide public TaxonomySelect with explicit taxonomy resolution and native
   relationship hydration/saving. Single state is ID/null; multiple is a list.
3. Validate IDs/cardinality/membership/scopes/permissions before saving and again
   under the taxonomy lock. Never silently truncate unavailable selections.
4. Keep branch disclosure, focus and selection separate. Support independent
   multiple toggles, clear, search, RTL and reactive disabled/read-only state.
5. Use native outer create/edit/action transactions for whole-form atomicity,
   with sorted taxonomy locks before owner writes. Document custom-form ownership.
6. Deliver a local Deck resource and repeatable factories/seeder with two nested
   taxonomies so the public API can be exercised in a real backend.
7. Verify native create/edit, rollback, authorization, malformed/stale IDs, unrelated
   taxonomies, relationship and JSON repeaters, modals, copied consumer distribution.

Plan review chose explicit assignment callbacks rather than reusing term-update
permissions: assigning an existing term does not edit its metadata. Default owner
access remains the consumer resource's responsibility; scopes constrain options.
No browser ID-range widening and no external selector package were introduced.

## Findings fixed during implementation review

- Filament repeater fields are cloned: lifecycle closures now receive the actual
  component through native injection instead of capturing the original field.
- Plain JSON repeaters have no row owner. Default relationship mode rejects them;
  explicit saved(false)->dehydrated() stores state without loading/saving root terms.
  The guard runs in validation/saving too, including custom forms that never render.
- Two writable fields cannot silently overwrite the same owner/taxonomy: validation
  rejects duplicate configuration while allowing separate row owners/taxonomies.
- Taxonomy slug reassignment while waiting for the lock cannot clear the earlier
  taxonomy: saving verifies the resolved identity again against the locked record.
- Single configuration preserves preexisting multiple assignments until explicit
  replacement; scoped-out term IDs remain opaque unavailable state, never dropped.
- Implicit validation checks whole-taxonomy denial even for an empty selection.
- Browser fixtures apply the P1 assignment migration even when a local fixture DB
  predates P1. Their demo reset affects build/browser.sqlite only.
- Demo Deck keys preserve renamed records and assignments during repeated seeding.
  Composer quotes the seeder namespace correctly across shells.

## Verification

Final reviewed-source gates (2026-10-04):

- Full PHP suite: **435 passed / 1,671 assertions**.
- P2 cases: **25 passed / 116 assertions**, grouped by lifecycle, validation,
  context and workbench demo seeding.
- Lowest supported Laravel 11 dependency set: **30 passed / 150 assertions**
  (P2 plus existing parent-selector regressions).
- Node: **28 passed**; committed bundles reproduce byte for byte.
- Chromium fixture: **13 passed**, including assignment create/edit, keyboard,
  axe, transactional modal/cancellation, repeater isolation and parent regressions.
- Fresh Git/Composer archives: all required runtime files included, local
  workbench/tests excluded; copied consumer CLI and **2 consumer browser cases pass**.
- PHPStan level 4, Pint (**149 files**), Composer strict validation and whitespace pass.
- `composer demo` succeeded against the persistent workbench; repeatability and
  preservation of edited names/assignments are covered by the demo seeder test.

The persistent
workbench now has two demo taxonomies, 24 terms, four Decks and 12 assignment rows.
Its existing server returns HTTP 200 for /admin/decks and /admin/decks/1/edit.

## Removable tag follow-up (2026-10-04)

Multiple selections now use Filament's native gray badge component and its delete
button slot inside our own tree field. X removes only that selection; normal form
saving persists the change. Removal restores focus to the picker trigger, respects
whole-field disabled/read-only state, and preserves the other IDs losslessly.
Single-value and parent selectors retain their existing display.

Follow-up verification: **29 Node tests**, **24 form cases / 111 assertions**,
and **13 Chromium fixture cases** pass. Browser coverage includes pointer and
keyboard removal, saved/reopened assignments, unrelated taxonomy preservation,
reactive disabled/read-only buttons and axe checks. Published JS reproduces byte
for byte. A visual check of long tags at 390px in dark mode/RTL found no overflow
or JavaScript errors. The baseline full-suite/distribution gates above predate this
follow-up; their copied-consumer browser assertion now also exercises the X.

## Acceptance limits

Enable native page/panel/action transactions for one atomic owner-plus-fields
save. Without an outer transaction only each service mutation is atomic. General
custom workflows must obey the documented taxonomy-before-owner lock order.
The field preserves P1's default-connection and owner-identity contracts.
Actual human NVDA/VoiceOver acceptance is still unverified; automated keyboard
and axe results are not presented as that evidence. Remote P2 CI has not run:
this milestone remains uncommitted/unpushed pending the user's next instruction.

## Tree redesign and optional ancestor selection (2026-10-04)

The shared selector uses native Filament SVG chevrons, subtle gray hover colors,
faint hierarchy guides, a separated search bar and inline red disabled reasons.
Multiple assignments use left checkbox indicators with aria-checked; single
selectors retain aria-selected. A checked parent always represents a real
assignment; selected-descendant summaries explain its subtree without misleading
mixed states or claiming persisted automatic-selection provenance.

`multiple()->selectAncestors()` opts into ancestor closure; independent selection
remains the default. Selecting a child adds allowed ancestors, never descendants
or siblings. Removing a parent removes its selected descendants; leaf removal
retains ancestors. Hydration expands only editable form state, saving validates
closure under the existing taxonomy lock, and denied ancestors disable their
branches. Opaque unavailable IDs remain intact. The demo Topics field enables `->selectAncestors()` directly in its resource
schema. There is no user-facing mode toggle.

Review findings: summary text needed gray-600 contrast against gray hover;
popup placement now measures the entire field including its selection count;
browser tests wait for native Livewire mode configuration before using it.

Final follow-up gates: **442 PHP tests / 1,705 assertions**, **34 Node tests**,
**16 Chromium tests** (including axe in light and narrow dark RTL ancestor mode),
**36 lowest Laravel 11 form/parent cases / 179 assertions**, PHPStan level 4,
Pint **150 files**, reproducible JS and whitespace checks pass. The full PHP run
predates only the test-only Laravel 11 collection-method correction; all seven
ancestor cases were rerun on current and lowest installations after that fix.
The lowest check caught `modelKeys()` being called directly on a relation; the
test now retrieves an Eloquent collection explicitly. Dark feedback/clear text
contrast was also fixed. Baseline archive checks above predate this follow-up.
Final assets are published on the existing localhost backend; its durable data
was not changed by this verification. Changes remain uncommitted/unpushed.

## Field configuration and stable summary rows (2026-10-04)

User clarification: selection mode belongs to field configuration. Removed the
workbench toggle and its state/closure; Topics uses `->multiple()->selectAncestors()`
directly. Consumers keep independent selection unless they opt in. Native lifecycle
and browser expectations reflect the demo configuration. The correction passed
31 form cases / 145 assertions and seven assignment browser cases.

Selected-descendant summaries now reserve a single line on multiple-mode parent
rows even when empty. The line stays blank at zero and is described to assistive
technology only when it contains a count. Single selectors do not reserve this
space. A browser regression compares all option row heights and content positions
before selection, after selection and after removal.

## Hierarchy context in selected tags

Native gray badges now display a muted ancestor path and emphasize the selected
term. For example, Science / Mathematics / Algebra makes the parent relationship
visible even when those ancestors are not assigned. Paths use current visible tree
nodes, update with configuration changes, and preserve unavailable IDs without
inventing ancestry. Bidirectional text is isolated with bdi; long paths wrap inside
the input and full paths are available in title text. Existing X behavior and
assignment state are unchanged by path display.

The stable-summary follow-up passed eight assignment browser cases, including
row geometry comparisons before/after child selection and removal. Final tag
verification follows below.

## Revised grouping and inline summaries

User review replaced the repeated breadcrumb labels with nested badge groups.
Each selected term is shown once, following taxonomy tree order. Parents contain
selected descendants; unassigned ancestors are plain contextual labels, while
assigned ancestors remain native removable badges. Unavailable IDs retain the
existing fallback badges. A new recursive view is explicitly required by the
archive gate. Native slot removal labels use server-side translation bindings;
Blade directives embedded in a slot's Alpine attribute were caught during browser
review and fixed.

Counts now sit on the same row as the term title, using a reserved inline column.
There is no empty line below titles, and the geometry regression still compares
row positions/heights across selection and removal. The old breadcrumb and blank
second-line designs described above are superseded by this revision.

Final nested-group verification: **35 JavaScript tests**, **17 browser tests**
(including contextual/selected parent grouping, sibling sharing, X removal, saved
assignments, axe, narrow dark RTL and stable inline row geometry), reproducible
assets, focused PHP formatting and whitespace checks pass. Final assets are
published on localhost. No durable records changed; nothing committed or pushed.


## Selected branch refinement (2026-10-04)

Multiple fields now use a compact trigger and separate selected-branch review,
bounded to three levels, with a staged checkbox picker. The 2026-10-05 refinement
uses Filament search, checkbox, button and link components inside HTML dialogs
styled to match Filament. Current paths remain visible as horizontally scrollable
breadcrumbs with full labels; Back stays mounted. The picker keeps a responsive
fixed height with independent option scrolling. Removal previews open a separate
confirmation dialog for either the picker draft or pending field state.
See README for Apply/Cancel, Undo, and context semantics. The obsolete
selected-term-branch partial was removed; distribution requires assignment-picker,
assignment-path and assignment-removal views plus taxonomy-assignment-picker
compiled component. The existing backend assignment and transaction contracts
are unchanged. The local large fixture has 534 terms, 25 levels, 90 assigned terms
in its broad Deck, and 72 duplicate Overview labels.

Pre-refinement regression gates: 443 PHP /1718 assertions; 44 JS; 22 Chromium browser
cases; PHPStan4; Pint153; strict Composer metadata; current Git/Composer archives,
copied consumer installation/publishing plus two consumer browser cases.
Human screen-reader testing remains an acceptance follow-up. User visual review
is pending; these changes have not been committed or pushed.
