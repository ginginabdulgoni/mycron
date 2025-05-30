<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cronjob extends Model
{
    protected $fillable = ['name', 'url', 'schedule', 'active', 'save_logs', 'last_run_at'];

    public function logs(): HasMany
    {
        return $this->hasMany(CronLog::class);
    }

    // app/Models/Cronjob.php
    public function lastLog()
    {
        return $this->hasOne(CronLog::class)->latestOfMany(); // Laravel 8+
    }

    // app/Models/CronLog.php
    protected $casts = [
        'run_at' => 'datetime',
    ];
}
