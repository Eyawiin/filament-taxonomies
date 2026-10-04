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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;

class TaxonomyResource extends Resource
{
    protected static ?string $model = Taxonomy::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Taxonomies';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Taxonomies';

    protected static ?string $slug = 'taxonomies';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Explicit distinct key also makes Laravel's pagination count unique records.
        return $query->addSelect($query->qualifyColumn('*'))
            ->distinct([$query->getModel()->getQualifiedKeyName()])
            ->whereBetween($query->getModel()->getQualifiedKeyName(), [1, PHP_INT_MAX]);
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        if (! static::hasPage('view') && ! static::canEdit($record) && static::canView($record)) {
            return static::getUrl('manageTerms', ['record' => $record]);
        }

        return parent::getGlobalSearchResultUrl($record);
    }

    /** @internal Aggregate callback shared by navigation and table counts. */
    public static function countDistinctTerms(Builder $query): Builder
    {
        $key = $query->getQuery()->getGrammar()->wrap($query->getModel()->getQualifiedKeyName());

        return $query->select(new Expression("count(distinct {$key})"));
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        if (! static::canAccess()) {
            return [];
        }

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

        $query = static::getEloquentQuery();
        /** @var Taxonomy $taxonomy */
        foreach ($query->withCount(['terms' => static::countDistinctTerms(...)])->orderBy($query->qualifyColumn('name'))
            ->orderBy($query->qualifyColumn('id'))->get()->unique('id') as $index => $taxonomy) {
            if (! static::canView($taxonomy)) {
                continue;
            }

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
        return TaxonomiesTable::configure($table, static::class);
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
