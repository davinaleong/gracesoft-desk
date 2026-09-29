<?php

namespace App\Http\Requests;

use App\Services\InvoiceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'client_uuid' => ['required', 'uuid', Rule::exists('clients', 'uuid')->whereNull('deleted_at')],
            'grouping' => ['required', Rule::in([InvoiceService::GROUP_PER_ENTRY, InvoiceService::GROUP_BY_STAGE])],
            'time_entry_uuids' => ['array'],
            'time_entry_uuids.*' => ['uuid'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...ManualInvoiceLines::rules(),
        ];
    }
}
