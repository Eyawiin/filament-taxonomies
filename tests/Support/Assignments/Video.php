<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support\Assignments;

use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasTaxonomies;

    protected $table = 'assignment_videos';

    protected $guarded = [];

    public $timestamps = false;
}
