<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
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
            'vendor_uuid' => ['required', 'uuid', 'exists:vendors,uuid'],
            'name' => ['required', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'in:storage,communication,design,dev_tools,security,productivity,other'],
            'status' => ['required', 'in:active,paused,cancelled'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
