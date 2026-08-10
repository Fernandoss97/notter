<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O vínculo entre a sessão de foco e o bloco do rodízio que ela
     * alimenta.
     *
     * O vínculo é explícito e não derivado de disciplina + data: derivar
     * quebraria no instante em que o rodízio fosse reordenado, ou em que
     * duas voltas do mesmo dia tocassem a mesma disciplina.
     *
     * Nulo é um estado legítimo, não uma pendência: quem estuda fora do
     * rodízio — ou não montou nenhum — continua registrando sessões.
     */
    public function up(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->foreignId('cycle_block_id')
                ->nullable()
                ->after('topic_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropForeign(['cycle_block_id']);
            $table->dropColumn('cycle_block_id');
        });
    }
};
