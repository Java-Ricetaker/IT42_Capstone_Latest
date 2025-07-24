<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('clinic_calendar', function (Blueprint $table) {
        $table->id();
        $table->date('date')->unique(); // one record per day
        $table->boolean('is_open')->default(true);
        $table->time('open_time')->nullable(); // opening time, null if closed
        $table->time('close_time')->nullable(); // closing time, null if closed
        $table->unsignedTinyInteger('dentist_count')->default(1);
        $table->string('note')->nullable(); // optional reason (e.g., holiday)
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinic_calendar');
    }
};
