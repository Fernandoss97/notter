<?php

namespace App\Http\Requests\Study;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StartFocusSessionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['nullable', 'integer'],
            'topic_id' => ['nullable', 'integer'],
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
