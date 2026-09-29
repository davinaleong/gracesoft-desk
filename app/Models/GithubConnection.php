<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A connected account on a source provider (GitHub, GitLab or Bitbucket; see `provider`).
 * The table and class keep their original GitHub names for compatibility; github_id / github_login
 * hold the provider's account id and username. Tokens are encrypted at rest.
 */
class GithubConnection extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'refresh_token',
        'token_expires_at',
        'github_id',
        'github_login',
        'access_token',
        'token_scope',
        'connected_at',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
