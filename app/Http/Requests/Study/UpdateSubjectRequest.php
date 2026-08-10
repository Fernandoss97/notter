<?php

namespace App\Http\Requests\Study;

use App\Models\Enums\SubjectArea;
use App\Models\Enums\SubjectMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends FormRequest
{
    /**
     * Os dois campos são `sometimes` porque a tela edita um de cada vez:
     * trocar a família da disciplina não é dizer nada sobre em que pé ela
     * está, e vice-versa.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['sometimes', Rule::enum(SubjectMode::class)],
            'area' => ['sometimes', Rule::enum(SubjectArea::class)],
        ];
    }
}
