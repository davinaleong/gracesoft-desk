<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'manual_lines' => ManualInvoiceLines::withoutBlankRows($this->input('manual_lines', [])),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'remove_line_uuids' => ['array'],
            'remove_line_uuids.*' => ['uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...ManualInvoiceLines::rules(),
        ];
    }
}
