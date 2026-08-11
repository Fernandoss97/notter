<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quantas vezes a disciplina passa por volta.
     *
     * Peso no rodízio sem alongar a sentada: duas passagens de 60 minutos
     * dão à matéria o mesmo tempo que uma de 120, mas com outras no meio —
     * que é a razão de existir do ciclo. O default 1 é o comportamento que
     * havia até aqui, então nenhum ciclo existente muda de forma.
     */
    public function up(): void
    {
        Schema::table('cycle_slots', function (Blueprint $table) {
            $table->unsignedInteger('blocks_per_lap')->default(1)->after('block_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('cycle_slots', function (Blueprint $table) {
            $table->dropColumn('blocks_per_lap');
        });
    }
};
