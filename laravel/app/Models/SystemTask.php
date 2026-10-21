<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemTask extends Model
{
    protected $fillable = ['type', 'status', 'requested_by', 'log', 'started_at', 'finished_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public const ACTIVE_STATUSES = ['pending', 'running', 'restarting'];

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
