<?php

namespace App\Models;

use App\Traits\UsesCuid;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use UsesCuid;

    protected $table = 'audit_logs';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'userId',
        'action',
        'details',
        'timestamp',
        'ipAddress',
        'userAgent',
        'deviceName',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
