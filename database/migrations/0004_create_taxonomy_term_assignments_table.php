<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_term_assignments', function (Blueprint $table): void {
            $table->foreignId('taxonomy_term_id')->constrained('taxonomy_terms')->cascadeOnDelete();
            $type = $table->string('assignable_type', 191);
            $key = $table->string('assignable_id', 36);
            // Morph aliases and string owner keys have exact, case-sensitive identities.
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $type->charset('ascii')->collation('ascii_bin');
                $key->charset('ascii')->collation('ascii_bin');
            }
            $table->unique(['assignable_type', 'assignable_id', 'taxonomy_term_id'], 'taxonomy_assignments_owner_term_unique');
            $table->index('taxonomy_term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_term_assignments');
    }
};
