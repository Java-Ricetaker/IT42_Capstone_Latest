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
        Schema::create('inventory_batches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $t->string('lot_no')->nullable();
            $t->date('expiry_date')->nullable();
            $t->string('supplier')->nullable();
            $t->decimal('unit_cost', 12, 2)->nullable();
            $t->decimal('qty_on_hand', 12, 2)->default(0);     // supports partials (ml/g)
            $t->date('received_at')->nullable();
            $t->timestamps();

            $t->index(['item_id', 'expiry_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_batches');
    }
};
