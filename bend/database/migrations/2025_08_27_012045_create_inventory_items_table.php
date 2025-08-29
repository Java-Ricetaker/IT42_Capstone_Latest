<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('sku')->nullable();
            $t->string('category')->nullable();          // Drug, Disinfectant, Consumable, PPE, etc.
            $t->string('unit')->default('piece');        // piece, ml, g, box
            $t->string('unit_hint')->nullable();         // e.g., "1 vial = 5 ml"
            $t->decimal('reorder_level', 12, 2)->default(0); // allow decimals for ml/g items
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->index(['name']);
            $t->index(['sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
