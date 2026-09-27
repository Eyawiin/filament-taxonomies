<?php

namespace Eyawiin\FilamentTaxonomies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $taxonomy_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 */
class TaxonomyTerm extends Model
{
    protected $fillable = [
        'taxonomy_id',
        'parent_id',
        'name',
        'slug',
    ];

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
