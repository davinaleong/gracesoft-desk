<?php

namespace App\Http\Requests;

use App\Services\BudgetMonitor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
        if ($this->has('budget_thresholds') && is_string($this->input('budget_thresholds'))) {
            $thresholds = array_values(array_filter(array_map('trim', explode(',', (string) $this->input('budget_thresholds'))), fn (string $value): bool => $value !== ''));

            $this->merge(['budget_thresholds' => $thresholds === [] ? null : $thresholds]);
        }

        if ($this->input('budget_type') === 'none') {
            $this->merge(['budget_value' => null]);
        }

        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper((string) $this->input('code')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('projects', 'code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_billable' => ['required', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'ai_opt_out' => ['sometimes', 'boolean'],
            'budget_type' => ['sometimes', Rule::in(BudgetMonitor::TYPES)],
            'budget_value' => ['nullable', 'required_if:budget_type,hours,amount', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'budget_thresholds' => ['nullable', 'array', 'max:10'],
            'budget_thresholds.*' => ['integer', 'min:1', 'max:1000'],
            'client_uuid' => ['nullable', 'uuid', Rule::exists('clients', 'uuid')->whereNull('deleted_at')],
        ];
    }
}
