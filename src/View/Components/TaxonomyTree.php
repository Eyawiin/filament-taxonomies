<?php

namespace Eyawiin\FilamentTaxonomies\View\Components;

use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TaxonomyTree extends Component
{
    /**
     * @param  list<array{term: TaxonomyTerm, children: list<mixed>}>  $nodes
     */
    public function __construct(
        public array $nodes,
    ) {}

    public function render(): View
    {
        return view('filament-taxonomies::components.taxonomy-tree');
    }
}
