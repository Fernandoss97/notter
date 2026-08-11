<?php

use App\Models\PomodoroSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A meta do dia passa a ser escolha de quem estuda.
     *
     * Antes ela era a meta semanal do plano dividida por sete, o que supunha
     * estudo todos os dias: quem estuda cinco fechava a semana em 70% depois
     * de bater a meta todo dia. Dividir por um número que o usuário não
     * escolheu é o app decidindo por ele qual é a rotina dele.
     *
     * A meta semanal continua no plano, e as duas passam a ser
     * independentes — a tela de configurações mostra o equivalente semanal
     * ao lado do campo para que a diferença não fique escondida.
     */
    public function up(): void
    {
        Schema::table('pomodoro_settings', function (Blueprint $table) {
            $table->unsignedInteger('daily_goal_minutes')
                ->default(PomodoroSetting::DEFAULT_DAILY_GOAL_MINUTES)
                ->after('cycles_before_long_break');
        });

        $this->carryOverTheDerivedGoal();
    }

    public function down(): void
    {
        Schema::table('pomodoro_settings', function (Blueprint $table) {
            $table->dropColumn('daily_goal_minutes');
        });
    }

    /**
     * Quem já usava o app não vê a meta mudar sozinha: a derivada de até
     * agora (semanal ÷ 7 do plano ativo) vira o valor inicial do campo.
     *
     * Tabela a tabela, sem Eloquent: migration que passa por relação de
     * modelo deixa de rodar igual assim que a relação muda, e esta roda em
     * todo `RefreshDatabase`. Uma consulta por linha é desperdício que não
     * se nota numa tabela de uma linha por usuário — e um `update` com
     * `join` não é portável entre SQLite e MySQL, que é o que interessa
     * aqui.
     *
     * Só alcança quem já tem linha de configuração: quem nunca abriu a tela
     * começa no padrão.
     */
    private function carryOverTheDerivedGoal(): void
    {
        DB::table('pomodoro_settings')->orderBy('id')->each(function (object $settings): void {
            $weeklyGoal = DB::table('study_plans')
                ->where('user_id', $settings->user_id)
                ->where('is_active', true)
                ->value('weekly_goal_minutes');

            if ($weeklyGoal === null) {
                return;
            }

            DB::table('pomodoro_settings')
                ->where('id', $settings->id)
                ->update(['daily_goal_minutes' => (int) round((int) $weeklyGoal / 7)]);
        });
    }
};
