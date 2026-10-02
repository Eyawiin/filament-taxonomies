<?php

use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Tests\Support\TaxonomyTreeFixture;
use Filament\Facades\Filament;
use Illuminate\Support\Js;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('renders both arrows for every term and disables sibling boundaries', function () {
    $tree = TaxonomyTreeFixture::create();
    $tree['lionKing']->update(['position' => 0]);
    $tree['liloAndStitch']->update(['position' => 1]);

    $html = Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id])->html();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($document);

    foreach ([
        'Disney' => [true, true],
        'Lion King' => [true, false],
        'Simba' => [true, true],
        'Lilo & Stitch' => [false, true],
    ] as $name => [$upDisabled, $downDisabled]) {
        foreach (['up' => $upDisabled, 'down' => $downDisabled] as $direction => $disabled) {
            $buttons = $xpath->query('//button[@aria-label="Move ' . $name . ' ' . $direction . '"]');
            expect($buttons->length)->toBe(1);
            $button = $buttons->item(0);
            expect($button->getAttribute('aria-disabled') === 'true')->toBe($disabled);
            expect($button->hasAttribute('x-on:click'))->toBe(! $disabled);
        }
    }
});

it('keeps each rendered edit and delete button tied to its own term', function (): void {
    $tree = TaxonomyTreeFixture::create();
    $html = Livewire::test(ManageTaxonomyTerms::class, ['record' => $tree['taxonomy']->id])->html();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($document);
    foreach ($tree['taxonomy']->terms()->get() as $term) {
        foreach (['editTerm' => 'Edit Term', 'deleteTerm' => 'Delete Term'] as $action => $label) {
            $button = $xpath->query('//li[@data-term-id="' . $term->id . '"]/div[@data-taxonomy-row]//button[@aria-label="' . $label . '"]')->item(0);
            expect($button)->not->toBeNull();
            $handler = "mountAction('" . $action . "', " . Js::from(['term' => $term->id]);
            expect($button->getAttribute('wire:click'))->toContain($handler);
        }
    }
});
