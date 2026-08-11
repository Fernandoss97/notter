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
     * A faixa de 60 a 90 minutos que o método recomenda é aviso na tela,
     * não regra: quem estuda Contabilidade sabe por que estourou, e quem
     * só tem meia hora antes do trabalho sabe por que ficou abaixo. O que
     * a validação guarda é o intervalo em que um bloco ainda é um bloco.
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
