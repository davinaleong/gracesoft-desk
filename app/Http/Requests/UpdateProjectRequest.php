<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Services\BudgetMonitor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
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
     * The fixed fee can't drop below what the milestones already add up to.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $project = $this->route('project');

                if (! $project instanceof Project || $this->input('billing_model') !== Project::BILLING_FIXED_FEE || ! is_numeric($this->input('fixed_fee_total'))) {
                    return;
                }

                $milestoneTotal = (float) $project->milestones()->sum('amount');

                if ((float) $this->input('fixed_fee_total') + 0.001 < $milestoneTotal) {
                    $validator->errors()->add('fixed_fee_total', __('The fixed fee can\'t be less than the milestones already planned (:total).', ['total' => number_format($milestoneTotal, 2)]));
                }
            },
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-]+$/',
                Rule::unique('projects', 'code')->ignore($project?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_billable' => ['required', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'ai_opt_out' => ['sometimes', 'boolean'],
            'billing_model' => ['sometimes', Rule::in(Project::BILLING_MODELS)],
            'fixed_fee_total' => ['nullable', 'required_if:billing_model,fixed_fee', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'retainer_monthly_amount' => ['nullable', 'required_if:billing_model,retainer', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'retainer_included_hours' => ['nullable', 'required_if:billing_model,retainer', 'numeric', 'min:0', 'max:744', 'decimal:0,2'],
            'retainer_overage_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'retainer_rollover' => ['sometimes', 'boolean'],
            'budget_type' => ['sometimes', Rule::in(BudgetMonitor::TYPES)],
            'budget_value' => ['nullable', 'required_if:budget_type,hours,amount', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'budget_thresholds' => ['nullable', 'array', 'max:10'],
            'budget_thresholds.*' => ['integer', 'min:1', 'max:1000'],
            'client_uuid' => ['nullable', 'uuid', Rule::exists('clients', 'uuid')->whereNull('deleted_at')],
        ];
    }
}
