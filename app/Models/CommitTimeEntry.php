<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\CommitTimeEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitTimeEntry extends Model
{
    /** @use HasFactory<CommitTimeEntryFactory> */
    use HasFactory;

    use HasPublicUuid;

    protected static function booted(): void
    {
        static::creating(function (self $commit): void {
            self::fillUuid($commit);
        });
    }

    protected $fillable = [
        'project_id',
        'sha',
        'branch',
        'author_name',
        'author_email',
        'committed_at',
        'message',
        'additions',
        'deletions',
        'changed_files',
        'status',
        'squashed_into',
        'converted_time_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'committed_at' => 'datetime',
            'additions' => 'integer',
            'deletions' => 'integer',
            'changed_files' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function squashedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'squashed_into');
    }

    public function convertedTimeEntry(): BelongsTo
    {
        return $this->belongsTo(TimeEntry::class, 'converted_time_entry_id');
    }
}
