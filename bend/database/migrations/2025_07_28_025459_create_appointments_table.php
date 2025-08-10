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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('time_slot'); // e.g., "08:00-09:00"
            $table->string('reference_code')->unique()->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled', 'completed'])->default('pending');
            $table->enum('payment_method', ['cash', 'maya', 'hmo'])->default('cash');
            $table->enum('payment_status', ['unpaid', 'awaiting_payment', 'paid'])->default('unpaid');
            $table->text('notes')->nullable(); // for staff rejection notes etc.
            $table->timestamp('canceled_at')->nullable(); // for tracking when patient cancels
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
