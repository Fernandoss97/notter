<?php

namespace App\Http\Requests\Study;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportStudyTrackRequest extends FormRequest
{
    /**
     * O formato do conteúdo é problema do parser, que sabe apontar a linha
     * do erro. Aqui só se garante que veio texto, e de tamanho sensato.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:200000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => __('Cole as tarefas da planilha para importar.'),
        ];
    }
}
