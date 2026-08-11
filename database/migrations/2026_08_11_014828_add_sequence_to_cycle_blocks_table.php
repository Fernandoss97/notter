<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qual das passagens da disciplina este bloco é, dentro da volta.
     *
     * O índice único passa a incluí-la: era ele que gravava no banco a
     * regra "uma passagem por volta". Continua sendo a trava que protege o
     * `insertOrIgnore` de ResolveCurrentCycleBlock quando duas abas abrem a
     * mesma volta ao mesmo tempo — só que agora por passagem, e não por
     * cadeira.
     */
    public function up(): void
    {
        Schema::table('cycle_blocks', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->default(1)->after('lap');

            $table->dropUnique(['cycle_slot_id', 'lap']);
            $table->unique(['cycle_slot_id', 'lap', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::table('cycle_blocks', function (Blueprint $table) {
            $table->dropUnique(['cycle_slot_id', 'lap', 'sequence']);
            $table->unique(['cycle_slot_id', 'lap']);

            $table->dropColumn('sequence');
        });
    }
};
