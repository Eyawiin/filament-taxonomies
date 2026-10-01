<?php

namespace Eyawiin\FilamentTaxonomies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Taxonomy extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * @return HasMany<TaxonomyTerm, $this>
     */
    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class);
    }
}
