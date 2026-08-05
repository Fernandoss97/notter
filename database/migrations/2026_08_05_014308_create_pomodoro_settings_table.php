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
        Schema::create('pomodoro_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('focus_minutes')->default(50);
            $table->unsignedSmallInteger('short_break_minutes')->default(10);
            $table->unsignedSmallInteger('long_break_minutes')->default(25);
            $table->unsignedTinyInteger('cycles_before_long_break')->default(4);
            $table->boolean('auto_start_break')->default(true);
            $table->boolean('auto_start_focus')->default(false);
            $table->boolean('log_session_on_finish')->default(true);
            $table->boolean('silent_mode_during_focus')->default(true);
            $table->boolean('exclude_breaks_from_study_time')->default(false);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pomodoro_settings');
    }
};
