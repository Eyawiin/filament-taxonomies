<?php

namespace Eyawiin\FilamentTaxonomies\Resources\Taxonomies;

use BackedEnum;
use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesPlugin;
use Eyawiin\FilamentTaxonomies\Models\Taxonomy;
use Eyawiin\FilamentTaxonomies\Models\TaxonomyTerm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\CreateTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\EditTaxonomy;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ListTaxonomies;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Schemas\TaxonomyForm;
use Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Tables\TaxonomiesTable;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use UnitEnum;

class TaxonomyResource extends Resource
{
    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $slug = 'taxonomies';

    /** @return class-string<Model> */
    public static function getModel(): string
    {
        return static::$model ?? TaxonomyModels::taxonomy();
    }

    public static function getModelLabel(): string
    {
        return static::$modelLabel ?? static::getLabel() ?? __('filament-taxonomies::taxonomies.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return static::$pluralModelLabel ?? static::getPluralLabel() ?? __('filament-taxonomies::taxonomies.resource.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return static::plugin()->getNavigationLabel() ?? parent::getNavigationLabel();
    }

    public static function getNavigationIcon(): string | BackedEnum | Htmlable | null
    {
        return static::plugin()->getNavigationIcon() ?? parent::getNavigationIcon();
    }

    public static function getNavigationGroup(): string | UnitEnum | null
    {
        $plugin = static::plugin();

        return $plugin->hasNavigationGroup() ? $plugin->getNavigationGroup() : parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return static::plugin()->getNavigationSort() ?? parent::getNavigationSort();
    }

    /** Settings of the plugin on the current panel, or its defaults when the resource is registered directly. */
    protected static function plugin(): FilamentTaxonomiesPlugin
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $plugin = $panel?->hasPlugin('filament-taxonomies') ? $panel->getPlugin('filament-taxonomies') : null;

        return $plugin instanceof FilamentTaxonomiesPlugin ? $plugin : FilamentTaxonomiesPlugin::make();
    }

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

    /**
     * @internal Aggregate callback shared by navigation and table counts.
     *
     * @param  Builder<TaxonomyTerm>  $query
     * @return Builder<TaxonomyTerm>
     */
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

        if (! static::plugin()->hasTaxonomyNavigation()) {
            return parent::getNavigationItems();
        }

        $items = [
            NavigationItem::make(static::getNavigationLabel())
                ->key(static::class)
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->sort(static::getNavigationSort() ?? 0)
                ->isActiveWhen(fn (): bool => request()->routeIs(
                    static::getRouteBaseName() . '.index',
                ))
                ->url(static::getUrl('index')),
        ];

        $group = static::plugin()->getTaxonomyNavigationGroup();
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
                ->group($group)
                ->icon('heroicon-o-tag')
                ->sort($index + 1)
                ->badge((string) $taxonomy->terms_count)
                ->badgeTooltip(__('filament-taxonomies::taxonomies.navigation.terms_count', ['name' => $taxonomy->name]))
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
