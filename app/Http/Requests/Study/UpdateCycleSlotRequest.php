<?php

namespace App\Http\Requests\Study;

use App\Models\CycleSlot;
use App\Models\Enums\CycleSlotState;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCycleSlotRequest extends FormRequest
{
    /**
     * O teto de 120 minutos é regra (§2 da spec); os 90 recomendados são
     * só aviso na tela, porque quem estuda Contabilidade sabe por que
     * estourou.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'block_minutes' => [
                'sometimes',
                'integer',
                'min:'.CycleSlot::MIN_BLOCK_MINUTES,
                'max:'.CycleSlot::MAX_BLOCK_MINUTES,
            ],
            'state' => ['sometimes', Rule::enum(CycleSlotState::class)],
        ];
    }
}
