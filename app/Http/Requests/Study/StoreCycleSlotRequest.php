<?php

namespace App\Http\Requests\Study;

use App\Models\CycleSlot;
use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreCycleSlotRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer'],
            'block_minutes' => [
                'nullable',
                'integer',
                'min:'.CycleSlot::MIN_BLOCK_MINUTES,
                'max:'.CycleSlot::MAX_BLOCK_MINUTES,
            ],
        ];
    }

    /**
     * A disciplina precisa ser do plano ativo. Fica no `after()` e não numa
     * regra `exists`, porque a pergunta não é se ela existe — é se ela é
     * de quem está pedindo.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $plan = $this->user()->activeStudyPlan;
                $subject = Subject::find($this->integer('subject_id'));

                if (! $plan || ! $subject || $subject->study_plan_id !== $plan->id) {
                    $validator->errors()->add('subject_id', __('Disciplina não encontrada no plano ativo.'));
                }
            },
        ];
    }
}
