<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use UsesCuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name'];

    protected $casts = [
        'createdAt' => 'datetime',
    ];

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;
}
