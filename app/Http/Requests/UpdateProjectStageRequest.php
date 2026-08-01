<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $stage = $this->route('projectStage');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('project_stages', 'name')->ignore($stage?->id)],
            'status' => ['required', 'in:active,inactive'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'is_default' => ['required', 'boolean'],
        ];
    }
}
