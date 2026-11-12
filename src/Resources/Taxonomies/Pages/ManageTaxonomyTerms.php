<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages;

use Closure;
use Eyawiin\FilamentTaxonomies\Authorization\TaxonomyTermAuthorization;
use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyDropException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Forms\TaxonomySlugValidation;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Schemas\TaxonomyTermForm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Eyawiin\FilamentTaxonomies\Support\ManagedTermTree;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyIdentity;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyOutline;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-import-type ManagedBranch from ManagedTermTree
 */
class ManageTaxonomyTerms extends Page
{
    use InteractsWithRecord;

    /** Children shown per branch, and added by "Show more", when the tree loads on demand. */
    public const TERMS_PER_PAGE = 50;

    public const SEARCH_LIMIT = 100;

    /** Bounds the expanded branches remembered per taxonomy. */
    public const MAX_EXPANDED = 500;

    protected static string $resource = TaxonomyResource::class;

    public string $termSearch = '';

    /** @var array<int|string, mixed> Children shown per branch, keyed by term ID or 'root'. Browser state; read through termLimits(). */
    public array $termLimits = [];

    /**
     * Branches expanded on demand-loaded trees. The session keeps them across visits; this
     * property keeps them between requests, also in panels without session middleware.
     *
     * @var array<int|string, mixed> Browser state; read through expandedTermIds().
     */
    public array $expandedTermIds = [];

    private bool $isMutatingTerms = false;

    private ?TaxonomyOutline $outline = null;

    protected string $view = 'filament-taxonomies::resources.taxonomies.pages.manage-taxonomy-terms';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $stored = session()->get($this->expansionKey(), []);
        $this->expandedTermIds = is_array($stored) ? $stored : [];
        $this->expandedTermIds = $this->expandedTermIds();
    }

    public function hydrate(): void
    {
        // Livewire restores Eloquent models without their query scopes.
        $this->record = $this->resolveRecord($this->getRecord()->getRouteKey());
    }

    public static function canAccess(array $parameters = []): bool
    {
        $resource = static::getResource();

        return $resource::canAccess()
            && isset($parameters['record'])
            && $resource::canView($parameters['record']);
    }

    /** @return class-string<FilamentResource> */
    private function managingResource(): string
    {
        /** @var class-string<FilamentResource> $resource Filament declares a plain class-string. */
        $resource = static::getResource();

        return $resource;
    }

    public function getRecord(): Taxonomy
    {
        abort_unless($this->record instanceof Taxonomy, 404);

        $record = $this->record;

        abort_unless(static::canAccess(['record' => $record]), 403);

        return $record;
    }

    public function getTitle(): string | Htmlable
    {
        return __('filament-taxonomies::taxonomies.manage_terms.title');
    }

    public function getHeading(): string
    {
        return __('filament-taxonomies::taxonomies.manage_terms.heading', ['name' => $this->getRecord()->name]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTerm')
                ->label(__('filament-taxonomies::taxonomies.actions.create_term'))
                ->icon('heroicon-o-plus')
                ->authorize(fn (): Response => TaxonomyTermAuthorization::create($this->getRecord()))
                ->schema(
                    fn (Schema $schema): Schema => TaxonomyTermForm::configure(
                        $schema,
                        $this->getRecord(),
                        resource: $this->managingResource(),
                    ),
                )
                ->action(function (array $data, Schema $schema): void {
                    $this->mutateTermForm($schema, function (TaxonomyTreeService $service) use ($data, $schema): void {
                        TaxonomyTermAuthorization::create($this->getRecord())->authorize();
                        $parent = $this->resolveParent($data['parent_id'] ?? null, $schema);
                        $term = $service->createTerm($this->getRecord(), (string) $data['name'], (string) $data['slug'], $parent);
                        if ($parent !== null) {
                            $this->expandAfterCommit((int) $parent->getKey());
                        }
                        $this->revealAfterCommit((int) $term->getKey());
                    });
                }),
        ];
    }

    /**
     * The rendered part of the tree: everything for small taxonomies, otherwise the expanded
     * branches a page at a time. A search shows matches below their ancestors instead.
     *
     * @return array{nodes: list<mixed>, remaining: int, total: int|null}
     */
    public function getTermTree(): array
    {
        $outline = $this->outline();
        $search = $this->getTermSearch();
        if ($search !== '') {
            return ManagedTermTree::search($this->getRecord(), $outline, $search, self::SEARCH_LIMIT);
        }
        $tree = $this->isLazy()
            ? ManagedTermTree::expanded($this->getRecord(), $outline, array_fill_keys($this->expandedTermIds(), true), $this->termLimits(), self::TERMS_PER_PAGE)
            : ManagedTermTree::complete($this->getRecord(), $outline);

        return [...$tree, 'total' => null];
    }

    /** Large taxonomies load their branches on demand; see FilamentTaxonomiesPlugin::lazyLoadingThreshold(). */
    public function isLazy(): bool
    {
        return $this->outline()->count() > TaxonomyResource::plugin()->getLazyLoadingThreshold();
    }

    public function getTermSearch(): string
    {
        return trim(mb_substr($this->termSearch, 0, 255));
    }

    public function hasTerms(): bool
    {
        return $this->outline()->count() > 0;
    }

    public function toggleTerm(mixed $termId): void
    {
        $id = $this->normalizeTermId($termId);
        if ($id === null) {
            return;
        }
        $expanded = $this->expandedTermIds();
        $this->storeExpandedTermIds(in_array($id, $expanded, true)
            ? array_values(array_diff($expanded, [$id]))
            : [...$expanded, $id]);
    }

    /** Opens a branch, for example while a dragged term hovers over it. */
    public function expandTerm(mixed $termId): void
    {
        $id = $this->normalizeTermId($termId);
        if ($id !== null && ! in_array($id, $this->expandedTermIds(), true)) {
            $this->storeExpandedTermIds([...$this->expandedTermIds(), $id]);
        }
    }

    public function collapseAllTerms(): void
    {
        $this->storeExpandedTermIds([]);
        $this->termLimits = [];
    }

    public function showMoreTerms(mixed $parentId = null): void
    {
        $id = $parentId === null ? null : $this->normalizeTermId($parentId);
        if ($parentId !== null && $id === null) {
            return;
        }
        $limits = $this->termLimits();
        $key = $id ?? 'root';
        $limits[$key] = min(
            count($this->outline()->children($id)),
            max(self::TERMS_PER_PAGE, $limits[$key] ?? 0) + self::TERMS_PER_PAGE,
        );
        $this->termLimits = $limits;
    }

    /**
     * The public property is browser state; keep only sane page sizes for real branches.
     *
     * @return array<int|string, int>
     */
    private function termLimits(): array
    {
        $limits = [];
        foreach ($this->termLimits as $key => $limit) {
            $branch = $key === 'root' ? 'root' : $this->normalizeTermId($key);
            if ($branch !== null && is_int($limit) && $limit > 0) {
                $limits[$branch] = min($limit, 10_000);
            }
        }

        return $limits;
    }

    /** @return list<int> */
    private function expandedTermIds(): array
    {
        $ids = [];
        foreach ($this->expandedTermIds as $id) {
            $id = $this->normalizeTermId($id);
            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return array_slice($ids, -self::MAX_EXPANDED);
    }

    /** @param list<int> $ids */
    private function storeExpandedTermIds(array $ids): void
    {
        $this->expandedTermIds = array_slice(array_values(array_unique($ids)), -self::MAX_EXPANDED);
        session()->put($this->expansionKey(), $this->expandedTermIds);
    }

    private function expansionKey(): string
    {
        return 'filament-taxonomies.expanded.' . $this->getRecord()->getKey();
    }

    private function outline(): TaxonomyOutline
    {
        return $this->outline ??= TaxonomyOutline::for($this->getRecord());
    }

    public function editTermAction(): Action
    {
        return Action::make('editTerm')
            ->label(__('filament-taxonomies::taxonomies.actions.edit_term'))
            ->icon('heroicon-o-pencil-square')
            ->iconButton()
            ->tooltip(__('filament-taxonomies::taxonomies.actions.edit_term_tooltip'))
            ->authorize(fn (array $arguments): Response => TaxonomyTermAuthorization::update($this->resolveTerm($arguments)))
            ->fillForm(function (array $arguments): array {
                $term = $this->resolveTerm($arguments);

                return [
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'parent_id' => $term->parent_id,
                ];
            })
            ->schema(function (Schema $schema, array $arguments): Schema {
                $term = $this->resolveTerm($arguments);

                return TaxonomyTermForm::configure(
                    $schema,
                    $this->getRecord(),
                    $term,
                    resource: $this->managingResource(),
                );
            })
            ->action(function (array $data, array $arguments, Schema $schema): void {
                $this->mutateTermForm($schema, function (TaxonomyTreeService $service) use ($data, $arguments, $schema): void {
                    $term = $this->resolveTerm($arguments);
                    TaxonomyTermAuthorization::update($term)->authorize();
                    $parent = $this->resolveParent($data['parent_id'] ?? null, $schema);
                    $term->name = (string) $data['name'];
                    $term->slug = (string) $data['slug'];
                    $service->setParent($term, $parent);
                    $this->revealAfterCommit((int) $term->getKey());
                });
            });
    }

    private function mutateTermForm(Schema $schema, Closure $operation): mixed
    {
        try {
            return $this->mutateTerms($operation);
        } catch (InvalidTaxonomyParentException $exception) {
            // Domain messages are English sentences; JSON translations can localize them.
            throw ValidationException::withMessages([
                $schema->getStatePath() . '.parent_id' => __($exception->getMessage()),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            TaxonomySlugValidation::report($exception, 'taxonomy_terms', $schema->getStatePath() . '.slug');
        }
    }

    private function resolveParent(mixed $value, Schema $schema): ?TaxonomyTerm
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = $this->normalizeTermId($value);
        $terms = $this->getRecord()->terms();
        $parent = $id === null ? null : $terms->lockForUpdate()->find($id, [$terms->getRelated()->qualifyColumn('*')]);

        if ($parent === null) {
            throw ValidationException::withMessages([
                $schema->getStatePath() . '.parent_id' => __('filament-taxonomies::taxonomies.validation.parent_unavailable'),
            ]);
        }

        return $parent;
    }

    /** @param array<string, mixed> $arguments */
    private function resolveTerm(array $arguments): TaxonomyTerm
    {
        $query = $this->getRecord()->terms();
        if ($this->isMutatingTerms) {
            $query->lockForUpdate();
        }

        $id = $this->normalizeTermId($arguments['term'] ?? null);
        if ($id === null) {
            throw (new ModelNotFoundException)->setModel(TaxonomyModels::term());
        }

        return $query->findOrFail($id, [$query->getRelated()->qualifyColumn('*')]);
    }

    private function normalizeTermId(mixed $value): ?int
    {
        return TaxonomyIdentity::normalize($value, TaxonomyIdentity::MAX_BROWSER_ID);
    }

    public function deleteTermAction(): Action
    {
        return Action::make('deleteTerm')
            ->label(__('filament-taxonomies::taxonomies.actions.delete_term'))
            ->icon('heroicon-o-trash')
            ->iconButton()
            ->color('danger')
            ->tooltip(__('filament-taxonomies::taxonomies.actions.delete_term_tooltip'))
            ->authorize(fn (array $arguments): Response => TaxonomyTermAuthorization::delete($this->resolveTerm($arguments)))
            ->requiresConfirmation()
            ->modalHeading(__('filament-taxonomies::taxonomies.actions.delete_term_heading'))
            ->modalDescription(__('filament-taxonomies::taxonomies.actions.delete_term_description'))
            ->modalSubmitActionLabel(__('filament-taxonomies::taxonomies.actions.delete_term_submit'))
            ->failureNotificationTitle(__('filament-taxonomies::taxonomies.actions.delete_term_failed'))
            ->action(function (array $arguments, Action $action): void {
                try {
                    $deleted = $this->mutateTerms(function (TaxonomyTreeService $service) use ($arguments): bool {
                        $term = $this->resolveTerm($arguments);
                        TaxonomyTermAuthorization::delete($term)->authorize();

                        return $service->deleteTerm($term);
                    });
                } catch (InvalidTaxonomyParentException $exception) {
                    $action->failureNotificationTitle(__('filament-taxonomies::taxonomies.actions.delete_term_rejected'))
                        ->failureNotificationBody(__($exception->getMessage()));
                    $deleted = false;
                }
                if (! $deleted) {
                    $action->failure();
                }
            });
    }

    public function dropTerm(
        mixed $termId,
        mixed $targetId,
        mixed $placement,
    ): void {
        $this->resetErrorBag(['placement', 'drop']);

        $position = is_string($placement) ? TaxonomyTermDropPosition::tryFrom($placement) : null;

        if ($position === null) {
            throw ValidationException::withMessages([
                'placement' => __('filament-taxonomies::taxonomies.validation.placement'),
            ]);
        }

        $this->mutateTerms(function (TaxonomyTreeService $service) use ($termId, $targetId, $position): void {
            $term = $this->resolveTerm(['term' => $termId]);
            TaxonomyTermAuthorization::update($term)->authorize();
            $target = $this->resolveTerm(['term' => $targetId]);

            try {
                $service->moveRelativeTo($term, $target, $position);
            } catch (InvalidTaxonomyDropException | InvalidTaxonomyParentException | InvalidTaxonomyOrderException $exception) {
                throw ValidationException::withMessages(['drop' => __($exception->getMessage())]);
            }

            if ($position === TaxonomyTermDropPosition::Inside) {
                $this->expandAfterCommit((int) $target->getKey());
            }
            $this->revealAfterCommit((int) $term->getKey());
        });
    }

    public function moveTerm(
        mixed $termId,
        mixed $position,
        mixed $parentId = null,
    ): void {
        $this->resetErrorBag('move');
        $position = is_int($position) || is_string($position)
            ? filter_var($position, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])
            : false;
        if ($position === false) {
            throw ValidationException::withMessages(['move' => __('filament-taxonomies::taxonomies.validation.position')]);
        }

        $this->mutateTerms(function (TaxonomyTreeService $service) use ($termId, $position, $parentId): void {
            $term = $this->resolveTerm(['term' => $termId]);
            TaxonomyTermAuthorization::update($term)->authorize();
            $parent = $parentId === null ? null : $this->resolveTerm(['term' => $parentId]);
            $oldParentId = $term->parent_id === null ? null : (int) $term->parent_id;

            try {
                $service->moveTerm($term, $parent, $position);
            } catch (InvalidTaxonomyParentException | InvalidTaxonomyOrderException $exception) {
                throw ValidationException::withMessages(['move' => __($exception->getMessage())]);
            }
            if ($parent !== null && $oldParentId !== (int) $parent->getKey()) {
                $this->expandAfterCommit((int) $parent->getKey());
            }
            $this->revealAfterCommit((int) $term->getKey());
        });
    }

    private function mutateTerms(Closure $operation): mixed
    {
        $service = app(TaxonomyTreeService::class);

        return $service->withTaxonomyLock($this->getRecord(), function (Taxonomy $taxonomy) use ($service, $operation): mixed {
            // Permission and resource scope may have changed while waiting for the lock.
            $resource = static::getResource();
            $this->record = $resource::getEloquentQuery()->lockForUpdate()->findOrFail($taxonomy->getKey());
            $this->getRecord();

            $this->isMutatingTerms = true;

            try {
                return $operation($service);
            } finally {
                $this->isMutatingTerms = false;
                $this->outline = null;
            }
        });
    }

    /** Fully rendered trees expand the branch in the browser. */
    private function expandAfterCommit(int $termId): void
    {
        $this->getRecord()->getConnection()->afterCommit(function () use ($termId): void {
            $this->outline = null;
            if (! $this->isLazy()) {
                $this->dispatch('taxonomy-tree-expand-term', termId: $termId);
            }
        });
    }

    /** After a change on demand-loaded trees, open every ancestor and page so the term is shown. */
    private function revealAfterCommit(int $termId): void
    {
        $this->getRecord()->getConnection()->afterCommit(function () use ($termId): void {
            $this->outline = null;
            $outline = $this->outline();
            if (! $this->isLazy() || ! $outline->has($termId)) {
                return;
            }
            $ancestors = $outline->ancestors($termId);
            $this->storeExpandedTermIds([...$this->expandedTermIds(), ...$ancestors]);
            $parent = $outline->parent($termId);
            $limits = $this->termLimits();
            $key = $parent ?? 'root';
            $index = array_search($termId, $outline->children($parent), true);
            if ($index !== false && $index >= max(self::TERMS_PER_PAGE, $limits[$key] ?? 0)) {
                $limits[$key] = $index + 1;
                $this->termLimits = $limits;
            }
        });
    }
}
