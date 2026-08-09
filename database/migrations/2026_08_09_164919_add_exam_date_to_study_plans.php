<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A data da prova. É o que transforma a trilha num prazo: sem ela, o
     * plano só sabe quantas tarefas faltam; com ela, sabe quantas precisam
     * sair por semana.
     *
     * Nula por padrão — plano de estudo livre não tem prova, e concurso
     * com data ainda não divulgada também não.
     */
    public function up(): void
    {
        Schema::table('study_plans', function (Blueprint $table) {
            $table->date('exam_date')->nullable()->after('weekly_goal_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('study_plans', function (Blueprint $table) {
            $table->dropColumn('exam_date');
        });
    }
};
