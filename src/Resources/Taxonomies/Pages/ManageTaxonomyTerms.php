<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages;

use Eyawiin\FilamentTaxonomies\Enums\TaxonomyTermDropPosition;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyDropException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyOrderException;
use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Schemas\TaxonomyTermForm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class ManageTaxonomyTerms extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TaxonomyResource::class;

    protected string $view = 'filament-taxonomies::resources.taxonomies.pages.manage-taxonomy-terms';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getRecord(): Taxonomy
    {
        /** @var Taxonomy $record */
        $record = $this->record;

        return $record;
    }

    public function getHeading(): string
    {
        return "Manage Terms: {$this->getRecord()->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editTaxonomy')
                ->label('Edit Taxonomy')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => TaxonomyResource::getUrl('edit', [
                    'record' => $this->getRecord(),
                ]))
                ->visible(fn (): bool => TaxonomyResource::canEdit($this->getRecord())),

            DeleteAction::make('deleteTaxonomy')
                ->label('Delete Taxonomy')
                ->record(fn (): Taxonomy => $this->getRecord())
                ->modalDescription('Deleting this taxonomy will also delete all of its terms. Are you sure you want to continue?')
                ->successRedirectUrl(fn (): string => TaxonomyResource::getUrl('index'))
                ->visible(fn (): bool => TaxonomyResource::canDelete($this->getRecord())),

            Action::make('createTerm')
                ->label('Create Term')
                ->icon('heroicon-o-plus')
                ->schema(
                    fn (Schema $schema): Schema => TaxonomyTermForm::configure(
                        $schema,
                        $this->getRecord(),
                    ),
                )
                ->action(function (array $data): void {
                    $taxonomyId = (int) $this->getRecord()->getKey();

                    $parentId = empty($data['parent_id'])
                        ? null
                        : (int) $data['parent_id'];

                    $position = app(TaxonomyTreeService::class)->getNextPosition(
                        $taxonomyId,
                        $parentId,
                    );

                    TaxonomyTerm::create([
                        'taxonomy_id' => $taxonomyId,
                        'parent_id' => $parentId,
                        'name' => $data['name'],
                        'slug' => $data['slug'],
                        'position' => $position,
                    ]);

                    if ($parentId !== null) {
                        $this->dispatch(
                            'taxonomy-tree-expand-term',
                            termId: $parentId,
                        );
                    }
                }),
        ];
    }

    public function getTermTree(): array
    {
        return app(TaxonomyTreeService::class)->getTree($this->getRecord());
    }

    public function editTermAction(): Action
    {
        return Action::make('editTerm')
            ->label('Edit Term')
            ->icon('heroicon-o-pencil-square')
            ->iconButton()
            ->tooltip('Edit term')
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
                );
            })
            ->action(function (array $data, array $arguments): void {
                $term = $this->resolveTerm($arguments);

                $parent = null;

                if (! empty($data['parent_id'])) {
                    $parent = TaxonomyTerm::query()
                        ->where(
                            'taxonomy_id',
                            $this->getRecord()->getKey(),
                        )
                        ->findOrFail((int) $data['parent_id']);
                }

                $term->name = (string) $data['name'];
                $term->slug = (string) $data['slug'];

                app(TaxonomyTreeService::class)
                    ->setParent($term, $parent);
            });
    }

    private function resolveTerm(array $arguments): TaxonomyTerm
    {
        return TaxonomyTerm::query()
            ->where(
                'taxonomy_id',
                $this->getRecord()->getKey(),
            )
            ->findOrFail((int) ($arguments['term'] ?? 0));
    }

    public function deleteTermAction(): Action
    {
        return Action::make('deleteTerm')
            ->label('Delete Term')
            ->icon('heroicon-o-trash')
            ->iconButton()
            ->color('danger')
            ->tooltip('Delete term')
            ->requiresConfirmation()
            ->modalHeading('Delete term')
            ->modalDescription('Are you sure you want to delete this term? Its direct children will become root terms.')
            ->modalSubmitActionLabel('Delete')
            ->action(function (array $arguments): void {
                $term = $this->resolveTerm($arguments);

                $term->deleteOrFail();
            });
    }

    public function dropTerm(
        int $termId,
        int $targetId,
        string $placement,
    ): void {
        $this->resetErrorBag(['placement', 'drop']);

        $position = TaxonomyTermDropPosition::tryFrom($placement);

        if ($position === null) {
            throw ValidationException::withMessages([
                'placement' => 'The drop placement must be before, inside, or after.',
            ]);
        }

        $term = $this->resolveTerm(['term' => $termId]);
        $target = $this->resolveTerm(['term' => $targetId]);

        try {
            app(TaxonomyTreeService::class)->moveRelativeTo($term, $target, $position);
        } catch (InvalidTaxonomyDropException | InvalidTaxonomyParentException | InvalidTaxonomyOrderException $exception) {
            throw ValidationException::withMessages([
                'drop' => $exception->getMessage(),
            ]);
        }

        if ($position === TaxonomyTermDropPosition::Inside) {
            $this->dispatch(
                'taxonomy-tree-expand-term',
                termId: (int) $target->getKey(),
            );
        }
    }

    public function moveTerm(
        int $termId,
        int $position,
        ?int $parentId = null,
    ): void {
        $term = $this->resolveTerm([
            'term' => $termId,
        ]);

        $parent = $parentId === null
            ? null
            : $this->resolveTerm([
                'term' => $parentId,
            ]);

        $currentParentId = $term->parent_id === null
            ? null
            : (int) $term->parent_id;

        $newParentId = $parent === null
            ? null
            : (int) $parent->getKey();

        app(TaxonomyTreeService::class)->moveTerm(
            $term,
            $parent,
            $position,
        );

        if (
            $newParentId !== null
            && $currentParentId !== $newParentId
        ) {
            $this->dispatch(
                'taxonomy-tree-expand-term',
                termId: $newParentId,
            );
        }
    }
}
