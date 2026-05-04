<?php

namespace App\Http\Requests;

use App\Support\TransactionIntegrity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTransactionRequest extends FormRequest
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
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'transaction_category_id' => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'type' => ['required', 'in:income,expense,transfer'],
            'direction' => ['required', 'in:in,out'],
            'status' => ['required', 'in:pending,completed,void'],
            'transaction_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'gst_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = TransactionIntegrity::validate($this->all());

            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        });
    }
}
