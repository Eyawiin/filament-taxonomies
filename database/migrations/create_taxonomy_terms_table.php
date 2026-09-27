<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_terms', function (Blueprint $table): void {
            $table->id();

            $table
                ->foreignId('taxonomy_id')
                ->constrained('taxonomies')
                ->cascadeOnDelete();

            $table
                ->foreignId('parent_id')
                ->nullable()
                ->constrained('taxonomy_terms')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['taxonomy_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_terms');
    }
};
