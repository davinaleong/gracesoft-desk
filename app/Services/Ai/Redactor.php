<?php

namespace App\Services\Ai;

/**
 * Masks email addresses and token-like strings before text leaves the server.
 */
class Redactor
{
    public const MASK = '[redacted]';

    /**
     * @var array<int, string>
     */
    private const PATTERNS = [
        // "Bearer <token>" (before the JWT rule, so the whole header value goes)
        '/\bBearer\s+[A-Za-z0-9._~+\/\-]+=*/i',
        // Email addresses
        '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
        // JSON Web Tokens
        '/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/',
        // Well-known key prefixes (OpenAI, Anthropic, GitHub, GitLab, Slack, Stripe, AWS)
        '/\b(?:sk-(?:ant-)?[A-Za-z0-9_\-]{8,}|gh[pousr]_[A-Za-z0-9]{16,}|github_pat_[A-Za-z0-9_]{20,}|glpat-[A-Za-z0-9_\-]{16,}|xox[abposr]-[A-Za-z0-9\-]{10,}|(?:sk|pk|rk)_(?:live|test)_[A-Za-z0-9]{10,}|AKIA[0-9A-Z]{16})\b/',
        // key=value / key: value secrets
        '/\b(?:password|passwd|secret|token|api[_-]?key)\s*[:=]\s*\S+/i',
    ];

    public function redact(string $text): string
    {
        $text = (string) preg_replace(self::PATTERNS, self::MASK, $text);

        // Long mixed letter/digit strings (random keys, hashes) are masked too.
        return (string) preg_replace_callback('/\b[A-Za-z0-9_\-]{32,}\b/', function (array $match): string {
            $value = $match[0];

            return preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value) ? self::MASK : $value;
        }, $text);
    }
}
