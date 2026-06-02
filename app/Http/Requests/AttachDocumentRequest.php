<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttachDocumentRequest extends FormRequest
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
            'documentable_type' => ['required', 'string', 'in:transaction,project,time-entry'],
            'documentable_uuid' => ['required', 'string', 'uuid'],
            'redirect_back' => ['nullable', 'string', 'in:transaction,project,time-entry'],
        ];
    }
}
