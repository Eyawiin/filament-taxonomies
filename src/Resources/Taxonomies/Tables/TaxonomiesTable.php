<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Tables;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\TaxonomyResource;
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
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('terms_count')
                    ->counts('terms')
                    ->label('Terms'),
            ])
            ->recordActions([
                Action::make('manageTerms')
                    ->label('Manage Terms')
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
                DeleteAction::make(),
            ]);
    }
}
