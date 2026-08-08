<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O ciclo pomodoro (B2) precisa distinguir *o que* está rodando (foco
     * ou pausa) de *como* está rodando (running/paused), por isso `phase`
     * é ortogonal a `state`: pausar durante um intervalo é legítimo.
     *
     * `accumulated_seconds` mantém a semântica antiga — só tempo de foco,
     * que é o que alimenta dashboard e histórico. O tempo de intervalo vai
     * para `break_seconds`, e `phase_accumulated_seconds` é o relógio da
     * fase corrente, zerado a cada transição.
     */
    public function up(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->string('phase')->default('focus')->after('state');
            $table->unsignedInteger('phase_accumulated_seconds')->default(0)->after('accumulated_seconds');
            $table->unsignedInteger('break_seconds')->default(0)->after('phase_accumulated_seconds');
            $table->unsignedInteger('completed_cycles')->default(0)->after('break_seconds');
            $table->text('notes')->nullable()->after('exercises_correct');
        });
    }

    public function down(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'phase',
                'phase_accumulated_seconds',
                'break_seconds',
                'completed_cycles',
                'notes',
            ]);
        });
    }
};
