<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A que família a disciplina pertence.
     *
     * Existe por uma razão só: sem isso o sistema não tem como saber que
     * "Direito Constitucional" e "Direito Administrativo" são parentes, e
     * a intercalação do §3 — não pôr duas matérias parecidas em sequência —
     * seria impossível de sugerir ou de avisar.
     *
     * É uma etiqueta grosseira de propósito. Uma taxonomia fina exigiria
     * catálogo curado por concurso, que é conteúdo e não código; cinco
     * famílias bastam para responder "essas duas cansam do mesmo jeito?".
     */
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('area')->default('other')->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('area');
        });
    }
};
