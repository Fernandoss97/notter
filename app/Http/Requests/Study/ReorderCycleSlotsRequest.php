<?php

namespace App\Http\Requests\Study;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReorderCycleSlotsRequest extends FormRequest
{
    /**
     * A posse das cadeiras é conferida na Action, que ignora em silêncio
     * qualquer id de outro plano — a tela manda a lista inteira e não tem
     * como saber de uma cadeira criada em outra aba.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slots' => ['required', 'array', 'max:50'],
            'slots.*' => ['integer'],
        ];
    }
}
