<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StudyDataDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Mesma exigência da exclusão de conta: a senha no corpo, e não a
     * confirmação de sessão. Apagar meses de trilha é irreversível, e o
     * gesto tem que custar o mesmo que apagar a conta.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }
}
