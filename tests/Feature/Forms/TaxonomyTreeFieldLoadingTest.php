<?php

use Eyawiin\FilamentTaxonomies\Forms\TaxonomySelect;
use Eyawiin\FilamentTaxonomies\Forms\TaxonomyTreeField;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Tests\Support\Assignments\FormFixture;
use Filament\Schemas\Schema;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Decks\Pages\EditDeck;
use Workbench\Database\Factories\DeckFactory;

beforeEach(function (): void {
    foreach (FormFixture::create() as $key => $value) {
        $this->{$key} = $value;
    }
    $this->term = fn (string $slug): TaxonomyTerm => $this->themes->terms()->where('slug', $slug)->firstOrFail();
});

function themesField(mixed $taxonomy): TaxonomySelect
{
    return TaxonomySelect::make('topics')->taxonomy($taxonomy)->container(Schema::make());
}

it('shows small multiple fields as an inline tree and everything else as a dropdown', function (): void {
    expect(themesField($this->themes)->multiple())
        ->getLayout()->toBe('tree')->isLazy()->toBeFalse()
        ->and(themesField($this->themes))
        ->getLayout()->toBe('dropdown')->isLazy()->toBeFalse()
        ->and(themesField($this->themes)->multiple()->lazyLoadingThreshold(5))
        ->getLayout()->toBe('dropdown')->isLazy()->toBeTrue()
        ->and(themesField($this->themes)->multiple()->lazyLoadingThreshold(12)->isLazy())->toBeFalse();
});

it('lets fields choose their layout and loading explicitly', function (): void {
    expect(themesField($this->themes)->multiple()->lazyLoadingThreshold(5)->tree())
        ->getLayout()->toBe('tree')->isLazy()->toBeTrue()
        ->and(themesField($this->themes)->multiple()->dropdown()->getLayout())->toBe('dropdown')
        ->and(themesField($this->themes)->multiple()->tree(fn (): bool => false)->getLayout())->toBe('dropdown')
        ->and(themesField($this->themes)->multiple()->dropdown(false)->getLayout())->toBe('tree')
        ->and(themesField($this->themes)->lazyLoading()->isLazy())->toBeTrue()
        ->and(themesField($this->themes)->lazyLoadingThreshold(0)->lazyLoading(false)->isLazy())->toBeFalse()
        ->and(themesField($this->themes)->lazyLoadingThreshold(-3)->getLazyLoadingThreshold())->toBe(0);
});

it('sends every term without lazy loading, but only roots and the selection with it', function (): void {
    $eager = themesField($this->themes)->multiple()->getTreeConfiguration();
    expect($eager['nodes'])->toHaveCount(12)
        ->and($eager)->toMatchArray(['lazy' => false, 'layout' => 'tree', 'more' => false, 'searchable' => true, 'selection' => [], 'names' => []]);

    $deck = DeckFactory::new()->create();
    $deck->syncTaxonomyTerms($this->themes, [($this->term)('lilo-and-stitch')->id, ($this->term)('wolves')->id]);
    TaxonomySelect::configureUsing(fn (TaxonomySelect $field) => $field->lazyLoadingThreshold(5));
    $html = Livewire::test(EditDeck::class, ['record' => $deck->id])->html();
    preg_match_all('/data-tree-config="([^"]+)"/', $html, $matches);
    $configuration = collect($matches[1])->map(fn (string $json): array => json_decode(html_entity_decode($json), true))
        ->first(fn (array $configuration): bool => $configuration['multiple']);

    expect($configuration)->toMatchArray(['lazy' => true, 'layout' => 'dropdown', 'more' => false])
        ->and(array_column($configuration['nodes'], 'name'))->toBe(['Pop culture', 'Nature'])
        ->and(array_column($configuration['selection'], 'name'))->toEqualCanonicalizing(['Pop culture', 'Disney', 'Lilo & Stitch', 'Nature', 'Animals', 'Wolves'])
        ->and(collect($configuration['selection'])->firstWhere('name', 'Lilo & Stitch')['ancestors'])->toBe([($this->term)('pop-culture')->id, ($this->term)('disney')->id])
        ->and($configuration['names'])->toMatchArray([($this->term)('disney')->id => 'Disney', ($this->term)('animals')->id => 'Animals']);
});

it('loads branches a page at a time and ignores unknown parents and offsets', function (): void {
    foreach (range(1, TaxonomyTreeField::PAGE_SIZE + 5) as $index) {
        $this->themes->terms()->create(['name' => 'Extra ' . $index, 'slug' => 'extra-' . $index, 'parent_id' => ($this->term)('nature')->id, 'position' => 10 + $index]);
    }
    $field = themesField($this->themes)->multiple()->lazyLoading();
    $first = $field->getTreeChildren(($this->term)('nature')->id);
    $second = $field->getTreeChildren((string) ($this->term)('nature')->id, (string) TaxonomyTreeField::PAGE_SIZE);

    expect($first['nodes'])->toHaveCount(TaxonomyTreeField::PAGE_SIZE)->and($first['more'])->toBeTrue()
        ->and(array_slice(array_column($first['nodes'], 'name'), 0, 3))->toBe(['Animals', 'Space', 'Extra 1'])
        ->and($first['nodes'][0])->toMatchArray(['ancestors' => [($this->term)('nature')->id], 'hasChildren' => true, 'disabled' => false])
        ->and($second['nodes'])->toHaveCount(7)->and($second['more'])->toBeFalse()
        ->and(array_column($field->getTreeChildren()['nodes'], 'name'))->toBe(['Pop culture', 'Nature'])
        ->and($field->getTreeChildren(999_999))->toBe(['nodes' => [], 'more' => false])
        ->and($field->getTreeChildren('1e3'))->toBe(['nodes' => [], 'more' => false])
        ->and($field->getTreeChildren(['x']))->toBe(['nodes' => [], 'more' => false])
        ->and(array_column($field->getTreeChildren(null, 'not a number')['nodes'], 'name'))->toBe(['Pop culture', 'Nature']);
});

it('searches and resolves selections with their ancestor names', function (): void {
    $field = themesField($this->themes)->multiple()->lazyLoading();
    $search = $field->searchTree('LILO');
    $selection = $field->getTreeSelection([($this->term)('wolves')->id, (string) ($this->term)('the-mandalorian')->id, 999_999, 'junk', ['x']]);

    expect(array_column($search['nodes'], 'name'))->toBe(['Lilo & Stitch'])
        ->and($search['total'])->toBe(1)
        ->and($search['names'])->toBe([($this->term)('pop-culture')->id => 'Pop culture', ($this->term)('disney')->id => 'Disney'])
        ->and($field->searchTree(['lilo']))->toBe(['nodes' => [], 'names' => [], 'total' => 0])
        ->and(array_column($selection['nodes'], 'name'))->toBe(['Wolves', 'The Mandalorian'])
        ->and($selection['names'])->toHaveCount(4)
        ->and($field->getTreeSelection('1'))->toBe(['nodes' => [], 'names' => []]);
});

it('marks forbidden terms and descendants of forbidden ancestors in loaded branches', function (): void {
    $disney = ($this->term)('disney');
    $field = themesField($this->themes)->multiple()->selectAncestors()->lazyLoading()
        ->disableTermWhen(fn (TaxonomyTerm $term): bool => $term->is($disney));
    $popCulture = collect($field->getTreeChildren(($this->term)('pop-culture')->id)['nodes'])->keyBy('name');
    $disneyChildren = collect($field->getTreeChildren($disney->id)['nodes'])->keyBy('name');

    expect($popCulture['Disney'])->toMatchArray(['disabled' => true, 'reason' => __('filament-taxonomies::assignment-tree.denied')])
        ->and($popCulture['Star Wars']['disabled'])->toBeFalse()
        ->and($disneyChildren['Lilo & Stitch'])->toMatchArray(['disabled' => true, 'reason' => __('filament-taxonomies::assignment-tree.ancestor_denied')])
        ->and(collect($field->searchTree('stitch')['nodes'])->first()['disabled'])->toBeTrue();

    $denied = themesField($this->themes)->multiple()->lazyLoading()->canAssignUsing(fn (): bool => false);
    expect(array_unique(array_column($denied->getTreeChildren()['nodes'], 'disabled')))->toBe([true])
        ->and($denied->lazyLoading(false)->getTreeConfiguration()['readOnly'])->toBeTrue();
});

it('exposes only the read endpoints to the browser and answers through Livewire', function (): void {
    $exposed = collect((new ReflectionClass(TaxonomySelect::class))->getMethods())
        ->filter(fn (ReflectionMethod $method): bool => $method->getAttributes(ExposedLivewireMethod::class) !== [])
        ->map->getName()->sort()->values()->all();
    expect($exposed)->toBe(['getTreeChildren', 'getTreeSelection', 'searchTree']);

    $deck = DeckFactory::new()->create();
    $component = Livewire::test(EditDeck::class, ['record' => $deck->id]);
    $key = collect($component->instance()->getSchema('form')->getFlatComponents())
        ->first(fn ($field): bool => $field instanceof TaxonomySelect && $field->getName() === 'theme_ids')->getKey();

    $component->call('callSchemaComponentMethod', $key, 'getTreeChildren', ['parent' => ($this->term)('nature')->id])
        ->assertReturned(fn (array $result): bool => array_column($result['nodes'], 'name') === ['Animals', 'Space'])
        ->call('callSchemaComponentMethod', $key, 'searchTree', ['search' => 'mandalorian'])
        ->assertReturned(fn (array $result): bool => array_column($result['nodes'], 'name') === ['The Mandalorian'])
        ->call('callSchemaComponentMethod', $key, 'saveRelationships')
        ->assertReturned(null);
});
