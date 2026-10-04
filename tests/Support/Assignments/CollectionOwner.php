<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support\Assignments;

use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Models\Deck;

/** @property array|null $rows */
class CollectionOwner extends Model
{
    use HasTaxonomies;

    protected $table = 'assignment_collections';

    protected $guarded = [];

    protected $casts = ['rows' => 'array'];

    public $timestamps = false;

    /** @return HasMany<Deck, $this> */
    public function decks(): HasMany
    {
        return $this->hasMany(Deck::class, 'collection_id');
    }
}
