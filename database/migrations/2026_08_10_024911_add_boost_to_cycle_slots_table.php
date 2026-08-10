<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Até quando a disciplina nova recebe tratamento de imersão (§4).
     *
     * Uma matéria que acaba de entrar no ciclo precisa de mais tempo nas
     * primeiras semanas, e depois não precisa mais. Uma data de validade,
     * e não um sinalizador de prioridade: a prioridade de disciplina já
     * foi tentada e removida deste projeto (ver a migration que a
     * aposentou) porque nunca era revista — ficava declarada em `high`
     * para sempre. O reforço expira sozinho.
     *
     * O quanto ele reforça vive em `block_minutes`, que continua sendo a
     * única medida de peso do rodízio. Isto aqui só diz até quando.
     */
    public function up(): void
    {
        Schema::table('cycle_slots', function (Blueprint $table) {
            $table->date('boost_until')->nullable()->after('block_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('cycle_slots', function (Blueprint $table) {
            $table->dropColumn('boost_until');
        });
    }
};
