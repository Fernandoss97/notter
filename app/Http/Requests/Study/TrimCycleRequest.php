<?php

namespace App\Http\Requests\Study;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TrimCycleRequest extends FormRequest
{
    /**
     * Enxugar para menos de duas disciplinas não é enxugar o ciclo — é
     * desmontá-lo. O piso protege o único traço que define um rodízio:
     * haver de quem alternar.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keep' => ['required', 'integer', 'min:2', 'max:50'],
        ];
    }
}
