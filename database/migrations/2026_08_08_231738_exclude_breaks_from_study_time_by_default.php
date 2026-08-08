<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tempo de intervalo deixa de contar como tempo estudado por padrão.
     * Contar o descanso inflava a métrica que o app existe para medir —
     * quem quiser o comportamento antigo desliga a opção.
     *
     * Só o padrão muda: quem já salvou uma preferência mantém a sua.
     */
    public function up(): void
    {
        Schema::table('pomodoro_settings', function (Blueprint $table) {
            $table->boolean('exclude_breaks_from_study_time')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pomodoro_settings', function (Blueprint $table) {
            $table->boolean('exclude_breaks_from_study_time')->default(false)->change();
        });
    }
};
