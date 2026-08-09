<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A trilha (ciclo) do plano de estudos, e a aposentadoria da prioridade
     * de disciplina.
     *
     * `topics.position` muda de significado: era a ordem dentro da
     * disciplina, passa a ser a sequência dentro do *plano* — a fila que
     * atravessa disciplinas, como a coluna TAREFA de uma planilha de
     * trilha. `cycle` agrupa essa fila em ondas. A chave de ordenação
     * passa a ser (cycle, position) em todo lugar.
     *
     * A prioridade sai porque nunca foi editável (não existe rota de
     * update de disciplina) e seu único efeito era desempatar a sugestão
     * de estudo — papel que a ordem da trilha assume, medida em vez de
     * declarada.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->unsignedInteger('cycle')->default(0)->after('subject_id');
            $table->index(['cycle', 'position']);
        });

        $this->resequenceWithinPlans();

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }

    /**
     * Devolve a prioridade (com o default de sempre — o valor original de
     * cada disciplina não sobrevive ao drop) e renumera `position` dentro
     * de cada disciplina, respeitando a ordem (cycle, position) que a
     * trilha estabeleceu.
     */
    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('priority')->default('medium')->after('name');
        });

        $this->resequenceWithinSubjects();

        Schema::table('topics', function (Blueprint $table) {
            $table->dropIndex(['cycle', 'position']);
            $table->dropColumn('cycle');
        });
    }

    /**
     * Renumera os tópicos existentes como uma fila única por plano. A ordem
     * anterior é preservada disciplina a disciplina: quem estava no plano
     * antes da trilha existir não tinha intercalação para descrever.
     */
    private function resequenceWithinPlans(): void
    {
        $rows = DB::table('topics')
            ->join('subjects', 'topics.subject_id', '=', 'subjects.id')
            ->orderBy('subjects.study_plan_id')
            ->orderBy('subjects.position')
            ->orderBy('subjects.id')
            ->orderBy('topics.position')
            ->orderBy('topics.id')
            ->get(['topics.id', 'subjects.study_plan_id']);

        $positions = [];

        foreach ($rows as $row) {
            $position = $positions[$row->study_plan_id] ?? 0;
            $positions[$row->study_plan_id] = $position + 1;

            DB::table('topics')->where('id', $row->id)->update(['position' => $position]);
        }
    }

    private function resequenceWithinSubjects(): void
    {
        $rows = DB::table('topics')
            ->orderBy('subject_id')
            ->orderBy('cycle')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'subject_id']);

        $positions = [];

        foreach ($rows as $row) {
            $position = $positions[$row->subject_id] ?? 0;
            $positions[$row->subject_id] = $position + 1;

            DB::table('topics')->where('id', $row->id)->update(['position' => $position]);
        }
    }
};
