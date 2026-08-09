<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Questões resolvidas e acertos por *tarefa*, não por sessão.
     *
     * `study_sessions` já guarda os dois números, mas só de quem estudou
     * com o cronômetro ligado. Quem resolveu o PDF no papel e veio marcar
     * a tarefa depois — o modelo de qualquer planilha de trilha — não teria
     * onde registrar. A tarefa é a fonte da verdade; a sessão encerrada com
     * questões soma nela.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedInteger('exercises_total')->default(0)->after('completed_at');
            $table->unsignedInteger('exercises_correct')->default(0)->after('exercises_total');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn(['exercises_total', 'exercises_correct']);
        });
    }
};
