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

    public function lastLog()
    {
        return $this->hasOne(CronLog::class)->latestOfMany();
    }

    protected $casts = [
        'run_at' => 'datetime',
    ];

    /**
     * Estimasi interval jadwal cron dalam satuan menit.
     */
    public static function expectedIntervalMinutes(?string $schedule): int
    {
        $parts = preg_split('/\s+/', trim($schedule ?: '* * * * *'));
        if (count($parts) < 5) {
            return 1;
        }

        [$min, $hour, $dom, $month, $dow] = $parts;

        if (str_starts_with($min, '*/')) {
            return max(1, (int) substr($min, 2));
        }
        if (str_starts_with($hour, '*/')) {
            return max(1, (int) substr($hour, 2)) * 60;
        }
        if (str_starts_with($dom, '*/')) {
            return max(1, (int) substr($dom, 2)) * 1440;
        }
        if ($dow !== '*') {
            return 7 * 1440;
        }
        if ($dom !== '*') {
            return 30 * 1440;
        }
        if ($hour !== '*') {
            return 1440;
        }
        if ($min !== '*') {
            return 60;
        }

        return 1;
    }

    /**
     * Label jadwal cron yang mudah dibaca manusia.
     */
    public static function scheduleHumanLabel(?string $schedule): string
    {
        $mins = self::expectedIntervalMinutes($schedule);
        if ($mins < 60) {
            return "Setiap {$mins} menit";
        }
        if ($mins < 1440) {
            $hours = (int) round($mins / 60);
            return "Setiap {$hours} jam";
        }
        if ($mins < 10080) {
            $days = (int) round($mins / 1440);
            return "Setiap {$days} hari";
        }
        if ($mins < 43200) {
            return "Setiap minggu";
        }
        return "Setiap bulan";
    }
}
