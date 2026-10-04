<?php

namespace Eyawiin\FilamentTaxonomies\Tests\Support\Assignments;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Fixture
{
    public static function createSchema(): void
    {
        foreach (['articles', 'videos', 'uuid_owners', 'ulid_owners', 'soft_owners'] as $name) {
            Schema::create('assignment_' . $name, function (Blueprint $table) use ($name): void {
                str_contains($name, 'uuid') || str_contains($name, 'ulid') ? $table->string('id', 36)->primary() : $table->id();
                $table->string('name')->default('Owner');
                if ($name === 'soft_owners') {
                    $table->softDeletes();
                }
            });
        }
    }
}
