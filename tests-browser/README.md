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
