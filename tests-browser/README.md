# Browser verification

Development tools only: Playwright Chromium and axe. The fixture boots real
Filament and Livewire, publishes the package assets, and stores data exclusively
in `build/browser.sqlite`. A bootstrap guard rejects any other database;
workbench providers are excluded. Port 8011 must be free.

```sh
composer install
npm ci
npm run build:theme
npx playwright install --with-deps chromium
npm run test:browser
```

Tests cover keyboard clearing, focus versus selection, disabled branches,
multiple/repeater fields, reactive state/disabled/read-only/options, validation,
modal remounts, escaped labels, RTL/dark/reduced motion and narrow containment.
Chromium accessibility snapshots and axe inspect semantics. These are not a
substitute for testing with an actual screen reader.

## Assistive technology acceptance check

Still requires a human screen-reader session (NVDA/Firefox or VoiceOver/Safari).
Run `composer demo:large` and edit the Playground Decks under `/admin/decks`:

- In the inline Themes tree, hear each term's level, expanded state, checked
  state and "selected below" counts; Space toggles, Right and Left open and
  close branches.
- In the dropdowns, Arrow Down opens the tree on the selected term; current-term,
  cycle and "Not permitted" reasons are announced without a selected state; the
  empty choice clears; Escape and Tab leave predictably and return focus.
- In the large dropdown, check how loading branches come across, that search
  results include their paths, and that chips announce their remove buttons.
- On Manage Terms for the Large catalog, expand branches, use **Show more**, search,
  and move terms with the arrow buttons.

Test with a narrow viewport and 200% zoom as well. Record browser and reader
versions and the actual results in `docs/development/LAZY_TREES.md`. No
screen-reader session is claimed by the automated suite, and automated axe
results are not evidence of one.

## Coverage

`term-fields.spec.js` covers the inline tree (ancestor selection and branch
removal, search, keyboard, collapse counts, restricted terms, read-only and
disabled modes), single and multiple dropdowns (keyboard, empty choice, search
with Enter, Livewire updates), the 534-term lazy dropdown (on-demand branches,
server search with paths, chips, focus after Escape), repeaters, an action modal,
reactive cardinality, saving through the Deck resource, and axe in light and
narrow dark RTL layouts. `manage-terms.spec.js` covers the lazy management page:
roots first, branches kept open between requests, search, arrow moves, and drag
and drop with hover expansion of a collapsed branch.
