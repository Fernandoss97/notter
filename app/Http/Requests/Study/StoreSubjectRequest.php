<?php

namespace App\Http\Requests\Study;

use App\Models\Enums\SubjectPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::enum(SubjectPriority::class)],
            'goal_minutes' => ['nullable', 'integer', 'min:60', 'max:10080'],
        ];
    }
}
