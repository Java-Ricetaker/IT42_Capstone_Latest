<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('batch_id')->constrained('inventory_batches')->cascadeOnDelete();
            $t->enum('type', ['IN','OUT','ADJUST']);
            $t->decimal('qty', 12, 2);                    // positive; meaning depends on type
            $t->string('reason')->nullable();

            // Optional polymorphic link (e.g., appointment)
            $t->nullableMorphs('ref');                    // ref_type, ref_id

            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamps();

            $t->index(['batch_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
