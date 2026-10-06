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

Still requires a human screen-reader session (NVDA/Firefox or VoiceOver/Safari):
open a parent field, enter the selected term, traverse nested groups and confirm
levels/expanded state; hear current-term/cycle reasons without selected state;
Home/Enter clears to root and returns focus to the trigger; search and collapse
leave focus on a visible item; Escape and Tab leave predictably. Record browser,
reader versions and actual results in the foundation acceptance review (F6).
No screen-reader session is claimed by the automated suite.


## Multiple assignment picker

The suite includes the additive large demo only in its disposable database:
534 terms, 25 levels, 90 selections and repeated labels. Checks cover staged
Apply/Cancel, exact branch preview, Undo, scope persistence, contextual ancestors,
50-row progressive rendering, reactive multiple mode, nested HTML dialogs,
focus wrapping, accessible search-result paths, RTL/dark contrast and stable row
positions when descendant counts/status change. Current UX uses Filament controls,
always-visible breadcrumb paths, a persistent Back control, independently scrolling
options in a fixed responsive picker, and a separate removal confirmation dialog.

For human testing, run `composer demo:large` and edit the Playground Decks under
`/admin/decks`. With NVDA/Firefox or VoiceOver/Safari, verify the chooser's
dialog label, checkbox state and result paths, breadcrumb navigation and scrolling,
preview list and removal scope in both draft and field state, Tab/Shift+Tab
containment, Escape cancel and return focus, and Undo feedback.
Test with a narrow viewport and 200% zoom. Do not treat automated axe results
as evidence of an actual screen-reader session.
