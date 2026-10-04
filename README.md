# A flexible taxonomy and hierarchical term management plugin for Filament.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eyawiin/filament-taxonomies.svg?style=flat-square)](https://packagist.org/packages/eyawiin/filament-taxonomies)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/eyawiin/filament-taxonomies/tests.yml?branch=5.x&label=tests&style=flat-square)](https://github.com/eyawiin/filament-taxonomies/actions?query=workflow%3Atests+branch%3A5.x)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/eyawiin/filament-taxonomies/code-style.yml?branch=5.x&label=code%20style&style=flat-square)](https://github.com/eyawiin/filament-taxonomies/actions?query=workflow%3Acode-style+branch%3A5.x)
[![Total Downloads](https://img.shields.io/packagist/dt/eyawiin/filament-taxonomies.svg?style=flat-square)](https://packagist.org/packages/eyawiin/filament-taxonomies)



Filament Taxonomies lets administrators define taxonomies and organize their terms into nested trees. The package registers a Taxonomies resource in your Filament panel, where you can create taxonomies and manage their terms.

## Installation

You can install the package via composer:

```bash
composer require eyawiin/filament-taxonomies
```

> [!IMPORTANT]
> If you have not set up a custom theme and are using Filament Panels follow the instructions in the [Filament Docs](https://filamentphp.com/docs/5.x/styling/overview#creating-a-custom-theme) first.

After setting up a custom theme add the plugin's views to your theme css file or your app's css file if using the standalone packages.

```css
@source '../../../../vendor/eyawiin/filament-taxonomies/resources/**/*.blade.php';
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-taxonomies-migrations"
php artisan migrate
```

Publish the package's compiled JavaScript and CSS after installation and after
every package update:

```bash
php artisan filament:assets
```

The optional reserved config file currently has no settings:

```bash
php artisan vendor:publish --tag="filament-taxonomies-config"
```

It publishes `config/filament-taxonomies.php` and merges under
`config('filament-taxonomies')`. Publishing it is not required.
The interactive `php artisan filament-taxonomies:install` command publishes
config/migrations and offers to run migrations. It does not install the panel
plugin or build your theme; follow those steps below.

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="filament-taxonomies-views"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

Register the plugin on your Filament panel:

```php
use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // Keep your existing panel configuration here.
        ->plugin(FilamentTaxonomiesPlugin::make());
}
```

Open **Taxonomies** in the panel navigation to create a taxonomy, then choose **Manage Terms** to organize its terms in a tree. Drag a term by its handle and drop it near the top of another row to place it before, in the middle to make it a child, or near the bottom to place it after. You can also use the row's **Move up** and **Move down** buttons to reorder siblings without dragging. Use **Edit** to change a term's parent.

## Assigning terms to Eloquent models

Publish migrations again and run `php artisan migrate` after updating to this
version. The new `taxonomy_term_assignments` table stores explicit assignments.
No existing records are assigned automatically.

Add the trait to each owner model:

```php
use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasTaxonomies;
}
```

Use the taxonomy model, a positive integer taxonomy ID, or its **exact slug**.
Strings always mean slugs, including numeric strings. Term arguments are distinct
positive integer IDs or lossless integer strings:

```php
$article->attachTaxonomyTerms('topics', [$termId]); // Repeating this is idempotent.
$article->detachTaxonomyTerms($taxonomy, [$termId]);
$article->syncTaxonomyTerms($taxonomy->id, [$firstId, $secondId]);
$article->syncTaxonomyTerms('topics', []); // Clears topics; preserves other taxonomies.

$article->termsForTaxonomy('topics')->get();
$article->taxonomyTerms; // Every visible explicitly assigned term.
Article::with('taxonomyTerms')->get();
Article::whereHas('taxonomyTerms', fn ($query) =>
    $query->where('taxonomy_terms.id', $termId)
)->get();
```

Assigning a child does not assign its parents, children or siblings. Multiple
terms per taxonomy are allowed. The relation supports normal Eloquent reading,
eager loading and query constraints. Write through the trait methods or
`TaxonomyAssignmentService`; native relation `attach`/`sync`, pivot writes and
raw imports bypass the managed checks. When an owner query joins other tables,
select the owner table’s columns (for example `articles.*`) so Eloquent hydrates
the owner’s own ID and attributes.

Owners must be saved, have an unchanged positive integer, UUID, or ULID primary
key, and use the default database connection. UUID/ULID models must
use Eloquent's string key type (configured by native `HasUuids`/`HasUlids`).
ULID letter case is preserved, including Laravel's generated lowercase IDs.
The pivot uses a 36-character string owner key,
so these owner types can coexist. Morph aliases are supported, must be ASCII
letters/digits/underscore/backslash/dot/hyphen, and fit in 191 bytes. MySQL owner
keys and morph types use binary ASCII collation. Keep aliases stable; changing
an alias or adopting a morph map requires migrating existing pivot types.
Custom string keys and cross-connection ownership are unsupported.

Managed writes recheck scoped owners, taxonomies and term membership inside a
transaction, locking the taxonomy first and then the owner. MySQL 8.4/InnoDB
serializes cooperating writers, including term deletion; the last completed sync
sets the selection for that taxonomy. No automatic retry replays consumer code.
For larger application transactions acquire taxonomy locks in ascending ID order
**before** owner writes, or let these methods own their transaction. Deadlocks
from another lock order propagate to the caller. SQLite has functional coverage;
other databases have no concurrency guarantee here.

Foreign, deleted, hidden, duplicate or malformed term inputs reject the whole
operation. Sync also rejects an existing assignment hidden by a term scope,
rather than clearing invisible data. Attach/detach can still operate on visible
terms. The service performs domain checks without authentication; consumers must
authorize the owner and assignment operation. Global scopes provide visibility,
not a tenant ownership schema or a replacement for authorization.

Term/taxonomy deletion cascades to their assignments. Normal owner hard deletion
cleans its pivot rows via a model event. Soft deletion retains them; restoration
makes them available again, and force deletion removes them. Wrap owner deletion
in `DB::transaction()` when owner deletion and cleanup must be atomic.
Query-builder/bulk deletion, `deleteQuietly()` and raw SQL do not dispatch that
cleanup event. For those paths, explicitly clean each loaded owner's assignments
in the **same transaction** as its hard deletion:

```php
DB::transaction(function () use ($article): void {
    Article::whereKey($article->getKey())->delete();
    app(\Eyawiin\FilamentTaxonomies\Services\TaxonomyAssignmentService::class)
        ->forgetOwner($article);
});
```

Do not call that cleanup for soft deletion. A polymorphic owner foreign key cannot
protect eventless deletion; applications remain responsible for those paths.
Reusable assignment form fields are the next milestone.

### Trying assignments in the workbench

P1 provides the backend API. Reusable assignment controls in Filament are P2.
The workbench `User` already includes `HasTaxonomies` for manual API testing.
From the repository root in your PHP/WSL terminal:

```bash
composer build
php vendor/bin/testbench tinker
```

Paste these statements in Tinker:

```php
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Workbench\Database\Factories\UserFactory;

$user = UserFactory::new()->create(['name' => 'P1 demo']);
$taxonomy = Taxonomy::create(['name' => 'P1 demo', 'slug' => 'p1-demo-' . uniqid()]);
$term = app(TaxonomyTreeService::class)->createTerm($taxonomy, 'Demo term', 'demo');

$user->attachTaxonomyTerms($taxonomy, [$term->id]);
$user->termsForTaxonomy($taxonomy)->pluck('taxonomy_terms.name')->all(); // ["Demo term"]
$user->syncTaxonomyTerms($taxonomy, []);
$user->taxonomyTerms()->count(); // 0
```

The demo taxonomy also appears on the normal Manage Terms page. Cleanup:

```php
\Illuminate\Support\Facades\DB::transaction(fn () => $user->delete());
app(TaxonomyTreeService::class)->deleteTaxonomy($taxonomy);
```

The automated assignment suite exercises owner types, key formats, scopes,
invalid inputs, rollback and cleanup:

```bash
composer test -- tests/Feature/Assignments --no-coverage
```

## Taxonomy fields in resource forms

Use `HasTaxonomies` on the owner model, run the assignment migration, and use
`TaxonomySelect` in the resource's schema:

```php
use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;

TaxonomySelect::make('topic_ids')
    ->label('Topics')
    ->taxonomy('topics') // Exact slug; an integer means an ID.
    ->multiple(),

TaxonomySelect::make('level_id')
    ->label('Level')
    ->taxonomy('levels'),
```

These are relationship fields: their names are form state paths, not columns on
the owner. They hydrate assigned IDs and save through the scoped P1 assignment
service after a new owner has been created. Single state is an ID or `null`;
multiple state is a list of IDs, with `[]` clearing that taxonomy. Neither field
changes assignments in other taxonomies. By default selecting a branch assigns
only that term; its disclosure button expands descendants without selecting them.

For multiple fields, opt into automatic ancestor selection:

```php
TaxonomySelect::make('topic_ids')
    ->taxonomy('topics')
    ->multiple()
    ->selectAncestors(); // Also accepts a reactive boolean closure.
```

Selecting a child then assigns every visible, permitted ancestor. It does not
select siblings or descendants. Removing a parent (in the tree or via its tag X)
also removes its selected descendants; removing a child retains its parents.
An unavailable or denied ancestor makes its descendants unselectable in this
mode. Existing child assignments expand in editable form state when the mode is
enabled; opening the form does not write assignments, and normal saving persists
the expanded selection. Programmatic form submissions must include the complete
ancestor chain; validation checks it again under the taxonomy lock. Read-only and
disabled fields keep their existing state. Single fields ignore this setting.

Multiple tree options show a checkbox for the term's actual assignment. A parent
also displays a selected-descendant count, including while collapsed, so its own
assignment is distinct from selected terms below it. Counts include all assigned
descendants, including intermediate ancestors. The UI does not claim to remember
which persisted assignments were selected automatically.

**Enable Filament database transactions on create/edit pages** (or on the panel)
to make the owner and all relationship fields one atomic save:

```php
// On your CreateRecord and EditRecord pages:
protected ?bool $hasDatabaseTransactions = true;

// Alternatively, on the panel:
$panel->databaseTransactions();
```

Inside an outer transaction, validation locks the form's visible, writable
field taxonomies in ascending ID order before native owner writes. Saving checks
current term visibility and permissions again under the taxonomy lock. A failure
becomes a field validation error; the outer transaction rolls back the owner and
other fields. Without an outer transaction, only each assignment service call is
atomic: earlier owner/field writes can survive a later failure. Custom forms and
modal actions must wrap validation, owner creation/update and relationship saving
in the same transaction; actions can use `->databaseTransaction()`.

Use one assignment field per taxonomy per owner. Two different taxonomy fields
can safely coexist; duplicate writable fields for the same owner/taxonomy are
rejected during validation. In relationship repeaters the field binds to the row's
Eloquent owner; each owner must use `HasTaxonomies`. In a plain JSON repeater,
use `->saved(false)->dehydrated()` for a state-only picker: the selected IDs are
stored in that JSON row, with no relationship hydration/saving on the root owner.
The default assignment mode rejects a JSON repeater configuration rather than
accidentally overwriting root-owner assignments.
A custom create form must call `$form->model($record)->saveRelationships()` after
persisting the owner, as native Filament resource pages do.

The consumer resource authorizes owner access. Assignment permission is separate
from permission to edit the taxonomy or its terms; global model scopes always
restrict selectable terms. Add your application's assignment policy explicitly:

```php
TaxonomySelect::make('topic_ids')->taxonomy('topics')->multiple()
    ->canAssignUsing(fn ($taxonomy, $record): bool =>
        auth()->user()->can('assignTerms', [$taxonomy, $record]))
    ->disableTermWhen(fn ($term): bool => $term->slug === 'restricted');
```

The callbacks can receive `taxonomy`, `record` (`null` on create), and, for
`disableTermWhen()`, `term`. They must be read-only and deterministic; they run
while rendering, validating and saving. Denying the whole taxonomy denies clearing
too. If a user should edit other owner attributes while leaving assignments
untouched, also configure `->readOnly()` for that user to skip assignment saving.
Disabled terms remain in the tree with a reason and can be explored, but
cannot be selected. Disabled, hidden and read-only fields do not save assignments.
Taxonomy references, multiple mode and callbacks may use closures for reactive
forms; changing the taxonomy does not silently replace old selection state.

Unavailable selections are shown explicitly and are never silently discarded.
A single field opening an owner with several existing assignments requires an
explicit new single choice or clearing. Scoped-out existing assignments block a
full sync, including clearing, to protect data the caller cannot see. Handle those
through an authorized backend workflow. Current browser term IDs are positive
integers up to `2^53 - 1`; larger IDs remain unavailable rather than being rounded.

The tree supports search, RTL, disabled/read-only state, keyboard navigation and
independent expansion. Arrow keys move focus; Enter/Space select or toggle a term;
Home then Enter/Space clears; Escape closes and returns focus to the trigger.
Multiple mode displays selected terms as removable Filament badges grouped by the
tree hierarchy. Parents appear once with their descendants nested inside the group.
Unassigned ancestors use plain context labels; selected ancestors have their own
removable badges. Context labels do not add assignments. Each X removes
only that term in independent mode, or its selected branch in ancestor mode;
save the form to persist the change. The tree stays open when choosing terms and
exposes checked states, descendant summaries and a live count.

## Authorization and visibility

Taxonomy CRUD keeps Filament's standard policy abilities. Listing requires
`viewAny` on `Taxonomy`; managing a taxonomy's terms also requires `view` on that
taxonomy. Term operations use a separate `TaxonomyTerm` policy:

| Operation | Term policy method |
| --- | --- |
| Create | `create($user, $taxonomy)` |
| Edit, change parent, move, or reorder | `update($user, $term)` |
| Delete | `delete($user, $term)` |

Register the policies through Laravel's policy discovery or `Gate::policy()`.
The create policy receives the owning `Taxonomy` as additional context; existing
one-argument create policies continue to work. Term deletion permission is
independent of permission to delete a taxonomy.

Checks use the active panel guard and run when operations execute, including
direct Livewire movement requests and submissions after a modal was opened.
Denied actions are hidden; movement arrows remain visible and disabled.

As in Filament, missing policies or methods allow access in normal mode, subject
to Gate before callbacks. Define every listed ability for a read-only policy.
A panel using `strictAuthorization()` reports missing policies or methods.

Navigation, record pages, parent options, and mutation lookups honor resource
queries and model global scopes. Scope changes are checked again on subsequent
Livewire requests. A `view` policy controls access to Manage Terms; use query
scopes to exclude records from the taxonomy list itself. Taxonomies are global
by default, without an ownership or tenant schema.

The tree service remains independent of authentication. Consumers calling it
directly must authorize their own integration before invoking mutations.

## Tree size and imported data

The current management page and parent field render the complete visible tree.
For the measured desktop setup, plan around **100 terms per taxonomy, up to four
levels, and approximately 100 sidebar taxonomies**. These are conservative
operating guidelines, not database limits or enforced caps. Benchmark your own
theme, labels, policies and hardware before expanding them.

A 1,000-term tree is a stress fixture: its management page renders roughly
37,000 DOM elements and more than 11 MB of decoded HTML. Reducing database queries
does not make that a responsive large-tree editor. Very deep trees also exhaust
usable indentation space. Large or deep interactive trees need a separate
rendering design; they are outside the current responsiveness claim.

Managed writes validate hierarchy and coordinate locking. Raw imports do not
automatically receive those checks. To inspect an import without changing it:

```php
$issues = app(\Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService::class)
    ->diagnoseTree($taxonomy);
```

Each result contains `term_id` and `reason`: `cycle`,
`missing_or_foreign_parent`, or `invalid_identity` for a nonpositive or unrepresentable term/ancestor
key. IDs outside PHP’s native integer range remain decimal strings in diagnostic
results. A reason applies to the term's ancestor chain,
including descendants affected by the defect. This read deliberately includes
scoped-out structural rows; authorize access to the **entire taxonomy** before
exposing its results. It is an import/admin diagnostic, not a scoped UI listing.
It does not repair records or check slug/position rules.

Normal trees show rooted visible branches only. Orphans, cycles, and branches
behind hidden ancestors are omitted; an empty display does not prove that no
stored terms exist. Diagnose and explicitly repair malformed imports in the
consumer application before using managed operations on the affected hierarchy.

### Identity ranges

Managed storage operations require positive IDs within PHP’s native integer
range. Unsigned imports beyond that range are rejected before record lookup;
diagnostics preserve their exact decimal IDs. Generated term IDs at the native
integer ceiling are rejected and rolled back because the database driver can
already have clamped an overflowing insert ID.

The current browser term controls require IDs from 1 through
9,007,199,254,740,991 (JavaScript’s largest exact integer). Larger term IDs and
their branches are omitted from the editor and parent options; incoming IDs
outside this range are rejected. This bounds the current browser representation,
not the schema or backend service. Supporting larger browser IDs requires a
future conversion to lossless string IDs throughout state, events and actions.

## Compatibility

Filament 5 requires PHP 8.2+, Laravel 11.28+ and Tailwind CSS 4.1+.
This branch retains Laravel 11 as legacy compatibility. Its tested dependency
set reported upstream security advisories in the [2026-10-02 foundation review](.github/F6_REVIEW.md).
Use Laravel 12/13 for new projects. [Filament installation requirements](https://filamentphp.com/docs/5.x/introduction/installation)

| Laravel | PHP test lanes | Testbench |
| --- | --- | --- |
| 11.28+ | 8.2, 8.3, 8.4 | 9 |
| 12 | 8.2, 8.3, 8.4 | 10 |
| 13 | 8.3, 8.4 | 11 |

PHP tests run lowest/stable dependencies on Ubuntu and Windows. These are CI
lanes, not a claim that every version allowed by Composer has been verified.
PHP 8.5+ is not in this package's current CI matrix. MySQL 8.4/InnoDB has a
focused contention/constraint lane; SQLite is the functional test backend.
Managed writes require one default database connection and cooperating service
writers; other engines and raw writes have no concurrency guarantee here.

## Development and testing

Use PHP/Composer and Node 24. From a clean checkout:

```bash
composer install
npm ci
npm run test:js
npm run check:build
composer test -- --no-coverage
composer analyse
composer test:lint
```

Compiled package assets are committed. After changing JavaScript, run
`npm run build` and commit its output. `npm run check:build` compares all
bundles byte for byte using locked dependencies.

The committed `testbench.yaml` defines workbench setup. `composer prepare`
generates ignored `workbench/storage` directories without symlinks. Workbench
builds run migrations rather than wiping existing data. The development database
is the gitignored `workbench/database/database.sqlite`, outside Testbench's
purgeable vendor skeleton and storage directories. Preparation creates it only
when absent; an existing legacy vendor database is copied with a WAL-aware
SQLite snapshot before Composer cleanup. Build and serve:

```bash
npm run build:theme
composer serve
```

Focused browser and fresh-consumer verification:

```bash
npx playwright install --with-deps chromium
npm run build:theme
npm run test:browser
composer test:distribution
```

The browser fixture uses `build/browser.sqlite` and port 8011. Distribution
verification creates a fresh Laravel app from the archive in ignored `build/`,
checks config/migrations/views/assets, exercises CRUD/parenting/movement/policy,
and tests repeated native dragging on port 8012. It requires Composer network
access, Node, Chromium and PHP ZipArchive. Both ports must be free.
[Browser verification and human screen-reader checklist](tests-browser/README.md).

For independent-process MySQL 8.4 verification, see
[the concurrency runner](tests-concurrency/README.md). `composer test:concurrency`
requires an explicitly configured disposable MySQL server and fails if it is
unavailable. It never silently skips the database gate.

Optional isolated scale measurements are documented in
[the performance harness](tests-performance/README.md); run them with
`npm run test:performance`. The recorded acceptance evidence and remaining checks
are in [F6 acceptance](.github/F6_ACCEPTANCE.md).

Formatting CI runs `pint --test` without committing or pushing changes.
All F0 pending specifications have been promoted into required suites.

## Local backend playground

From the package checkout in WSL:

```bash
composer demo
composer serve
```

`composer demo` prepares persistent workbench storage, builds/migrates the
workbench and seeds **Demo Topics** and **Demo Levels**, each with 12 terms across
three levels, plus four Decks. Open `/admin/decks` to create/edit Decks and try
multiple Topics and a single Level. Re-running adds missing fixture entries and
preserves existing names and Deck assignments. It does not reset the database.

To generate more random Decks after seeding, use Tinker:

```php
Workbench\Database\Factories\DeckFactory::new()->withDemoTerms()->count(10)->create();
```

`DeckFactory::new()->create()` makes an unrelated Deck with no assignments;
`withDemoTerms()` attaches terms from the two existing demo taxonomies. The
`DemoTaxonomyFactory` has `topics()` and `levels()` states that create nested terms
through the managed tree service. These models, factories, seeders and the Deck
resource belong to the local workbench and are excluded from package archives.

### Trying ancestor selection in the workbench

The workbench Topics field uses `->multiple()->selectAncestors()` in `DeckResource`.
Open `/admin/decks/create` or an existing Deck.
Choose Vocabulary under Languages / English: all three become checked and appear
as removable tags. Remove English to remove English and Vocabulary while keeping
Languages. To configure independent selection, remove `->selectAncestors()` or
set it to `false` in the resource schema. This is a field configuration option;
backend users do not see a mode toggle.


## Upgrade notes for the foundation cleanup

The unused `filament-taxonomies` scaffold command, empty `FilamentTaxonomies`
facade/alias and empty testing mixin have been removed. They implemented no
operations. Use `filament-taxonomies:install` for publishing and
`TaxonomyTreeService` for the documented hierarchy operations.
The internal parent factory now returns a dedicated Field, so undocumented
Select-specific chaining is not supported. Published parent views must be
updated for the nested field template and the new `parent-tree-branch` partial.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Eyawiin](https://github.com/Eyawiin)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
