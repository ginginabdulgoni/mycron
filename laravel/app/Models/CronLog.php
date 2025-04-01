<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CronLog extends Model
{
    protected $fillable = ['cronjob_id', 'status', 'response', 'run_at'];

    public function cronjob(): BelongsTo
    {
        return $this->belongsTo(Cronjob::class);
    }
}
