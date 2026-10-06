<?php

namespace Eyawiin\FilamentTaxonomies\Models;

use Eyawiin\FilamentTaxonomies\Support\TaxonomyModels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $taxonomy_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property int $position
 */
class TaxonomyTerm extends Model
{
    // Explicit, so configured subclasses keep the package table.
    protected $table = 'taxonomy_terms';

    protected $fillable = [
        'taxonomy_id',
        'parent_id',
        'name',
        'slug',
        'position',
    ];

    /**
     * @return BelongsTo<Taxonomy, $this>
     */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(TaxonomyModels::taxonomy(), 'taxonomy_id');
    }

    /**
     * @return BelongsTo<TaxonomyTerm, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaxonomyModels::term(), 'parent_id');
    }

    /**
     * @return HasMany<TaxonomyTerm, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(TaxonomyModels::term(), 'parent_id');
    }
}
