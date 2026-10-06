<?php

namespace Eyawiin\FilamentTaxonomies\Models;

use Eyawiin\FilamentTaxonomies\Support\TaxonomyIdentity;
use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Taxonomy extends Model
{
    // Explicit, so configured subclasses keep the package table.
    protected $table = 'taxonomies';

    protected $fillable = [
        'name',
        'slug',
    ];

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if (($field ?? $this->getRouteKeyName()) === $this->getKeyName() && TaxonomyIdentity::normalize($value) === null) {
            return $query->whereRaw('1 = 0');
        }

        return parent::resolveRouteBindingQuery($query, $value, $this->qualifyColumn($field ?? $this->getRouteKeyName()));
    }

    /**
     * @return HasMany<TaxonomyTerm, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyModels::term(), 'taxonomy_id');
    }
}
