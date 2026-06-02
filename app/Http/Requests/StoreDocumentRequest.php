<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,gif'],
            'name' => ['nullable', 'string', 'max:255'],
            'documentable_type' => ['nullable', 'string', 'in:transaction,project,time-entry'],
            'documentable_uuid' => ['nullable', 'string', 'uuid'],
        ];
    }
}
