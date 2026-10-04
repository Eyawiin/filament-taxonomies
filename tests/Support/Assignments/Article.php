<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support\Assignments;

use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasTaxonomies;

    protected $table = 'assignment_articles';

    protected $guarded = [];

    public $timestamps = false;
}
