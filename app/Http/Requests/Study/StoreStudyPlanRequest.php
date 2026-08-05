<?php

namespace App\Http\Requests\Study;

use App\Models\Enums\StudyPlanType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudyPlanRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(StudyPlanType::class)],
            'weekly_goal_minutes' => ['required', 'integer', 'min:60', 'max:10080'],
        ];
    }
}
