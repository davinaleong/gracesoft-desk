<?php

namespace App\Services\Ai;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * AI configuration held in system settings. AI is off until switched on.
 */
class AiSettings
{
    public const PROVIDERS = ['openai', 'anthropic', 'openai_compatible'];

    public const DEFAULT_MODELS = [
        'openai' => 'gpt-4o-mini',
        'anthropic' => 'claude-opus-5',
        'openai_compatible' => 'llama3.1',
    ];

    public const DEFAULT_RETENTION_DAYS = 90;

    /** @var Collection<string, string|null>|null */
    private ?Collection $values = null;

    public function enabled(): bool
    {
        return in_array(strtolower((string) $this->get('ai_enabled')), ['1', 'true', 'yes', 'on'], true);
    }

    public function provider(): ?string
    {
        $provider = (string) $this->get('ai_provider');

        return in_array($provider, self::PROVIDERS, true) ? $provider : null;
    }

    public function model(): string
    {
        $model = trim((string) $this->get('ai_model'));

        return $model !== '' ? $model : (self::DEFAULT_MODELS[$this->provider() ?? 'openai'] ?? 'gpt-4o-mini');
    }

    public function baseUrl(): ?string
    {
        $url = trim((string) $this->get('ai_base_url'));

        return $url === '' ? null : rtrim($url, '/');
    }

    /**
     * The decrypted API key, falling back to OPENAI_API_KEY for the OpenAI provider.
     */
    public function apiKey(): ?string
    {
        $encrypted = (string) $this->get('ai_api_key');

        if ($encrypted !== '') {
            try {
                return Crypt::decryptString($encrypted);
            } catch (Throwable) {
                return null;
            }
        }

        if ($this->provider() === 'openai') {
            $envKey = (string) config('services.openai.api_key', '');

            return $envKey === '' ? null : $envKey;
        }

        return null;
    }

    public function hasStoredApiKey(): bool
    {
        return (string) $this->get('ai_api_key') !== '';
    }

    public function sendFilePaths(): bool
    {
        return in_array(strtolower((string) $this->get('ai_send_file_paths')), ['1', 'true', 'yes', 'on'], true);
    }

    public function retentionDays(): int
    {
        $days = (int) $this->get('ai_log_retention_days');

        return $days > 0 ? $days : self::DEFAULT_RETENTION_DAYS;
    }

    /**
     * Ready means switched on and holding everything the chosen driver needs.
     */
    public function isReady(): bool
    {
        return match ($this->provider()) {
            'openai', 'anthropic' => $this->enabled() && $this->apiKey() !== null,
            'openai_compatible' => $this->enabled() && $this->baseUrl() !== null,
            default => false,
        };
    }

    private function get(string $key): ?string
    {
        $this->values ??= SystemSetting::getMappedValues();

        $value = $this->values->get($key);

        return $value === null ? null : (string) $value;
    }
}
