<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support\Assignments;

use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class UlidOwner extends Model
{
    use HasTaxonomies;
    use HasUlids;

    protected $table = 'assignment_ulid_owners';

    protected $guarded = [];

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;
}
