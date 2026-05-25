<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use UsesCuid;

    protected $table = 'error_logs';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'level',
        'message',
        'stack',
        'source',
        'metadata',
        'status',
        'resolutionNotes',
        'count',
        'createdAt',
        'resolvedAt',
        'userId',
    ];

    protected $casts = [
        'metadata' => 'array',
        'createdAt' => 'datetime',
        'resolvedAt' => 'datetime',
    ];
}
