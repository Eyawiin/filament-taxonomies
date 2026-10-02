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
use Eyawiin\FilamentTaxonomies\Support\TaxonomyIdentity;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class ManageTaxonomyTerms extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TaxonomyResource::class;

    private bool $isMutatingTerms = false;

    protected string $view = 'filament-taxonomies::resources.taxonomies.pages.manage-taxonomy-terms';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
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

    public function getRecord(): Taxonomy
    {
        abort_unless($this->record instanceof Taxonomy, 404);

        $record = $this->record;

        abort_unless(static::canAccess(['record' => $record]), 403);

        return $record;
    }

    public function getHeading(): string
    {
        return "Manage Terms: {$this->getRecord()->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTerm')
                ->label('Create Term')
                ->icon('heroicon-o-plus')
                ->authorize(fn (): Response => TaxonomyTermAuthorization::create($this->getRecord()))
                ->schema(
                    fn (Schema $schema): Schema => TaxonomyTermForm::configure(
                        $schema,
                        $this->getRecord(),
                        resource: static::getResource(),
                    ),
                )
                ->action(function (array $data, Schema $schema): void {
                    $this->mutateTermForm($schema, function (TaxonomyTreeService $service) use ($data, $schema): void {
                        TaxonomyTermAuthorization::create($this->getRecord())->authorize();
                        $parent = $this->resolveParent($data['parent_id'] ?? null, $schema);
                        $service->createTerm($this->getRecord(), (string) $data['name'], (string) $data['slug'], $parent);
                        if ($parent !== null) {
                            $this->expandAfterCommit((int) $parent->getKey());
                        }
                    });
                }),
        ];
    }

    public function getTermTree(): array
    {
        return TaxonomyIdentity::browserTree(app(TaxonomyTreeService::class)->getTree($this->getRecord()));
    }

    public function editTermAction(): Action
    {
        return Action::make('editTerm')
            ->label('Edit Term')
            ->icon('heroicon-o-pencil-square')
            ->iconButton()
            ->tooltip('Edit term')
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
                    resource: static::getResource(),
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
                });
            });
    }

    private function mutateTermForm(Schema $schema, Closure $operation): mixed
    {
        try {
            return $this->mutateTerms($operation);
        } catch (InvalidTaxonomyParentException $exception) {
            throw ValidationException::withMessages([
                $schema->getStatePath() . '.parent_id' => $exception->getMessage(),
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
        $parent = $id === null ? null : $this->getRecord()->terms()->lockForUpdate()->find($id, [(new TaxonomyTerm)->qualifyColumn('*')]);

        if ($parent === null) {
            throw ValidationException::withMessages([
                $schema->getStatePath() . '.parent_id' => 'The selected parent is no longer available.',
            ]);
        }

        return $parent;
    }

    private function resolveTerm(array $arguments): TaxonomyTerm
    {
        $query = $this->getRecord()->terms();
        if ($this->isMutatingTerms) {
            $query->lockForUpdate();
        }

        $id = $this->normalizeTermId($arguments['term'] ?? null);
        if ($id === null) {
            throw (new ModelNotFoundException)->setModel(TaxonomyTerm::class);
        }

        return $query->findOrFail($id, [$query->qualifyColumn('*')]);
    }

    private function normalizeTermId(mixed $value): ?int
    {
        return TaxonomyIdentity::normalize($value, TaxonomyIdentity::MAX_BROWSER_ID);
    }

    public function deleteTermAction(): Action
    {
        return Action::make('deleteTerm')
            ->label('Delete Term')
            ->icon('heroicon-o-trash')
            ->iconButton()
            ->color('danger')
            ->tooltip('Delete term')
            ->authorize(fn (array $arguments): Response => TaxonomyTermAuthorization::delete($this->resolveTerm($arguments)))
            ->requiresConfirmation()
            ->modalHeading('Delete term')
            ->modalDescription('Are you sure you want to delete this term? Its direct children will become root terms.')
            ->modalSubmitActionLabel('Delete')
            ->failureNotificationTitle('The term could not be deleted')
            ->action(function (array $arguments, Action $action): void {
                try {
                    $deleted = $this->mutateTerms(function (TaxonomyTreeService $service) use ($arguments): bool {
                        $term = $this->resolveTerm($arguments);
                        TaxonomyTermAuthorization::delete($term)->authorize();

                        return $service->deleteTerm($term);
                    });
                } catch (InvalidTaxonomyParentException $exception) {
                    $action->failureNotificationTitle('Unable to delete term')
                        ->failureNotificationBody($exception->getMessage());
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
                'placement' => 'The drop placement must be before, inside, or after.',
            ]);
        }

        $this->mutateTerms(function (TaxonomyTreeService $service) use ($termId, $targetId, $position): void {
            $term = $this->resolveTerm(['term' => $termId]);
            TaxonomyTermAuthorization::update($term)->authorize();
            $target = $this->resolveTerm(['term' => $targetId]);

            try {
                $service->moveRelativeTo($term, $target, $position);
            } catch (InvalidTaxonomyDropException | InvalidTaxonomyParentException | InvalidTaxonomyOrderException $exception) {
                throw ValidationException::withMessages(['drop' => $exception->getMessage()]);
            }

            if ($position === TaxonomyTermDropPosition::Inside) {
                $this->expandAfterCommit((int) $target->getKey());
            }
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
            throw ValidationException::withMessages(['move' => 'The term position must be a non-negative integer.']);
        }

        $this->mutateTerms(function (TaxonomyTreeService $service) use ($termId, $position, $parentId): void {
            $term = $this->resolveTerm(['term' => $termId]);
            TaxonomyTermAuthorization::update($term)->authorize();
            $parent = $parentId === null ? null : $this->resolveTerm(['term' => $parentId]);
            $oldParentId = $term->parent_id === null ? null : (int) $term->parent_id;

            try {
                $service->moveTerm($term, $parent, $position);
            } catch (InvalidTaxonomyParentException | InvalidTaxonomyOrderException $exception) {
                throw ValidationException::withMessages(['move' => $exception->getMessage()]);
            }
            if ($parent !== null && $oldParentId !== (int) $parent->getKey()) {
                $this->expandAfterCommit((int) $parent->getKey());
            }
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
            }
        });
    }

    private function expandAfterCommit(int $termId): void
    {
        $this->getRecord()->getConnection()->afterCommit(
            fn () => $this->dispatch('taxonomy-tree-expand-term', termId: $termId),
        );
    }
}
