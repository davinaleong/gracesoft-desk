<?php

namespace App\Services\SourceProviders;

use App\Contracts\SourceProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SourceProviderRegistry
{
    public const KEYS = ['github', 'gitlab', 'bitbucket'];

    /**
     * @var array<string, class-string<SourceProvider>>
     */
    private const PROVIDERS = [
        'github' => GitHubSourceProvider::class,
        'gitlab' => GitLabSourceProvider::class,
        'bitbucket' => BitbucketSourceProvider::class,
    ];

    public function get(?string $key): SourceProvider
    {
        $class = self::PROVIDERS[$key ?? 'github'] ?? null;

        if ($class === null) {
            throw new NotFoundHttpException("Unknown source provider [{$key}].");
        }

        return app($class);
    }

    /**
     * @return array<string, SourceProvider>
     */
    public function all(): array
    {
        return array_map(fn (string $class): SourceProvider => app($class), self::PROVIDERS);
    }
}
