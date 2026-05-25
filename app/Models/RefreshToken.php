<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    use UsesCuid;

    protected $table = 'refresh_tokens';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'token', 'userId', 'expiresAt', 'createdAt'];

    protected $casts = [
        'expiresAt' => 'datetime',
        'createdAt' => 'datetime',
    ];
}

