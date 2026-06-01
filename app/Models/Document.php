<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    use HasPublicUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'disk',
        'path',
        'mime_type',
        'size',
        'documentable_type',
        'documentable_id',
        'user_id',
    ];

    protected $hidden = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            self::fillUuid($document);
        });

        static::deleted(function (self $document): void {
            Storage::disk($document->disk)->delete($document->path);
        });
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function temporaryUrl(int $minutes = 5): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $this->path,
            now()->addMinutes($minutes),
        );
    }

    public function formattedSize(): string
    {
        $bytes = $this->size;

        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }

        if ($bytes >= 1_024) {
            return round($bytes / 1_024, 1).' KB';
        }

        return $bytes.' B';
    }
}
