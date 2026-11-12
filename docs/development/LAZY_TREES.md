# On-demand trees and the redesigned term fields

Recorded 2026-11-12. Supersedes the dialog picker described in
[P2_FIELDS.md](P2_FIELDS.md) and the management-page limits in
[F6_ACCEPTANCE.md](F6_ACCEPTANCE.md).

## Decisions

The package exists for an app that tracks a collection of playing-card decks,
with taxonomies such as a Disney theme containing Lilo & Stitch and The Lion
King. Some of those taxonomies will outgrow the 100-term comfort zone of the
fully rendered management page.

- The user found the dialog picker clunky: drilling down one level at a time,
  a separate branch review, Apply and Undo. They chose between previews:
  - small taxonomies: an inline checkbox tree in the form;
  - large taxonomies: a searchable dropdown with chips that show each term's
    path, loading branches and search results on demand.
- One threshold decides when a taxonomy counts as large: 100 visible terms by
  default. The management page and parent selector read it from the plugin
  (`lazyLoadingThreshold()`), fields from their own option, with
  `configureUsing()` for global defaults. Panel settings stay on the plugin, as
  agreed earlier; the config file keeps only models.
- Single fields keep the compact dropdown at every size.
- Selection changes apply to the form state immediately; the form's Save
  persists them. Unchecking a parent with ancestor selection removes its
  selected descendants without a confirmation step.

## Implementation

- `Support\TaxonomyOutline` reads ID, parent and name of every visible term in
  one query and walks it from the roots. It serves children, ancestors,
  descendants, the visible count and search (case and accent insensitive; every
  word in the name or an ancestor's name, at least one in the name).
- `TaxonomyTreeField` exposes `getTreeChildren()`, `searchTree()` and
  `getTreeSelection()` through `#[ExposedLivewireMethod]`, all renderless. They
  return only visible terms of the field's own taxonomy, with the same disabled
  reasons (`disableTermWhen`, assignment permission, ancestor denial, cycles) as
  the full tree. Validation and ancestor expansion use the outline plus models of
  the selected terms, so they no longer need the whole tree.
- `Support\ManagedTermTree` builds the rendered part of the management tree:
  everything, the expanded branches a page at a time, or search matches below
  their ancestors. Move buttons receive explicit sibling IDs, so paging cannot
  break them.
- One Alpine component, `taxonomy-term-field`, renders both layouts from a flat
  row list, keeps loaded branches outside Alpine's proxies and talks to the
  server through `callSchemaComponentMethod`.

## Findings during verification

- The field's `x-data` attribute must stay identical between renders. It used to
  embed the configuration, which includes the selected terms; after a selection
  change Livewire rewrote the attribute and Alpine restarted the component,
  losing search, loaded branches, the open dropdown and focus. The configuration
  now travels only through `data-tree-config`.
- Expanded management branches lived only in the session, which panels without
  session middleware forget between requests. A public Livewire property now
  keeps them between requests and the session across visits; both are
  sanitized because the browser can change them.
- Focusing a row through `$nextTick` waits for a timer, so a fast Enter after
  Arrow Down still reached the search box. Rows that already exist are focused
  immediately; Enter in the search box chooses the first match.
- A plain Filament action fills its modal with defaults and does not load
  relationships. That is Filament's behavior, not a field regression; the
  README documents `fillForm()`.

## Verification (2026-11-12)

PHP feature tests cover the outline, field modes and endpoints, the lazy
management page (paging, session-less expansion, tampered state, reveal after
create, search) and the parent selector. JavaScript unit tests cover selection,
ancestor cascades, restricted assignments, chips, lazy loading, search and
keyboard navigation. Eleven Chromium tests passed three consecutive runs,
including drag and drop with hover expansion on a lazy tree and axe checks in
light and narrow dark RTL layouts.

Performance harness on an Apple silicon Mac, median of three loads:

| Fixture | Manage Terms | Parent selector |
| --- | --- | --- |
| broad-100, rendered at once | 0.85 s, 1.46 MB, 4,853 elements | 0.35 s |
| broad-1000, on demand | 0.33 s, 0.41 MB, 1,619 elements | 0.25 s |
| mixed-100-depth4 | 0.84 s, 1.51 MB | 0.33 s |
| deep-32 | 0.46 s, 0.68 MB | 0.26 s |

Server side, the full parent configuration of 1,000 terms is 103 KB, while the
on-demand root page is about 1 KB and a search about 4 ms. The earlier numbers
in F6_ACCEPTANCE.md came from a different machine and are not directly
comparable. Human screen-reader acceptance is still open.
