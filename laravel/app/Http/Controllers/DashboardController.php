<?php

namespace App\Http\Controllers;

use App\Models\Cronjob;
use App\Models\CronLog;
use App\Models\RtoDomain;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Menampilkan halaman dashboard lengkap dengan statistik, grafik,
     * dan pemantauan cronjob yang stuck / belum tereksekusi.
     */
    public function index()
    {
        $timezone = setting('timezone', config('app.timezone', 'UTC'));
        $nowUtc = Carbon::now('UTC');

        // Total aktif & nonaktif
        $totalActive = Cronjob::where('active', 1)->count();
        $totalInactive = Cronjob::where('active', 0)->count();
        $totalRtoDomains = RtoDomain::count();

        // Daftar domain RTO untuk pencocokan cepat
        $rtoDomainsMap = array_flip(
            RtoDomain::pluck('domain')->map(fn ($d) => strtolower(trim($d)))->toArray()
        );

        // Ambil seluruh cronjob aktif untuk klasifikasi kesehatan & deteksi stuck
        $activeCronjobs = Cronjob::where('active', 1)
            ->select(['id', 'name', 'url', 'schedule', 'active', 'save_logs', 'last_run_at', 'created_at'])
            ->orderByRaw('(last_run_at IS NULL) DESC, last_run_at ASC')
            ->get();

        $totalHealthy = 0;
        $totalUnexecuted = 0;
        $totalStuck = 0;
        $totalRtoAffected = 0;

        $freqBreakdown = [
            'Setiap 1 Menit' => 0,
            '2 - 15 Menit' => 0,
            '16 - 59 Menit' => 0,
            'Per Jam' => 0,
            'Harian / Lainnya' => 0,
        ];

        $problemJobs = [];

        foreach ($activeCronjobs as $cron) {
            $expectedMins = Cronjob::expectedIntervalMinutes($cron->schedule);

            // Hitung distribusi frekuensi jadwal
            if ($expectedMins <= 1) {
                $freqBreakdown['Setiap 1 Menit']++;
            } elseif ($expectedMins <= 15) {
                $freqBreakdown['2 - 15 Menit']++;
            } elseif ($expectedMins < 60) {
                $freqBreakdown['16 - 59 Menit']++;
            } elseif ($expectedMins < 1440) {
                $freqBreakdown['Per Jam']++;
            } else {
                $freqBreakdown['Harian / Lainnya']++;
            }

            $host = strtolower((string) parse_url($cron->url, PHP_URL_HOST));
            $isRto = $host !== '' && isset($rtoDomainsMap[$host]);
            if ($isRto) {
                $totalRtoAffected++;
            }

            if (empty($cron->last_run_at)) {
                $totalUnexecuted++;
                $problemJobs[] = [
                    'id' => $cron->id,
                    'name' => $cron->name,
                    'url' => $cron->url,
                    'schedule' => $cron->schedule,
                    'schedule_label' => Cronjob::scheduleHumanLabel($cron->schedule),
                    'status_type' => 'unexecuted',
                    'status_label' => 'Belum Pernah Jalan',
                    'detail' => 'Menunggu eksekusi pertama oleh worker',
                    'last_run_formatted' => 'Belum pernah',
                    'last_run_ago' => '-',
                    'overdue_minutes' => 999999,
                    'is_rto' => $isRto,
                    'priority' => 1,
                ];
                continue;
            }

            $lastRunUtc = Carbon::parse($cron->last_run_at, 'UTC');
            $minsSinceLastRun = max(0, (int) $lastRunUtc->diffInMinutes($nowUtc, false));
            $graceThreshold = max($expectedMins * 2, $expectedMins + 3);

            if ($minsSinceLastRun > $graceThreshold) {
                $totalStuck++;
                $overdueMins = $minsSinceLastRun - $expectedMins;
                $problemJobs[] = [
                    'id' => $cron->id,
                    'name' => $cron->name,
                    'url' => $cron->url,
                    'schedule' => $cron->schedule,
                    'schedule_label' => Cronjob::scheduleHumanLabel($cron->schedule),
                    'status_type' => 'stuck',
                    'status_label' => 'Stuck / Terlambat',
                    'detail' => 'Terlambat ' . self::formatDurationMinutes($overdueMins) . ' dari jadwal',
                    'last_run_formatted' => $lastRunUtc->copy()->timezone($timezone)->format('d-m-Y H:i:s'),
                    'last_run_ago' => self::formatDurationMinutes($minsSinceLastRun) . ' lalu',
                    'overdue_minutes' => $overdueMins,
                    'is_rto' => $isRto,
                    'priority' => 2,
                ];
            } elseif ($isRto) {
                $problemJobs[] = [
                    'id' => $cron->id,
                    'name' => $cron->name,
                    'url' => $cron->url,
                    'schedule' => $cron->schedule,
                    'schedule_label' => Cronjob::scheduleHumanLabel($cron->schedule),
                    'status_type' => 'rto',
                    'status_label' => 'Domain RTO',
                    'detail' => 'Domain mengalami Request Timeout',
                    'last_run_formatted' => $lastRunUtc->copy()->timezone($timezone)->format('d-m-Y H:i:s'),
                    'last_run_ago' => self::formatDurationMinutes($minsSinceLastRun) . ' lalu',
                    'overdue_minutes' => 0,
                    'is_rto' => true,
                    'priority' => 3,
                ];
            } else {
                $totalHealthy++;
            }
        }

        // Urutkan: Belum tereksekusi paling atas, lalu yang paling lama terlambat (stuck), lalu RTO
        usort($problemJobs, function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            return $b['overdue_minutes'] <=> $a['overdue_minutes'];
        });

        $totalProblemJobs = count($problemJobs);
        $problemJobsList = array_slice($problemJobs, 0, 50);

        // Statistik Log 24 Jam Terakhir (Per Jam)
        $start24hUtc = $nowUtc->copy()->subHours(23)->startOfHour();
        $logRows = CronLog::select(
            DB::raw("DATE_FORMAT(run_at, '%Y-%m-%d %H:00:00') as hour_utc"),
            'status',
            DB::raw('COUNT(*) as total')
        )
            ->where('run_at', '>=', $start24hUtc)
            ->groupBy('hour_utc', 'status')
            ->get();

        $hourlyMap = [];
        $logs24hSuccess = 0;
        $logs24hError = 0;

        foreach ($logRows as $row) {
            $isSuccess = strtolower($row->status) === 'success';
            $count = (int) $row->total;
            if ($isSuccess) {
                $logs24hSuccess += $count;
                $hourlyMap[$row->hour_utc]['success'] = ($hourlyMap[$row->hour_utc]['success'] ?? 0) + $count;
            } else {
                $logs24hError += $count;
                $hourlyMap[$row->hour_utc]['error'] = ($hourlyMap[$row->hour_utc]['error'] ?? 0) + $count;
            }
        }

        $chartHoursLabels = [];
        $chartSuccessSeries = [];
        $chartErrorSeries = [];

        for ($i = 23; $i >= 0; $i--) {
            $slotUtc = $nowUtc->copy()->subHours($i)->startOfHour();
            $key = $slotUtc->format('Y-m-d H:00:00');
            $chartHoursLabels[] = $slotUtc->copy()->timezone($timezone)->format('H:00');
            $chartSuccessSeries[] = $hourlyMap[$key]['success'] ?? 0;
            $chartErrorSeries[] = $hourlyMap[$key]['error'] ?? 0;
        }

        $totalLogs24h = $logs24hSuccess + $logs24hError;
        $successRate24h = $totalLogs24h > 0
            ? round(($logs24hSuccess / $totalLogs24h) * 100, 1)
            : 100.0;

        // Status Worker Node
        $heartbeat = (int) setting('worker_heartbeat', 0);
        $workerOnline = $heartbeat > 0 && (time() - $heartbeat) < 75;
        $workerLastSeen = $heartbeat > 0
            ? Carbon::createFromTimestamp($heartbeat, 'UTC')->timezone($timezone)->format('H:i:s')
            : null;

        return view('dashboard', compact(
            'timezone',
            'totalActive',
            'totalInactive',
            'totalRtoDomains',
            'totalHealthy',
            'totalUnexecuted',
            'totalStuck',
            'totalRtoAffected',
            'problemJobsList',
            'totalProblemJobs',
            'logs24hSuccess',
            'logs24hError',
            'totalLogs24h',
            'successRate24h',
            'chartHoursLabels',
            'chartSuccessSeries',
            'chartErrorSeries',
            'freqBreakdown',
            'workerOnline',
            'workerLastSeen'
        ));
    }

    private static function formatDurationMinutes(int $minutes): string
    {
        if ($minutes < 1) {
            return 'baru saja';
        }
        if ($minutes < 60) {
            return "{$minutes} menit";
        }
        $hours = intdiv($minutes, 60);
        $remMins = $minutes % 60;
        if ($hours < 24) {
            return $remMins > 0 ? "{$hours} jam {$remMins} mnt" : "{$hours} jam";
        }
        $days = intdiv($hours, 24);
        $remHours = $hours % 24;
        return $remHours > 0 ? "{$days} hari {$remHours} jam" : "{$days} hari";
    }
}
