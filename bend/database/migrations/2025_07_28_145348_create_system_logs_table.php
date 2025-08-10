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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // actor (staff/admin)
            $table->string('category'); // e.g., appointment, visit, device
            $table->string('action');   // e.g., approved_appointment
            $table->text('message');    // readable description (e.g. "Staff Jane approved appointment #5")
            $table->json('context')->nullable(); // optional metadata for tracing (e.g. {"appointment_id": 5})
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
