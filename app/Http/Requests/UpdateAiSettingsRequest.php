<?php

namespace App\Http\Requests;

use App\Services\Ai\AiSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiSettingsRequest extends FormRequest
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
            'ai_enabled' => ['required', 'boolean'],
            'ai_provider' => ['nullable', 'required_if_accepted:ai_enabled', Rule::in(AiSettings::PROVIDERS)],
            'ai_model' => ['nullable', 'string', 'max:100'],
            'ai_base_url' => ['nullable', 'required_if:ai_provider,openai_compatible', 'url:http,https', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
            'clear_api_key' => ['nullable', 'boolean'],
            'ai_send_file_paths' => ['required', 'boolean'],
            'ai_log_retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
