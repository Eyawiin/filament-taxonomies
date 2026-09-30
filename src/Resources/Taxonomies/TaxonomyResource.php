<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies;

use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Schemas\TaxonomyForm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Tables\TaxonomiesTable;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TaxonomyResource extends Resource
{
    protected static ?string $model = Taxonomy::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Taxonomies';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Taxonomies';

    protected static ?string $slug = 'taxonomies';

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $items = [
            NavigationItem::make('Taxonomies')
                ->key(static::class)
                ->group(null)
                ->icon(static::getNavigationIcon())
                ->sort(0)
                ->isActiveWhen(fn (): bool => request()->routeIs(
                    static::getRouteBaseName() . '.index',
                ))
                ->url(static::getUrl('index')),
        ];

        foreach (Taxonomy::query()->withCount('terms')->orderBy('name')->orderBy('id')->get() as $index => $taxonomy) {
            $taxonomyId = (int) $taxonomy->getKey();

            $items[] = NavigationItem::make($taxonomy->name)
                ->key(static::class . '.taxonomy.' . $taxonomyId)
                ->group(static::getNavigationGroup())
                ->icon('heroicon-o-tag')
                ->sort($index + 1)
                ->badge((string) $taxonomy->terms_count)
                ->badgeTooltip("Number of terms in {$taxonomy->name}")
                ->isActiveWhen(
                    fn (): bool => request()->routeIs(static::getRouteBaseName() . '.manageTerms') &&
                    (string) request()->route('record') === (string) $taxonomyId
                )
                ->url(static::getUrl('manageTerms', ['record' => $taxonomyId]));
        }

        return $items;
    }

    public static function form(Schema $schema): Schema
    {
        return TaxonomyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxonomiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxonomies::route('/'),
            'create' => CreateTaxonomy::route('/create'),
            'edit' => EditTaxonomy::route('/{record}/edit'),
            'manageTerms' => ManageTaxonomyTerms::route('/{record}/manage-terms'),
        ];
    }
}
