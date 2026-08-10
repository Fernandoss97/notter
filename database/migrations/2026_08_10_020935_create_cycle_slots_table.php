<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A cadeira de uma disciplina no rodízio do plano.
     *
     * O ciclo de estudos é a ordem em que as disciplinas se alternam —
     * pergunta diferente da que a trilha responde. A trilha diz qual
     * *tarefa* vem agora dentro de uma disciplina; o rodízio diz qual
     * *disciplina*. Eles compõem: o bloco escolhe a disciplina, a trilha
     * escolhe a tarefa dentro dela.
     *
     * Uma disciplina ocupa no máximo uma cadeira por plano: peso maior se
     * expressa em `block_minutes` e em posição mais cedo, não em aparecer
     * duas vezes na mesma volta.
     */
    public function up(): void
    {
        Schema::create('cycle_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('block_minutes')->default(60);
            $table->string('state')->default('active');
            $table->timestamps();

            $table->unique(['study_plan_id', 'subject_id']);
            $table->index(['study_plan_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_slots');
    }
};
