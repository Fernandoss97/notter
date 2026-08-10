<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Páginas lidas, por tarefa e por sessão (§5 da spec).
     *
     * O acerto em questões já dizia se o estudo está funcionando; faltava
     * a outra metade da conta, que é a *velocidade* do avanço. Sem ela não
     * há como perceber que uma disciplina está consumindo o dobro do tempo
     * por página — o sintoma de leitura travada que o método manda evitar.
     *
     * Espelha exatamente o par de questões: a tarefa é a fonte da verdade
     * (quem leu o PDF no papel e veio marcar depois precisa de onde
     * registrar), e a sessão encerrada soma nela.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedInteger('pages_read')->default(0)->after('exercises_correct');
        });

        Schema::table('study_sessions', function (Blueprint $table) {
            $table->unsignedInteger('pages_read')->nullable()->after('exercises_correct');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('pages_read');
        });

        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropColumn('pages_read');
        });
    }
};
