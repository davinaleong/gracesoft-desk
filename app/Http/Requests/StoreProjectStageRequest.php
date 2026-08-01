<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectStageRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100', 'unique:project_stages,name'],
            'status' => ['required', 'in:active,inactive'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'is_default' => ['required', 'boolean'],
        ];
    }
}
