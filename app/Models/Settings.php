<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    use UsesCuid;

    protected $table = 'settings';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'tenantId', 'data'];

    protected $casts = [
        'data' => 'array',
    ];
}
