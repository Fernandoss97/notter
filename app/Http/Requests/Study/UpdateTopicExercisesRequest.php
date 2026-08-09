<?php

namespace App\Http\Requests\Study;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTopicExercisesRequest extends FormRequest
{
    /**
     * Os dois números vêm sempre juntos: este é o registro oficial da
     * tarefa, não um incremento, então o formulário manda o par inteiro e
     * substitui o que estava lá.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exercises_total' => ['required', 'integer', 'min:0', 'max:99999'],
            'exercises_correct' => ['required', 'integer', 'min:0', 'lte:exercises_total'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exercises_correct.lte' => __('Os acertos não podem passar do total de questões.'),
        ];
    }
}
