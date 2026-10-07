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
    $this->term = fn (string $slug): TaxonomyTerm => $this->topics->terms()->where('slug', $slug)->firstOrFail();
});

function topicsField(mixed $taxonomy): TaxonomySelect
{
    return TaxonomySelect::make('topics')->taxonomy($taxonomy)->container(Schema::make());
}

it('shows small multiple fields as an inline tree and everything else as a dropdown', function (): void {
    expect(topicsField($this->topics)->multiple())
        ->getLayout()->toBe('tree')->isLazy()->toBeFalse()
        ->and(topicsField($this->topics))
        ->getLayout()->toBe('dropdown')->isLazy()->toBeFalse()
        ->and(topicsField($this->topics)->multiple()->lazyLoadingThreshold(5))
        ->getLayout()->toBe('dropdown')->isLazy()->toBeTrue()
        ->and(topicsField($this->topics)->multiple()->lazyLoadingThreshold(12)->isLazy())->toBeFalse();
});

it('lets fields choose their layout and loading explicitly', function (): void {
    expect(topicsField($this->topics)->multiple()->lazyLoadingThreshold(5)->tree())
        ->getLayout()->toBe('tree')->isLazy()->toBeTrue()
        ->and(topicsField($this->topics)->multiple()->dropdown()->getLayout())->toBe('dropdown')
        ->and(topicsField($this->topics)->multiple()->tree(fn (): bool => false)->getLayout())->toBe('dropdown')
        ->and(topicsField($this->topics)->multiple()->dropdown(false)->getLayout())->toBe('tree')
        ->and(topicsField($this->topics)->lazyLoading()->isLazy())->toBeTrue()
        ->and(topicsField($this->topics)->lazyLoadingThreshold(0)->lazyLoading(false)->isLazy())->toBeFalse()
        ->and(topicsField($this->topics)->lazyLoadingThreshold(-3)->getLazyLoadingThreshold())->toBe(0);
});

it('sends every term without lazy loading, but only roots and the selection with it', function (): void {
    $eager = topicsField($this->topics)->multiple()->getTreeConfiguration();
    expect($eager['nodes'])->toHaveCount(12)
        ->and($eager)->toMatchArray(['lazy' => false, 'layout' => 'tree', 'more' => false, 'searchable' => true, 'selection' => [], 'names' => []]);

    $deck = DeckFactory::new()->create();
    $deck->syncTaxonomyTerms($this->topics, [($this->term)('grammar')->id, ($this->term)('algebra')->id]);
    TaxonomySelect::configureUsing(fn (TaxonomySelect $field) => $field->lazyLoadingThreshold(5));
    $html = Livewire::test(EditDeck::class, ['record' => $deck->id])->html();
    preg_match_all('/data-tree-config="([^"]+)"/', $html, $matches);
    $configuration = collect($matches[1])->map(fn (string $json): array => json_decode(html_entity_decode($json), true))
        ->first(fn (array $configuration): bool => $configuration['multiple']);

    expect($configuration)->toMatchArray(['lazy' => true, 'layout' => 'dropdown', 'more' => false])
        ->and(array_column($configuration['nodes'], 'name'))->toBe(['Languages', 'Science'])
        ->and(array_column($configuration['selection'], 'name'))->toEqualCanonicalizing(['Languages', 'English', 'Grammar', 'Science', 'Mathematics', 'Algebra'])
        ->and(collect($configuration['selection'])->firstWhere('name', 'Grammar')['ancestors'])->toBe([($this->term)('languages')->id, ($this->term)('english')->id])
        ->and($configuration['names'])->toMatchArray([($this->term)('english')->id => 'English', ($this->term)('mathematics')->id => 'Mathematics']);
});

it('loads branches a page at a time and ignores unknown parents and offsets', function (): void {
    foreach (range(1, TaxonomyTreeField::PAGE_SIZE + 5) as $index) {
        $this->topics->terms()->create(['name' => 'Extra ' . $index, 'slug' => 'extra-' . $index, 'parent_id' => ($this->term)('science')->id, 'position' => 10 + $index]);
    }
    $field = topicsField($this->topics)->multiple()->lazyLoading();
    $first = $field->getTreeChildren(($this->term)('science')->id);
    $second = $field->getTreeChildren((string) ($this->term)('science')->id, (string) TaxonomyTreeField::PAGE_SIZE);

    expect($first['nodes'])->toHaveCount(TaxonomyTreeField::PAGE_SIZE)->and($first['more'])->toBeTrue()
        ->and(array_slice(array_column($first['nodes'], 'name'), 0, 3))->toBe(['Mathematics', 'Biology', 'Extra 1'])
        ->and($first['nodes'][0])->toMatchArray(['ancestors' => [($this->term)('science')->id], 'hasChildren' => true, 'disabled' => false])
        ->and($second['nodes'])->toHaveCount(7)->and($second['more'])->toBeFalse()
        ->and(array_column($field->getTreeChildren()['nodes'], 'name'))->toBe(['Languages', 'Science'])
        ->and($field->getTreeChildren(999_999))->toBe(['nodes' => [], 'more' => false])
        ->and($field->getTreeChildren('1e3'))->toBe(['nodes' => [], 'more' => false])
        ->and($field->getTreeChildren(['x']))->toBe(['nodes' => [], 'more' => false])
        ->and(array_column($field->getTreeChildren(null, 'not a number')['nodes'], 'name'))->toBe(['Languages', 'Science']);
});

it('searches and resolves selections with their ancestor names', function (): void {
    $field = topicsField($this->topics)->multiple()->lazyLoading();
    $search = $field->searchTree('GRAM');
    $selection = $field->getTreeSelection([($this->term)('algebra')->id, (string) ($this->term)('verbs')->id, 999_999, 'junk', ['x']]);

    expect(array_column($search['nodes'], 'name'))->toBe(['Grammar'])
        ->and($search['total'])->toBe(1)
        ->and($search['names'])->toBe([($this->term)('languages')->id => 'Languages', ($this->term)('english')->id => 'English'])
        ->and($field->searchTree(['gram']))->toBe(['nodes' => [], 'names' => [], 'total' => 0])
        ->and(array_column($selection['nodes'], 'name'))->toBe(['Algebra', 'Verbs'])
        ->and($selection['names'])->toHaveCount(4)
        ->and($field->getTreeSelection('1'))->toBe(['nodes' => [], 'names' => []]);
});

it('marks forbidden terms and descendants of forbidden ancestors in loaded branches', function (): void {
    $english = ($this->term)('english');
    $field = topicsField($this->topics)->multiple()->selectAncestors()->lazyLoading()
        ->disableTermWhen(fn (TaxonomyTerm $term): bool => $term->is($english));
    $languages = collect($field->getTreeChildren(($this->term)('languages')->id)['nodes'])->keyBy('name');
    $english = collect($field->getTreeChildren($english->id)['nodes'])->keyBy('name');

    expect($languages['English'])->toMatchArray(['disabled' => true, 'reason' => __('filament-taxonomies::assignment-tree.denied')])
        ->and($languages['German']['disabled'])->toBeFalse()
        ->and($english['Grammar'])->toMatchArray(['disabled' => true, 'reason' => __('filament-taxonomies::assignment-tree.ancestor_denied')])
        ->and(collect($field->searchTree('grammar')['nodes'])->first()['disabled'])->toBeTrue();

    $denied = topicsField($this->topics)->multiple()->lazyLoading()->canAssignUsing(fn (): bool => false);
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
        ->first(fn ($field): bool => $field instanceof TaxonomySelect && $field->getName() === 'topic_ids')->getKey();

    $component->call('callSchemaComponentMethod', $key, 'getTreeChildren', ['parent' => ($this->term)('science')->id])
        ->assertReturned(fn (array $result): bool => array_column($result['nodes'], 'name') === ['Mathematics', 'Biology'])
        ->call('callSchemaComponentMethod', $key, 'searchTree', ['search' => 'verbs'])
        ->assertReturned(fn (array $result): bool => array_column($result['nodes'], 'name') === ['Verbs'])
        ->call('callSchemaComponentMethod', $key, 'saveRelationships')
        ->assertReturned(null);
});
