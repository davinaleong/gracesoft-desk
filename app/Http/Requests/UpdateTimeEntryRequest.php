<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTimeEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_uuid' => ['required', 'uuid', 'exists:projects,uuid'],
            'project_stage_uuid' => ['nullable', 'uuid', 'exists:project_stages,uuid'],
            'entry_date' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'is_billable' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
