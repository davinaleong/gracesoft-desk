<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One outbound AI call. Holds a hash and length of the payload, never the payload itself.
 */
class AiRequest extends Model
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_ERROR = 'error';

    protected $fillable = [
        'requested_at',
        'provider',
        'model',
        'purpose',
        'project_id',
        'payload_hash',
        'payload_length',
        'outcome',
        'error',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'payload_length' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
