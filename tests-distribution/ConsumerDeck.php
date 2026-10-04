<?php

namespace App\Models;

use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;

class ConsumerDeck extends Model
{
    use HasTaxonomies;

    protected $fillable = ['name'];

    public $timestamps = false;
}
