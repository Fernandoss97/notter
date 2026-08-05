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
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('activity_type')->nullable();
            $table->string('state')->default('running');
            $table->unsignedInteger('planned_seconds');
            $table->unsignedInteger('accumulated_seconds')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('exercises_total')->nullable();
            $table->unsignedInteger('exercises_correct')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('study_sessions');
    }
};
