<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyOutline;
use LogicException;

/** @internal Parent selection only; this is not a general purpose Select. Build it with TaxonomyParentSelect. */
class TaxonomyParentField extends TaxonomyTreeField
{
    protected ?Taxonomy $taxonomy = null;

    protected ?int $termId = null;

    /** The taxonomy whose terms are offered, and the edited term, which cannot become its own ancestor. */
    public function parentOf(Taxonomy $taxonomy, ?TaxonomyTerm $term = null): static
    {
        $this->taxonomy = $taxonomy;
        $this->termId = $term === null ? null : (int) $term->getKey();

        return $this;
    }

    public function getTaxonomy(): Taxonomy
    {
        return $this->taxonomy ?? throw new LogicException('Build the parent field with TaxonomyParentSelect::make().');
    }

    public function getEmptyOptionLabel(): string
    {
        return __('filament-taxonomies::parent-tree.root');
    }

    /** @return list<int> Terms that may become the parent: everything except the term and its descendants. */
    public function getAvailableIds(): array
    {
        $outline = TaxonomyOutline::for($this->getTaxonomy());
        $ids = $outline->ids();
        if ($this->termId === null || ! $outline->has($this->termId)) {
            return $ids;
        }

        return array_values(array_diff($ids, [$this->termId, ...$outline->descendants($this->termId)]));
    }

    protected function reasons(array $ids, TaxonomyOutline $outline, Taxonomy $taxonomy): array
    {
        $reasons = [];
        foreach ($ids as $id) {
            $reasons[$id] = match (true) {
                $this->termId === null => '',
                $id === $this->termId => __('filament-taxonomies::parent-tree.current'),
                in_array($this->termId, $outline->ancestors($id), true) => __('filament-taxonomies::parent-tree.cycle'),
                default => '',
            };
        }

        return $reasons;
    }
}
