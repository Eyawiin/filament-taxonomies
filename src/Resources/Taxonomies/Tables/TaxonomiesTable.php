<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Tables;

use Eyawiin\FilamentTaxonomies\Exceptions\InvalidTaxonomyParentException;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
use Eyawiin\FilamentTaxonomies\Services\TaxonomyTreeService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaxonomiesTable
{
    /**
     * @param  class-string<TaxonomyResource>  $resource
     */
    public static function configure(Table $table, string $resource = TaxonomyResource::class): Table
    {
        $modelClass = $resource::getModel();
        $model = new $modelClass;

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-taxonomies::taxonomies.fields.name'))
                    ->searchable([$model->qualifyColumn('name')])
                    ->sortable([$model->qualifyColumn('name')]),

                TextColumn::make('slug')
                    ->label(__('filament-taxonomies::taxonomies.fields.slug'))
                    ->searchable([$model->qualifyColumn('slug')])
                    ->sortable([$model->qualifyColumn('slug')]),

                TextColumn::make('terms_count')
                    ->counts(['terms' => $resource::countDistinctTerms(...)])
                    ->label(__('filament-taxonomies::taxonomies.fields.terms_count')),
            ])
            ->recordActions([
                Action::make('manageTerms')
                    ->label(__('filament-taxonomies::taxonomies.actions.manage_terms'))
                    ->icon('heroicon-o-list-bullet')
                    ->authorize(fn (Taxonomy $record): bool => $resource::canAccess()
                        && $resource::canView($record))
                    ->url(
                        fn (Taxonomy $record): string => $resource::getUrl(
                            'manageTerms',
                            ['record' => $record],
                        ),
                    ),
                EditAction::make(),
                DeleteAction::make()
                    ->failureNotificationTitle(__('filament-taxonomies::taxonomies.actions.delete_taxonomy_failed'))
                    ->using(function (Taxonomy $record, DeleteAction $action) use ($resource): bool {
                        $service = app(TaxonomyTreeService::class);

                        try {
                            $deleted = $service->withTaxonomyLock($record, function (Taxonomy $taxonomy) use ($service, $resource): bool {
                                /** @var Taxonomy $fresh */
                                $fresh = $resource::getEloquentQuery()->lockForUpdate()->findOrFail($taxonomy->getKey());
                                $resource::getDeleteAuthorizationResponse($fresh)->authorize();

                                return $service->deleteTaxonomy($fresh);
                            });
                        } catch (InvalidTaxonomyParentException $exception) {
                            // Domain messages are English sentences; JSON translations can localize them.
                            $action->failureNotificationBody(__($exception->getMessage()));

                            return false;
                        }

                        if ($deleted) {
                            // Preserve the native action record state for consumer after hooks.
                            $record->exists = false;
                        }

                        return $deleted;
                    }),
            ]);
    }
}
