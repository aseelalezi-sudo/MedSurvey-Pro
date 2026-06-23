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
        'tenantId',
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

    public function scopeVisibleTo($query, ?User $user)
    {
        if ($user?->role === 'super_admin') {
            return $query;
        }

        return $query->where(function ($q) use ($user): void {
            if ($user?->tenantId) {
                $q->where($q->qualifyColumn('tenantId'), $user->tenantId);

                return;
            }

            $q->whereNull($q->qualifyColumn('tenantId'));
        });
    }
}
