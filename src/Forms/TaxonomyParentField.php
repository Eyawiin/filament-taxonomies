<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;

/**
 * @internal Parent selection only; this is not a general purpose Select.
 *
 * @phpstan-type ParentNode array{id: int, name: string, ancestors: list<int>, hasChildren: bool, disabled: bool, reason: string}
 */
class TaxonomyParentField extends Field
{
    use CanBeReadOnly;

    protected string $view = 'filament-taxonomies::forms.parent-tree-select';

    protected array | Closure $nodes = [];

    /** @param list<ParentNode> | Closure $nodes */
    public function nodes(array | Closure $nodes): static
    {
        $this->nodes = $nodes;

        return $this;
    }

    /** @return list<ParentNode> */
    public function getNodes(): array
    {
        return $this->evaluate($this->nodes);
    }

    /** @return array<string, mixed> */
    public function getTreeConfiguration(): array
    {
        $labels = [];
        foreach (array_keys(__('filament-taxonomies::parent-tree', locale: 'en')) as $key) {
            $labels[$key] = __("filament-taxonomies::parent-tree.{$key}");
        }

        return [
            'nodes' => $this->getNodes(),
            'disabled' => $this->isDisabled(),
            'readOnly' => $this->isReadOnly(),
            'labels' => $labels,
        ];
    }
}
