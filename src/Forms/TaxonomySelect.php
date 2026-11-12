<?php

namespace Eyawiin\FilamentTaxonomies\Forms;

use Closure;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyAssignmentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyAssignmentService;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyIdentity;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyOutline;
use Filament\Forms\Components\Repeater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * A taxonomy-scoped relationship field. The owner must use HasTaxonomies.
 */
class TaxonomySelect extends TaxonomyTreeField
{
    protected string $translationGroup = 'assignment-tree';

    protected mixed $taxonomyReference = null;

    protected bool | Closure $multiple = false;

    protected bool | Closure $selectAncestors = false;

    protected ?Closure $assignmentAuthorization = null;

    protected ?Closure $termDisabled = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->default(static fn (TaxonomySelect $component): mixed => $component->isMultiple() ? [] : null)
            ->dehydrated(false)
            ->saved(static fn (TaxonomySelect $component): bool => ! $component->isDisabled() && ! $component->isReadOnly())
            ->validatedWhenNotDehydrated(static fn (TaxonomySelect $component): bool => ! $component->isDisabled() && ! $component->isReadOnly())
            ->rules(static fn (TaxonomySelect $component): array => [new TaxonomySelectionRule($component)])
            ->loadStateFromRelationshipsUsing(static function (TaxonomySelect $component): void {
                if ($component->isInJsonRepeater()) {
                    return;
                }
                $record = $component->getRecord();
                if (! $record instanceof Model) {
                    return;
                }
                $taxonomy = $component->getTaxonomy();
                $ids = app(TaxonomyAssignmentService::class)->assignedTermIds($record, $taxonomy);
                $ids = $component->expandAncestorSelection($ids, $taxonomy);
                // Preserve an incompatible existing selection until the user explicitly replaces it.
                $component->state($component->isMultiple() ? $ids : (count($ids) > 1 ? $ids : ($ids[0] ?? null)));
            })
            ->saveRelationshipsUsing(static function (TaxonomySelect $component): void {
                if ($component->isReadOnly()) {
                    return;
                }
                $component->assertAssignmentContext();
                if ($component->isInJsonRepeater()) {
                    return;
                }
                $record = $component->getRecord();
                if (! $record instanceof Model) {
                    throw new LogicException('TaxonomySelect requires an Eloquent owner.');
                }

                try {
                    $taxonomy = $component->getTaxonomy();
                    app(TaxonomyTreeService::class)->withTaxonomyLock($taxonomy, function (Taxonomy $fresh) use ($component, $record): void {
                        if ($component->getTaxonomy()->getKey() !== $fresh->getKey() || ! $component->acceptsSelectionForTaxonomy($component->getState(), $fresh)) {
                            $component->failSelection();
                        }
                        app(TaxonomyAssignmentService::class)->sync($record, $fresh, $component->selectionIds($component->getState()) ?? []);
                    });
                } catch (InvalidTaxonomyAssignmentException | ModelNotFoundException) {
                    $component->failSelection();
                }
            });
    }

    /** @param Taxonomy|int|string|Closure $taxonomy */
    public function taxonomy(mixed $taxonomy): static
    {
        $this->taxonomyReference = $taxonomy;

        return $this;
    }

    public function multiple(bool | Closure $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->multiple);
    }

    /** In multiple mode, selecting a child also assigns its visible, permitted ancestors. */
    public function selectAncestors(bool | Closure $condition = true): static
    {
        $this->selectAncestors = $condition;

        return $this;
    }

    public function shouldSelectAncestors(): bool
    {
        return $this->isMultiple() && (bool) $this->evaluate($this->selectAncestors);
    }

    /** Callback receives taxonomy and record (null during creation). Applies to clearing too. */
    public function canAssignUsing(Closure $callback): static
    {
        $this->assignmentAuthorization = $callback;

        return $this;
    }

    /** Callback receives term, taxonomy and record; return true to forbid assigning a term. */
    public function disableTermWhen(Closure $callback): static
    {
        $this->termDisabled = $callback;

        return $this;
    }

    public function getTaxonomy(): Taxonomy
    {
        return app(TaxonomyAssignmentService::class)->resolveTaxonomy($this->evaluate($this->taxonomyReference));
    }

    public function getEmptyOptionLabel(): ?string
    {
        return __('filament-taxonomies::assignment-tree.none');
    }

    public function getTreeConfiguration(): array
    {
        $this->assertAssignmentContext();
        $configuration = parent::getTreeConfiguration();
        $configuration['readOnly'] = $configuration['readOnly'] || ! $this->canAssign($this->getTaxonomy());

        return $configuration;
    }

    public function getNodes(): array
    {
        $this->assertAssignmentContext();

        return parent::getNodes();
    }

    protected function reasons(array $ids, TaxonomyOutline $outline, Taxonomy $taxonomy): array
    {
        if (! $this->canAssign($taxonomy)) {
            return array_fill_keys($ids, __('filament-taxonomies::assignment-tree.denied'));
        }
        $withAncestors = $this->shouldSelectAncestors();
        $checked = $ids;
        if ($withAncestors) {
            foreach ($ids as $id) {
                array_push($checked, ...$outline->ancestors($id));
            }
        }
        $denied = $this->deniedTerms(array_values(array_unique($checked)), $taxonomy);

        $reasons = [];
        foreach ($ids as $id) {
            $reasons[$id] = match (true) {
                isset($denied[$id]) => __('filament-taxonomies::assignment-tree.denied'),
                $withAncestors && array_intersect_key($denied, array_flip($outline->ancestors($id))) !== [] => __('filament-taxonomies::assignment-tree.ancestor_denied'),
                default => '',
            };
        }

        return $reasons;
    }

    /**
     * Terms that disableTermWhen() forbids, keyed by ID.
     *
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    protected function deniedTerms(array $ids, Taxonomy $taxonomy): array
    {
        if ($this->termDisabled === null || $ids === []) {
            return [];
        }
        $terms = $taxonomy->terms();
        $model = $terms->getRelated();
        // Large requests come from fully loaded trees; one read of the taxonomy beats many key lists.
        $query = count($ids) > 500 ? $terms : $terms->whereIn($model->getQualifiedKeyName(), $ids);
        $wanted = array_flip($ids);
        $denied = [];
        foreach ($query->get([$model->qualifyColumn('*')]) as $term) {
            $id = TaxonomyIdentity::normalize($term->getRawOriginal($term->getKeyName()));
            if ($id !== null && isset($wanted[$id]) && $this->isTermDisabled($term, $taxonomy)) {
                $denied[$id] = true;
            }
        }

        return $denied;
    }

    /** @internal Native form validation acquires locks before owner writes in an outer transaction. */
    public function lockFormTaxonomies(): void
    {
        $taxonomies = [];
        $owners = [];
        foreach ($this->getRootContainer()->getFlatComponents() as $component) {
            if (! $component instanceof self || ! $component->isSaved() || $component->isHidden()) {
                continue;
            }

            $component->assertAssignmentContext();

            try {
                $taxonomy = $component->getTaxonomy();
                $scope = $component->assignmentOwnerScope() . ':' . $taxonomy->getKey();
                if (isset($owners[$scope]) && $owners[$scope] !== $component->getStatePath()) {
                    throw new LogicException('Use one TaxonomySelect field per taxonomy per owner.');
                }
                $owners[$scope] = $component->getStatePath();
                $taxonomies[$taxonomy->getKey()] = $taxonomy;
            } catch (InvalidTaxonomyAssignmentException | ModelNotFoundException) {
                // Its own rule reports a field error; never skip that validation.
            }
        }
        if (DB::transactionLevel() === 0) {
            return;
        }
        ksort($taxonomies, SORT_NUMERIC);
        foreach ($taxonomies as $taxonomy) {
            app(TaxonomyTreeService::class)->withTaxonomyLock($taxonomy, static fn (): null => null);
        }
    }

    /** @internal Used both by validation and immediately before saving under the taxonomy lock. */
    public function acceptsSelection(mixed $state): bool
    {
        try {
            return $this->acceptsSelectionForTaxonomy($state, $this->getTaxonomy());
        } catch (InvalidTaxonomyAssignmentException | ModelNotFoundException) {
            return false;
        }
    }

    protected function acceptsSelectionForTaxonomy(mixed $state, Taxonomy $taxonomy): bool
    {
        $ids = $this->selectionIds($state);
        if ($ids === null || ! $this->canAssign($taxonomy)) {
            return false;
        }
        // A fresh read: under the taxonomy lock this must not reuse what the form rendered.
        $outline = TaxonomyOutline::for($taxonomy);
        foreach ($ids as $id) {
            if (! $outline->has($id)) {
                return false;
            }
        }
        if (array_filter($this->reasons($ids, $outline, $taxonomy)) !== []) {
            return false;
        }
        if ($this->shouldSelectAncestors()) {
            foreach ($ids as $id) {
                if (array_diff($outline->ancestors($id), $ids) !== []) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Expand existing visible assignments in form state; unavailable IDs stay intact.
     *
     * @param  list<int|string>  $ids
     * @return list<int|string>
     */
    protected function expandAncestorSelection(array $ids, Taxonomy $taxonomy): array
    {
        if (! $this->shouldSelectAncestors() || $this->isDisabled() || $this->isReadOnly()) {
            return $ids;
        }
        $outline = $this->outline();
        $visible = array_values(array_filter(
            array_map(static fn (int | string $id): ?int => TaxonomyIdentity::normalize($id, TaxonomyIdentity::MAX_BROWSER_ID), $ids),
            static fn (?int $id): bool => $id !== null && $outline->has($id),
        ));
        $reasons = $this->reasons($visible, $outline, $taxonomy);
        $expanded = $ids;
        foreach ($visible as $id) {
            if ($reasons[$id] !== '') {
                continue;
            }
            foreach ($outline->ancestors($id) as $ancestor) {
                if (! in_array($ancestor, $expanded, false)) {
                    $expanded[] = $ancestor;
                }
            }
        }

        return $expanded;
    }

    /** @return list<int>|null */
    protected function selectionIds(mixed $state): ?array
    {
        if ($state === null || $state === '') {
            return [];
        }
        if ($this->isMultiple()) {
            if (! is_array($state) || ! array_is_list($state)) {
                return null;
            }
            $values = $state;
        } else {
            if (is_array($state)) {
                return null;
            }
            $values = [$state];
        }
        $ids = [];
        foreach ($values as $value) {
            $id = TaxonomyIdentity::normalize($value);
            if ($id === null || $id > TaxonomyIdentity::MAX_BROWSER_ID || in_array($id, $ids, true)) {
                return null;
            }
            $ids[] = $id;
        }

        return $ids;
    }

    protected function canAssign(Taxonomy $taxonomy): bool
    {
        return $this->assignmentAuthorization === null || (bool) $this->evaluate($this->assignmentAuthorization, ['taxonomy' => $taxonomy]);
    }

    protected function isTermDisabled(TaxonomyTerm $term, Taxonomy $taxonomy): bool
    {
        return $this->termDisabled !== null && (bool) $this->evaluate($this->termDisabled, ['term' => $term, 'taxonomy' => $taxonomy]);
    }

    protected function assignmentOwnerScope(): string
    {
        $record = $this->getRecord();
        if ($record instanceof Model && $record->exists) {
            return $record->getMorphClass() . ':' . $record->getRawOriginal($record->getKeyName());
        }
        $container = $this->getContainer();
        while (($parent = $container->getParentComponent()) !== null) {
            if ($parent instanceof Repeater && $parent->hasRelationship()) {
                return ($this->getModel() ?? '') . ':new:' . $container->getStatePath();
            }
            $container = $parent->getContainer();
        }

        return ($this->getModel() ?? '') . ':root';
    }

    protected function assertAssignmentContext(): void
    {
        if ($this->isInJsonRepeater() && $this->isSaved()) {
            throw new LogicException('TaxonomySelect in a JSON repeater requires saved(false)->dehydrated(); use a relationship repeater for owner assignments.');
        }
    }

    /** Plain JSON rows do not have their own Eloquent owner. */
    protected function isInJsonRepeater(): bool
    {
        $container = $this->getContainer();
        while (($parent = $container->getParentComponent()) !== null) {
            if ($parent instanceof Repeater) {
                return ! $parent->hasRelationship();
            }
            $container = $parent->getContainer();
        }

        return false;
    }

    protected function failSelection(): never
    {
        throw ValidationException::withMessages([($this->getStatePath() ?? $this->getName()) => __('filament-taxonomies::assignment-tree.invalid')]);
    }
}
