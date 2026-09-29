<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * The account details an OAuth callback hands back, in provider-neutral form.
 */
readonly class ProviderAccount
{
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        public string $id,
        public string $login,
        public string $accessToken,
        public ?string $refreshToken = null,
        public ?CarbonInterface $expiresAt = null,
        public array $scopes = [],
    ) {}
}
