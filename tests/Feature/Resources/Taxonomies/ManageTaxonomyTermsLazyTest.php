<?php

use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesPlugin;
use Eyawiin\FilamentTaxonomies\Forms\TaxonomyParentSelect;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->plugin = FilamentTaxonomiesPlugin::get();
});

/** @return list<int> */
function renderedTermIds(Testable $component): array
{
    preg_match_all('/data-term-id="(\d+)"/', $component->html(), $matches);

    return array_map(intval(...), $matches[1]);
}

it('renders every term while the taxonomy stays within the threshold', function (): void {
    $tree = TaxonomyTreeFixture::create();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id]);

    expect($this->plugin->getLazyLoadingThreshold())->toBe(100)
        ->and($component->instance()->isLazy())->toBeFalse()
        ->and(renderedTermIds($component))->toHaveCount(4);
    $component->assertSee('Expand All')->assertSeeHtml('$persist(true)');
});

it('renders roots and expanded branches above the threshold and remembers them in the session', function (): void {
    $this->plugin->lazyLoadingThreshold(fn (): int => 2);
    $tree = TaxonomyTreeFixture::create();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id]);

    expect($component->instance()->isLazy())->toBeTrue()
        ->and(renderedTermIds($component))->toBe([$tree['disney']->id]);
    $component->assertDontSee('Expand All')->assertSee('2 terms below')->assertDontSeeHtml('$persist(true)');

    $component->call('toggleTerm', $tree['disney']->id);
    // Equal positions sort by name.
    expect(renderedTermIds($component))->toBe([$tree['disney']->id, $tree['liloAndStitch']->id, $tree['lionKing']->id]);

    $component->call('toggleTerm', (string) $tree['lionKing']->id)->call('toggleTerm', $tree['disney']->id);
    expect(renderedTermIds($component))->toBe([$tree['disney']->id]);

    $reloaded = Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id])->call('toggleTerm', $tree['disney']->id);
    expect(renderedTermIds($reloaded))->toContain($tree['simba']->id);

    $reloaded->call('toggleTerm', 'junk')->call('toggleTerm', ['x'])->call('collapseAllTerms');
    expect(renderedTermIds($reloaded))->toBe([$tree['disney']->id])
        ->and(session('filament-taxonomies.expanded.' . $tree['taxonomy']->id))->toBe([]);
});

it('shows large branches a page at a time and ignores tampered page sizes', function (): void {
    $this->plugin->lazyLoadingThreshold(0);
    $taxonomy = Taxonomy::create(['name' => 'Broad', 'slug' => 'broad']);
    foreach (range(1, ManageTaxonomyTerms::TERMS_PER_PAGE + 5) as $index) {
        $taxonomy->terms()->create(['name' => 'Root ' . $index, 'slug' => 'root-' . $index, 'position' => $index]);
    }
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id]);

    expect(renderedTermIds($component))->toHaveCount(ManageTaxonomyTerms::TERMS_PER_PAGE);
    $component->assertSee('Show 5 more terms')
        ->set('termLimits', ['root' => 'all', 'junk' => 9, '0' => 3])
        ->assertSuccessful();
    expect(renderedTermIds($component))->toHaveCount(ManageTaxonomyTerms::TERMS_PER_PAGE);

    $component->call('showMoreTerms');
    expect(renderedTermIds($component))->toHaveCount(ManageTaxonomyTerms::TERMS_PER_PAGE + 5);
    $component->assertDontSee('more terms');
});

it('reveals a created term behind collapsed branches and pages', function (): void {
    $this->plugin->lazyLoadingThreshold(0);
    $tree = TaxonomyTreeFixture::create();
    foreach (range(1, ManageTaxonomyTerms::TERMS_PER_PAGE) as $index) {
        $tree['taxonomy']->terms()->create(['name' => 'Sibling ' . $index, 'slug' => 'sibling-' . $index, 'parent_id' => $tree['lionKing']->id, 'position' => $index]);
    }
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id])
        ->callAction('createTerm', data: ['name' => 'Nala', 'slug' => 'nala', 'parent_id' => $tree['lionKing']->id])
        ->assertHasNoFormErrors()
        ->assertNotDispatched('taxonomy-tree-expand-term');
    $nala = $tree['taxonomy']->terms()->where('slug', 'nala')->firstOrFail();

    expect(renderedTermIds($component))->toContain($tree['disney']->id, $tree['lionKing']->id, $nala->id)
        ->and(session('filament-taxonomies.expanded.' . $tree['taxonomy']->id))->toBe([$tree['disney']->id, $tree['lionKing']->id]);
});

it('opens a branch on request, for example while dragging over it', function (): void {
    $this->plugin->lazyLoadingThreshold(0);
    $tree = TaxonomyTreeFixture::create();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id])
        ->call('expandTerm', $tree['disney']->id)
        ->call('expandTerm', $tree['disney']->id);

    expect(renderedTermIds($component))->toContain($tree['lionKing']->id)
        ->and(session('filament-taxonomies.expanded.' . $tree['taxonomy']->id))->toBe([$tree['disney']->id]);
});

it('searches matches below their ancestors and disables reordering meanwhile', function (bool $lazy): void {
    $this->plugin->lazyLoadingThreshold($lazy ? 0 : 100);
    $tree = TaxonomyTreeFixture::create();
    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id])
        ->set('termSearch', '  SIMBA ');

    expect(renderedTermIds($component))->toBe([$tree['disney']->id, $tree['lionKing']->id, $tree['simba']->id]);
    $component->assertSee('1 matching term.')
        ->assertSee('Clear the search to reorder terms.')
        ->assertDontSeeHtml('data-taxonomy-drag-handle')
        ->assertDontSeeHtml("\$dispatch('taxonomy-tree-move-term'")
        ->set('termSearch', 'nothing')
        ->assertSee('No terms match “nothing”.');
    expect(renderedTermIds($component))->toBe([]);
})->with(['loaded' => false, 'lazy' => true]);

it('loads parent choices on demand for large taxonomies', function (): void {
    $this->plugin->lazyLoadingThreshold(2);
    $tree = TaxonomyTreeFixture::create();
    $field = TaxonomyParentSelect::make($tree['taxonomy'], $tree['lionKing'])->container(Schema::make());
    $children = collect($field->getTreeChildren($tree['lionKing']->id)['nodes']);

    expect($field->isLazy())->toBeTrue()
        ->and($field->getLayout())->toBe('dropdown')
        ->and($children->pluck('reason', 'name')->all())->toBe(['Simba' => 'Would create a cycle'])
        ->and($field->getAvailableIds())->toBe([$tree['disney']->id, $tree['liloAndStitch']->id]);

    Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id])
        ->callAction(
            TestAction::make('editTerm')->arguments(['term' => $tree['lionKing']->id]),
            data: ['name' => 'Lion King', 'slug' => 'lion-king', 'parent_id' => $tree['simba']->id],
        )
        ->assertHasFormErrors(['parent_id']);
});
