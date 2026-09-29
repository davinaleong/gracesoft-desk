<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email', 'max:255'],
            'default_currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', 'string', 'max:10'],
            'default_hourly_rate' => ['required', 'numeric', 'min:0'],
            'archive_mode' => ['required', 'boolean'],
            'company_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'gst_registration_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'gst_rate' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'payment_terms_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:365'],
            'invoice_footer' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'weekly_hours_target' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:168'],
        ];
    }
}
