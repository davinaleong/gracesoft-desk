<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
            'type' => ['required', 'in:vendor,service'],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->where('type', $this->input('type')),
            ],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
