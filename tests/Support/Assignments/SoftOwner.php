<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support\Assignments;

use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftOwner extends Model
{
    use HasTaxonomies;
    use SoftDeletes;

    protected $table = 'assignment_soft_owners';

    protected $guarded = [];

    public $timestamps = false;
}
