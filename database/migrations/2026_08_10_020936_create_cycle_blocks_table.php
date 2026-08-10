<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A execução de uma cadeira do rodízio numa volta — o livro-razão do
     * bloco.
     *
     * O bloco não é um pomodoro longo: ele acumula segundos de *foco* ao
     * longo de vários pomodoros e de vários dias, e é justamente isso que
     * faz o "retomar de onde parou" cair de graça. Quem interrompe não
     * fecha nada; volta e encontra o mesmo bloco com o tempo restante.
     *
     * `target_seconds` é snapshot de `block_minutes` na abertura da volta,
     * pelo mesmo motivo de `study_sessions.planned_seconds`: mudar a
     * duração da cadeira não deve reescrever um bloco em andamento.
     */
    public function up(): void
    {
        Schema::create('cycle_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_slot_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('lap');
            $table->unsignedInteger('target_seconds');
            $table->unsignedInteger('accumulated_seconds')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['cycle_slot_id', 'lap']);
            $table->index(['cycle_slot_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_blocks');
    }
};
