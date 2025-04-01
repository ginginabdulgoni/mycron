<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cronjob extends Model
{
    protected $fillable = ['name', 'url', 'schedule', 'active', 'last_run_at'];

    public function logs(): HasMany
    {
        return $this->hasMany(CronLog::class);
    }
}
