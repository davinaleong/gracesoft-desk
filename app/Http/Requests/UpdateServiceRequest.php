<?php

namespace App\Http\Requests;

use App\Support\RenewalSchedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceRequest extends FormRequest
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
            'category_id' => ['required', Rule::exists('categories', 'id')->where('type', 'service')],
            'status' => ['required', 'in:active,paused,cancelled'],
            'notes' => ['nullable', 'string'],
            'billing_cycle' => ['nullable', Rule::in(RenewalSchedule::CYCLES)],
            'expected_amount' => ['nullable', 'required_with:billing_cycle', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'next_renewal_date' => ['nullable', 'required_with:billing_cycle', 'date'],
            'account_uuid' => ['nullable', 'required_if_accepted:auto_create_expense', 'uuid', 'exists:accounts,uuid'],
            'payment_method_uuid' => ['nullable', 'uuid', 'exists:payment_methods,uuid'],
            'transaction_category_uuid' => ['nullable', 'uuid', 'exists:transaction_categories,uuid'],
            'auto_create_expense' => ['sometimes', 'boolean'],
            'reminder_days_before' => ['sometimes', 'integer', 'min:0', 'max:90'],
        ];
    }
}
