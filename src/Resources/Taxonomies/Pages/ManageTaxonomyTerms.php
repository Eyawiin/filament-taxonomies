<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Schemas\TaxonomyTermForm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;

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
                    TaxonomyTerm::create([
                        'taxonomy_id' => $this->getRecord()->getKey(),
                        'parent_id' => $data['parent_id'] ?? null,
                        'name' => $data['name'],
                        'slug' => $data['slug'],
                    ]);
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
}
