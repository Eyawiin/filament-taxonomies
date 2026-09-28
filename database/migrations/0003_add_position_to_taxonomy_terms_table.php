<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomy_terms', function (Blueprint $table): void {
            $table->unsignedInteger('position')
                ->default(0);

            $table->index(
                ['taxonomy_id', 'parent_id', 'position'],
                'taxonomy_terms_sibling_position_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('taxonomy_terms', function (Blueprint $table): void {
            $table->dropIndex('taxonomy_terms_sibling_position_index');
            $table->dropColumn('position');
        });
    }
};
