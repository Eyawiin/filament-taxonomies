<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support\Assignments;

use Eyawiin\FilamentTaxonomies\Concerns\HasTaxonomies;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UuidOwner extends Model
{
    use HasTaxonomies;
    use HasUuids;

    protected $table = 'assignment_uuid_owners';

    protected $guarded = [];

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;
}
