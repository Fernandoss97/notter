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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer'],
            'topic_id' => ['nullable', 'integer'],
            'activity_type' => ['required', Rule::enum(ActivityType::class)],
            'exercises_total' => ['nullable', 'integer', 'min:0'],
            'exercises_correct' => ['nullable', 'integer', 'min:0', 'lte:exercises_total'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
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
