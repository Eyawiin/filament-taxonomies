# Distribution and clean consumer gate

`composer test:distribution` verifies HEAD. During a local review, stage
the intended files and use `php bin/check-distribution.php --staged` to verify
that exact tree before committing. Generated apps/archives/traces stay in build/.

The runner requires runtime migrations/views/translations/CSS/compiled JS in the
archive and rejects dev fixtures, workbench, tooling and local docs. It copies
the exported package into a fresh Laravel 13 application's vendor directory
(no symlink, no package dev dependencies), checks config merging and the actual
installer, publishes migrations/views/assets, and verifies managed CRUD,
movement, parenting, promotion and a denying term policy.

A separately built consumer custom theme scans the installed vendor views.
The Chromium smoke exercises published components and repeated native drag
after Livewire rerender. Port 8012 serves only a generated app under build/.
The fixture policy and state endpoint are local development files and excluded
from the package archive. No production or existing consumer app is modified.
\nThe gate inspects both Git and Composer archives, including a populated local checkout, before installing the copied Git archive into the consumer. Ignoring a file in Git alone does not exclude it from Composer archive.\n