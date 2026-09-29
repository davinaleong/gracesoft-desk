<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordInvoicePaymentRequest extends FormRequest
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
            'account_uuid' => ['required', 'uuid', 'exists:accounts,uuid'],
            'payment_date' => ['required', 'date'],
            'payment_method_uuid' => ['nullable', 'uuid', 'exists:payment_methods,uuid'],
            'transaction_category_uuid' => ['nullable', 'uuid', 'exists:transaction_categories,uuid'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
