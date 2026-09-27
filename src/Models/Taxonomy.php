<?php

namespace Eyawiin\FilamentTaxonomies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Taxonomy extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(TaxonomyTerm::class);
    }
}
