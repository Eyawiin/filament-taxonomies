<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Closure;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyAssignmentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyIdentity;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyOutline;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Renderless;

/**
 * @internal Shared tree field; use TaxonomySelect for assignments.
 *
 * Small taxonomies send every term to the browser. Above the lazy loading threshold the
 * browser requests branches and search results from the methods marked as exposed below.
 *
 * @phpstan-type TreeNode array{id: int, name: string, ancestors: list<int>, hasChildren: bool, disabled: bool, reason: string}
 */
abstract class TaxonomyTreeField extends Field
{
    use CanBeReadOnly;

    public const DEFAULT_LAZY_LOADING_THRESHOLD = 100;

    /** Terms per request when a branch loads on demand. */
    public const PAGE_SIZE = 100;

    public const SEARCH_LIMIT = 50;

    /** Smaller trees hide the search box. */
    public const SEARCH_MINIMUM = 10;

    protected string $view = 'filament-taxonomies::forms.term-tree-field';

    protected string $translationGroup = 'parent-tree';

    protected bool | Closure | null $treeLayout = null;

    protected bool | Closure | null $lazyLoading = null;

    protected int | Closure $lazyLoadingThreshold = self::DEFAULT_LAZY_LOADING_THRESHOLD;

    private ?TaxonomyOutline $outline = null;

    private int | string | null $outlineTaxonomy = null;

    abstract public function getTaxonomy(): Taxonomy;

    /**
     * Why terms cannot be chosen, keyed by ID; an empty string means the term is selectable.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    abstract protected function reasons(array $ids, TaxonomyOutline $outline, Taxonomy $taxonomy): array;

    public function isMultiple(): bool
    {
        return false;
    }

    public function shouldSelectAncestors(): bool
    {
        return false;
    }

    /** Label of the first option of a single field, which clears it. Null shows no such option. */
    public function getEmptyOptionLabel(): ?string
    {
        return null;
    }

    /** Show the tree inside the form instead of in a dropdown. */
    public function tree(bool | Closure $condition = true): static
    {
        $this->treeLayout = $condition;

        return $this;
    }

    public function dropdown(bool | Closure $condition = true): static
    {
        $this->treeLayout = $condition instanceof Closure
            ? fn (): bool => ! $this->evaluate($condition)
            : ! $condition;

        return $this;
    }

    /** Force on-demand loading on or off. By default it depends on the taxonomy size. */
    public function lazyLoading(bool | Closure | null $condition = true): static
    {
        $this->lazyLoading = $condition;

        return $this;
    }

    /** Taxonomies with more visible terms than this load branches and search results on demand. */
    public function lazyLoadingThreshold(int | Closure $terms): static
    {
        $this->lazyLoadingThreshold = $terms;

        return $this;
    }

    public function getLazyLoadingThreshold(): int
    {
        return max(0, (int) $this->evaluate($this->lazyLoadingThreshold));
    }

    public function isLazy(): bool
    {
        $lazy = $this->evaluate($this->lazyLoading);
        if ($lazy !== null) {
            return (bool) $lazy;
        }

        return $this->outline()->count() > $this->getLazyLoadingThreshold();
    }

    /** By default, multiple fields of fully loaded taxonomies show an inline tree, everything else a dropdown. */
    public function getLayout(): string
    {
        $tree = $this->evaluate($this->treeLayout);

        return ($tree ?? ($this->isMultiple() && ! $this->isLazy())) ? 'tree' : 'dropdown';
    }

    /** @return list<TreeNode> Every visible term, as the browser receives them without lazy loading. */
    public function getNodes(): array
    {
        $outline = $this->outline();

        return $this->buildNodes($outline->ids(), $outline, $this->getTaxonomy());
    }

    /** @return array<string, mixed> */
    public function getTreeConfiguration(): array
    {
        $outline = $this->outline();
        $taxonomy = $this->getTaxonomy();
        $lazy = $this->isLazy();
        $roots = $outline->children(null);

        $configuration = [
            'layout' => $this->getLayout(),
            'lazy' => $lazy,
            'multiple' => $this->isMultiple(),
            'selectAncestors' => $this->shouldSelectAncestors(),
            'emptyOption' => $this->isMultiple() ? null : $this->getEmptyOptionLabel(),
            'disabled' => $this->isDisabled(),
            'readOnly' => $this->isReadOnly(),
            'searchable' => $lazy || $outline->count() > self::SEARCH_MINIMUM,
            'nodes' => $this->buildNodes($lazy ? array_slice($roots, 0, self::PAGE_SIZE) : $outline->ids(), $outline, $taxonomy),
            'more' => $lazy && count($roots) > self::PAGE_SIZE,
            'selection' => [],
            'names' => [],
            'labels' => $this->getTreeLabels(),
        ];

        if ($lazy) {
            $selected = array_values(array_filter($this->stateIds(), $outline->has(...)));
            $nodes = $this->buildNodes($selected, $outline, $taxonomy);
            $configuration['selection'] = $nodes;
            $configuration['names'] = $this->ancestorNames($nodes, $outline);
        }

        return $configuration;
    }

    /**
     * One page of a branch for on-demand loading. Unknown and hidden parents return nothing.
     *
     * @return array{nodes: list<TreeNode>, more: bool}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getTreeChildren(mixed $parent = null, mixed $offset = 0): array
    {
        $empty = ['nodes' => [], 'more' => false];
        $outline = $this->browsableOutline();
        if ($outline === null) {
            return $empty;
        }
        $parentId = $parent === null ? null : TaxonomyIdentity::normalize($parent, TaxonomyIdentity::MAX_BROWSER_ID);
        if ($parent !== null && ($parentId === null || ! $outline->has($parentId))) {
            return $empty;
        }
        $start = is_int($offset) || is_string($offset)
            ? filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
            : false;
        $start = $start === false ? 0 : $start;
        $ids = $outline->children($parentId);

        return [
            'nodes' => $this->buildNodes(array_slice($ids, $start, self::PAGE_SIZE), $outline, $this->getTaxonomy()),
            'more' => count($ids) > $start + self::PAGE_SIZE,
        ];
    }

    /** @return array{nodes: list<TreeNode>, names: array<int, string>, total: int} */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function searchTree(mixed $search = ''): array
    {
        $outline = $this->browsableOutline();
        if ($outline === null || ! is_string($search)) {
            return ['nodes' => [], 'names' => [], 'total' => 0];
        }
        $result = $outline->search(mb_substr($search, 0, 255), self::SEARCH_LIMIT);
        $nodes = $this->buildNodes($result['ids'], $outline, $this->getTaxonomy());

        return ['nodes' => $nodes, 'names' => $this->ancestorNames($nodes, $outline), 'total' => $result['total']];
    }

    /**
     * Details of selected IDs that the browser has not loaded yet, for example after a form fill.
     *
     * @return array{nodes: list<TreeNode>, names: array<int, string>}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getTreeSelection(mixed $ids = []): array
    {
        $outline = $this->browsableOutline();
        if ($outline === null || ! is_array($ids)) {
            return ['nodes' => [], 'names' => []];
        }
        $known = [];
        foreach (array_slice($ids, 0, 1000) as $id) {
            $id = TaxonomyIdentity::normalize($id, TaxonomyIdentity::MAX_BROWSER_ID);
            if ($id !== null && $outline->has($id)) {
                $known[$id] = $id;
            }
        }
        $nodes = $this->buildNodes(array_values($known), $outline, $this->getTaxonomy());

        return ['nodes' => $nodes, 'names' => $this->ancestorNames($nodes, $outline)];
    }

    /** @return array<string, string> */
    protected function getTreeLabels(): array
    {
        $labels = [];
        foreach (array_keys(__('filament-taxonomies::' . $this->translationGroup, locale: 'en')) as $key) {
            $labels[$key] = __("filament-taxonomies::{$this->translationGroup}.{$key}");
        }

        return $labels;
    }

    /**
     * @param  list<int>  $ids
     * @return list<TreeNode>
     */
    protected function buildNodes(array $ids, TaxonomyOutline $outline, Taxonomy $taxonomy): array
    {
        $reasons = $this->reasons($ids, $outline, $taxonomy);

        return array_map(static fn (int $id): array => [
            'id' => $id,
            'name' => $outline->name($id),
            'ancestors' => $outline->ancestors($id),
            'hasChildren' => $outline->hasChildren($id),
            'disabled' => ($reasons[$id] ?? '') !== '',
            'reason' => $reasons[$id] ?? '',
        ], $ids);
    }

    /**
     * @param  list<TreeNode>  $nodes
     * @return array<int, string>
     */
    protected function ancestorNames(array $nodes, TaxonomyOutline $outline): array
    {
        $names = [];
        foreach ($nodes as $node) {
            foreach ($node['ancestors'] as $ancestor) {
                $names[$ancestor] ??= $outline->name($ancestor);
            }
        }

        return $names;
    }

    /** The visible structure of the current taxonomy, read once per request. */
    protected function outline(): TaxonomyOutline
    {
        $taxonomy = $this->getTaxonomy();
        if ($this->outline === null || $this->outlineTaxonomy !== $taxonomy->getKey()) {
            $this->outline = TaxonomyOutline::for($taxonomy);
            $this->outlineTaxonomy = $taxonomy->getKey();
        }

        return $this->outline;
    }

    /** Null when the field cannot currently resolve a browsable taxonomy. */
    protected function browsableOutline(): ?TaxonomyOutline
    {
        try {
            return $this->outline();
        } catch (InvalidTaxonomyAssignmentException | ModelNotFoundException) {
            return null;
        }
    }

    /** @return list<int> Browser-safe positive IDs in the current state, in order. */
    protected function stateIds(): array
    {
        $state = $this->getState();
        $ids = [];
        foreach (is_array($state) ? $state : [$state] as $value) {
            $id = TaxonomyIdentity::normalize($value, TaxonomyIdentity::MAX_BROWSER_ID);
            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
