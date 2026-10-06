<?php

use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Tests\Support\OrderedTaxonomyTreeFixture;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

it('translates every English line with the same placeholders', function (string $group): void {
    $english = Arr::dot(require dirname(__DIR__, 2) . "/resources/lang/en/{$group}.php");
    $german = Arr::dot(require dirname(__DIR__, 2) . "/resources/lang/de/{$group}.php");
    $placeholders = static function (string $line): array {
        preg_match_all('/:\w+/', $line, $matches);
        sort($matches[0]);

        return $matches[0];
    };

    expect(array_keys($german))->toEqualCanonicalizing(array_keys($english));
    foreach ($english as $key => $line) {
        expect($placeholders($german[$key]))->toBe($placeholders($line), "Placeholders differ in {$group}.{$key}");
    }
})->with(['taxonomies', 'parent-tree', 'assignment-tree']);

it('translates every hierarchy error the interface can show', function (): void {
    $messages = [];
    foreach (File::allFiles(dirname(__DIR__, 2) . '/src') as $file) {
        preg_match_all("/new Invalid(?:TaxonomyParent|TaxonomyDrop|TaxonomyOrder)Exception\\('([^']+)'\\)/", $file->getContents(), $matches);
        array_push($messages, ...$matches[1]);
    }
    $german = json_decode(File::get(dirname(__DIR__, 2) . '/resources/lang/de.json'), true, flags: JSON_THROW_ON_ERROR);

    expect(array_keys($german))->toEqualCanonicalizing(array_values(array_unique($messages)));
});

it('shows the term management page and its errors in German', function (): void {
    Filament::setCurrentPanel('admin');
    app()->setLocale('de');
    ['taxonomy' => $taxonomy, 'moving' => $moving] = OrderedTaxonomyTreeFixture::create();

    $component = Livewire::test(ManageTaxonomyTerms::class, ['record' => $taxonomy->id])
        ->assertSee('Begriffe verwalten: ' . $taxonomy->name)
        ->assertSee('Alle aufklappen')
        ->call('dropTerm', $moving->id, $moving->id, 'inside');

    expect($component->errors()->get('drop'))->toBe(['Das Ablageziel muss ein anderer Begriff derselben Taxonomie sein.'])
        ->and(TaxonomyResource::getNavigationLabel())->toBe('Taxonomien');
});
