<?php

namespace App\Actions\Study;

use App\Models\StudyPlan;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportStudyTrack
{
    /**
     * Despeja na trilha as tarefas lidas de uma planilha.
     *
     * Disciplina que ainda não existe é criada; tarefa que já existe na
     * mesma disciplina e no mesmo ciclo é ignorada. Essa é a regra que
     * torna a importação repetível: a trilha ganha tarefas novas toda
     * semana, e recolar a planilha inteira precisa acrescentar só o que
     * chegou, não duplicar o que já estava lá.
     *
     * @param  array<int, array{cycle: int|null, subject: string, name: string}>  $rows
     * @return array{imported: int, skipped: int, subjectsCreated: int}
     */
    public function handle(StudyPlan $plan, array $rows): array
    {
        return DB::transaction(function () use ($plan, $rows): array {
            $subjects = $plan->subjects()->get()->keyBy(
                fn (Subject $subject) => $this->key($subject->name)
            );

            $existing = $plan->topics()->get()
                ->mapWithKeys(fn (Topic $topic) => [
                    $this->signature($topic->subject_id, $topic->cycle, $topic->name) => true,
                ])
                ->all();

            $position = (int) ($plan->topics()->max('topics.position') ?? -1) + 1;
            $currentCycle = (int) ($plan->topics()->max('topics.cycle') ?? 0);
            $subjectPosition = (int) ($plan->subjects()->max('position') ?? -1) + 1;

            $imported = 0;
            $skipped = 0;
            $subjectsCreated = 0;

            foreach ($rows as $row) {
                $key = $this->key($row['subject']);
                $subject = $subjects->get($key);

                if (! $subject instanceof Subject) {
                    $subject = $plan->subjects()->create([
                        'name' => $row['subject'],
                        'position' => $subjectPosition++,
                    ]);
                    $subjects->put($key, $subject);
                    $subjectsCreated++;
                }

                $cycle = $row['cycle'] ?? $currentCycle;
                $signature = $this->signature($subject->id, $cycle, $row['name']);

                if (isset($existing[$signature])) {
                    $skipped++;

                    continue;
                }

                $subject->topics()->create([
                    'name' => $row['name'],
                    'cycle' => $cycle,
                    'position' => $position++,
                ]);

                $existing[$signature] = true;
                $imported++;
            }

            return [
                'imported' => $imported,
                'skipped' => $skipped,
                'subjectsCreated' => $subjectsCreated,
            ];
        });
    }

    /**
     * "Banco de Dados" e "banco de dados " são a mesma disciplina — quem
     * cola de uma planilha não controla a caixa nem os espaços.
     */
    private function key(string $name): string
    {
        return Str::lower(trim($name));
    }

    /**
     * A identidade de uma tarefa para efeito de reimportação: mesma
     * disciplina, mesmo ciclo, mesmo título. O mesmo título num ciclo
     * seguinte é outra tarefa — é a revisão da trilha.
     */
    private function signature(int $subjectId, int $cycle, string $name): string
    {
        return $subjectId.'|'.$cycle.'|'.$this->key($name);
    }
}
