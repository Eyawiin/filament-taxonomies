<?php

namespace Workbench\App\Models;

use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Workbench\Database\Factories\DeckFactory;

class Deck extends Model
{
    /** @use HasFactory<DeckFactory> */
    use HasFactory;

    use HasTaxonomies;

    protected $fillable = ['name', 'description', 'demo_key'];

    protected static function newFactory(): DeckFactory
    {
        return DeckFactory::new();
    }
}
