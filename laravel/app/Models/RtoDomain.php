<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RtoDomain extends Model
{
    protected $fillable = [
        'domain',
        'rto_count',
        'first_detected_at',
        'last_checked_at',
    ];

    protected $casts = [
        'first_detected_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function cronjobs()
    {
        return $this->hasMany(Cronjob::class, 'url', 'domain');
    }
}
