<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'token',
        'type',
        'user_id',
        'csv_s3_path',
        'valid_rows',
        'invalid_rows',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'valid_rows' => 'array',
        'invalid_rows' => 'array',
        'expires_at' => 'datetime',
    ];
}
