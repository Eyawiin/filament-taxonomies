# Foundation scale measurements

Run these optional measurements with installed PHP/Composer dependencies, Node 24,
Chromium and the built workbench theme. They are development tools, excluded from
both package archives.

```bash
npm run build
npm run build:theme
php tests-performance/setup.php
XDEBUG_MODE=off php tests-performance/measure.php current
PERFORMANCE_REPORT=current npm run test:performance
```

On PowerShell set the environment variables with `$env:XDEBUG_MODE = 'off'`
and `$env:PERFORMANCE_REPORT = 'current'` instead of inline shell assignments.
Playwright itself forces Xdebug off for its PHP server.

Setup replaces only the guarded `build/performance.sqlite` fixture. It never uses
the workbench database. Keep port 8013 free. Do not run two performance harnesses
against the same fixture at once. Browser setup reseeds it before each run.
Fixtures: 100/1,000 broad terms, 100 terms in 25 four-level chains, a 32-level
chain, and 100 additional sidebar
taxonomies. The edited term is the first root, exercising unavailable descendants.
Root counts as depth one.

Reports live in ignored `build/performance-*-server.json` and
`build/performance-*-browser.json`. Server operations discard one warm-up and
retain three samples; the browser records three page loads with ordinary browser
caching, followed by one matching search, one empty result, clearing and End/Home
navigation per fixture. Query headers count SQL after application bootstrap;
server time includes bootstrap and rendering. HTML bytes are the decoded response,
not gzip transfer size. Tree/configuration bytes are JSON, not Livewire snapshots.
Browser readiness means the expected row count/control is present. Interaction
times include Playwright input and assertion overhead, not a pure event-to-paint
metric. Sidebar service reads are measured separately and rendered item counts
are recorded on each page.

Run measurements without competing test/build jobs. These observations are not a
statistical load test or a production latency promise. Hardware, PHP workers,
database latency, custom policies/scopes, labels and theme affect results. CI
enforces deterministic query/visibility regressions in the normal PHP/Node suites;
wall-clock thresholds are deliberately not added to shared-runner CI.

See `.github/F6_ACCEPTANCE.md` for the recorded environment, comparison, operating
guidance and acceptance gaps.
