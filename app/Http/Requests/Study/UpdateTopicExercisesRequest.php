<?php

namespace App\Http\Requests\Study;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTopicExercisesRequest extends FormRequest
{
    /**
     * Os dois números de questões vêm sempre juntos: este é o registro
     * oficial da tarefa, não um incremento, então o formulário manda o par
     * inteiro e substitui o que estava lá.
     *
     * As páginas são `sometimes` e não `required`: elas chegaram depois das
     * questões, e um formulário que só corrige o acerto não deve ser
     * obrigado a repetir um número que não está corrigindo. Ausente
     * significa "não mexe", não "zera".
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exercises_total' => ['required', 'integer', 'min:0', 'max:99999'],
            'exercises_correct' => ['required', 'integer', 'min:0', 'lte:exercises_total'],
            'pages_read' => ['sometimes', 'integer', 'min:0', 'max:99999'],
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
