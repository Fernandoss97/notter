<?php

namespace App\Http\Requests\Study;

use App\Models\Enums\ActivityType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteFocusSessionRequest extends FormRequest
{
    /**
     * `subject_id` é opcional: com "registrar sessão ao encerrar"
     * desligado, o usuário encerra sem preencher nada e a sessão fica com
     * a disciplina escolhida no início (ou nenhuma, se foi sessão livre).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['nullable', 'integer'],
            'topic_id' => ['nullable', 'integer'],
            'activity_type' => ['nullable', Rule::enum(ActivityType::class)],
            'exercises_total' => ['nullable', 'integer', 'min:0'],
            'exercises_correct' => ['nullable', 'integer', 'min:0', 'lte:exercises_total'],
            'pages_read' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('subject_id')) {
                    if ($this->filled('topic_id')) {
                        $validator->errors()->add('topic_id', __('Selecione uma disciplina.'));
                    }

                    return;
                }

                $plan = $this->user()->activeStudyPlan()->with('subjects.topics')->first();
                $subject = $plan?->subjects->firstWhere('id', (int) $this->input('subject_id'));

                if (! $subject) {
                    $validator->errors()->add('subject_id', __('Disciplina inválida.'));

                    return;
                }

                if ($this->filled('topic_id') && ! $subject->topics->contains('id', (int) $this->input('topic_id'))) {
                    $validator->errors()->add('topic_id', __('Tópico inválido.'));
                }
            },
        ];
    }
}
