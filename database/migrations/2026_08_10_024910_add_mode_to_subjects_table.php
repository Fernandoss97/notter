<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Em que pé está a disciplina: primeiro contato com a teoria, já em
     * revisão, ou encerrada.
     *
     * É a informação que falta para o ciclo crescer sozinho (§4 da spec).
     * Quem termina a teoria de uma matéria não a abandona — ela passa a
     * ocupar menos tempo no rodízio, e é essa folga que abre espaço para
     * uma disciplina nova entrar. Sem registrar o modo, "quantas matérias
     * estou vendo pela primeira vez" é uma conta que só existe na cabeça
     * de quem estuda.
     *
     * O default é `reading`: uma disciplina recém-cadastrada é, por
     * definição, teoria que ainda não foi vista.
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('mode')->default('reading')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
