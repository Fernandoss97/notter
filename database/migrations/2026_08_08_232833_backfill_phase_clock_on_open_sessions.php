<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sessões que já estavam abertas quando as fases chegaram nasceram com
     * `phase_accumulated_seconds` zerado, embora todo o tempo delas tenha
     * corrido na única fase que existia até então — o foco. Sem isto, o
     * cronômetro dessas sessões recomeça do zero ao ser retomado.
     *
     * Só o relógio da fase corrente é afetado: `accumulated_seconds` sempre
     * guardou o tempo certo, então nenhum minuto estudado estava em risco.
     */
    public function up(): void
    {
        DB::table('study_sessions')
            ->where('state', '!=', 'completed')
            ->where('phase', 'focus')
            ->where('phase_accumulated_seconds', 0)
            ->update(['phase_accumulated_seconds' => DB::raw('accumulated_seconds')]);
    }

    /**
     * Sem volta: o valor anterior era zero por ausência de dado, não por
     * significar zero, e reverter só recriaria o defeito.
     */
    public function down(): void {}
};
