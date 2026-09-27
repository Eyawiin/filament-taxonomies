<?php

namespace Eyawiin\FilamentTaxonomies\Pages;

use Filament\Pages\Page;

class Taxonomies extends Page
{
    protected static ?string $navigationLabel = 'Taxonomies';

    protected static ?string $title = 'Taxonomies';

    protected string $view = 'filament-taxonomies::pages.taxonomies';
}
