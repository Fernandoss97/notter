<?php

namespace App\Http\Requests\Study;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTopicRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $plan = $this->user()->activeStudyPlan()->with('subjects')->first();
                $subject = $plan?->subjects->firstWhere('id', (int) $this->input('subject_id'));

                if (! $subject) {
                    $validator->errors()->add('subject_id', __('Disciplina inválida.'));
                }
            },
        ];
    }
}
