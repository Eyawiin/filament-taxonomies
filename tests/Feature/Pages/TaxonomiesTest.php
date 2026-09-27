<?php

use Eyawiin\FilamentTaxonomies\Pages\Taxonomies;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('can render the taxonomies page', function () {
    Filament::setCurrentPanel('admin');

    Livewire::test(Taxonomies::class)
        ->assertSuccessful()
        ->assertSeeText('Taxonomies')
        ->assertSeeText('Taxonomy management will be built here.');
});
