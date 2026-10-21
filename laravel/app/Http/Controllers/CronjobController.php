<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cronjob;
use Carbon\Carbon;

class CronjobController extends Controller
{
    public function index()
    {
        // Hanya return view, data akan diload via AJAX
        return view('cronjobs.ajax');
    }

    public function getData(Request $request)
    {
        $query = Cronjob::query();

        // Total records
        $totalData = $query->count();

        // Search
        if ($request->has('search') && $request->search['value'] != '') {
            $search = $request->search['value'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%")
                  ->orWhere('schedule', 'like', "%{$search}%");
            });
        }

        // Filtered records
        $totalFiltered = $query->count();

        // Ordering
        if ($request->has('order')) {
            $columnIndex = $request->order[0]['column'];
            $columnName = $request->columns[$columnIndex]['data'];
            $direction = $request->order[0]['dir'];

            // Map column names
            $orderColumns = ['id', 'name', 'url', 'schedule', 'last_run_at', 'active', 'save_logs'];
            if (isset($orderColumns[$columnIndex])) {
                $query->orderBy($orderColumns[$columnIndex], $direction);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $start = $request->start ?? 0;
        $length = $request->length ?? 10;
        $cronjobs = $query->skip($start)->take($length)->get();

        // Get RTO domains
        $rtoDomains = \App\Models\RtoDomain::pluck('domain')->toArray();
        $timezone = setting('timezone', config('app.timezone', 'UTC'));
        $nowUtc = Carbon::now('UTC');

        // Format data
        $data = [];
        foreach ($cronjobs as $cron) {
            // Extract domain from URL
            $domain = null;
            try {
                $urlObj = parse_url($cron->url);
                $domain = $urlObj['host'] ?? null;
            } catch (\Exception $e) {
                // Invalid URL
            }

            // Check if domain is in RTO list
            $isRto = $domain && in_array($domain, $rtoDomains);

            // Deteksi apakah cronjob belum tereksekusi atau stuck (terlambat)
            $healthStatus = 'healthy';
            if (!$cron->active) {
                $healthStatus = 'inactive';
            } elseif (empty($cron->last_run_at)) {
                $healthStatus = 'unexecuted';
            } else {
                $expectedMins = Cronjob::expectedIntervalMinutes($cron->schedule);
                $minsSince = max(0, (int) Carbon::parse($cron->last_run_at, 'UTC')->diffInMinutes($nowUtc, false));
                if ($minsSince > max($expectedMins * 2, $expectedMins + 3)) {
                    $healthStatus = 'stuck';
                }
            }

            $data[] = [
                'id' => $cron->id,
                'name' => $cron->name,
                'url' => $cron->url,
                'schedule' => $cron->schedule,
                'last_run_at' => $cron->last_run_at
                    ? Carbon::parse($cron->last_run_at, 'UTC')->timezone($timezone)->format('d-m-Y H:i')
                    : 'Belum ada',
                'active' => $cron->active ? 'Aktif' : 'Nonaktif',
                'save_logs' => $cron->save_logs ? 'Aktif' : 'Nonaktif',
                'is_rto' => $isRto,
                'health_status' => $healthStatus,
                'domain' => $domain
            ];
        }

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalData,
            'recordsFiltered' => $totalFiltered,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'url' => 'required|url',
            'schedule' => 'required',
            'active' => 'nullable|in:on,1,true',
            'save_logs' => 'nullable|in:on,1,true',
        ]);

        $data['active'] = $request->has('active');
        $data['save_logs'] = $request->has('save_logs');

        Cronjob::updateOrCreate(
            ['url' => $data['url']], // cari berdasarkan URL
            $data // update field lainnya
        );

        return response()->json(['status' => 'success', 'message' => 'Cronjob stored']);
    }


    public function edit($id)
    {
        $cron = Cronjob::findOrFail($id);
        return response()->json($cron);
    }

    public function update(Request $request, $id)
    {
        $cron = Cronjob::findOrFail($id);

        $data = $request->validate([
            'name' => 'required',
            'url' => 'required|url|unique:cronjobs,url,' . $cron->id,
            'schedule' => 'required',
            'active' => 'nullable|in:on,1,true',
            'save_logs' => 'nullable|in:on,1,true',
        ], [
            'url.unique' => 'URL ini sudah digunakan oleh cronjob lain.',
        ]);

        // ✅ Ubah checkbox menjadi 1/0
        $data['active'] = $request->has('active') ? 1 : 0;
        $data['save_logs'] = $request->has('save_logs') ? 1 : 0;

        $cron->update($data);

        return response()->json(['status' => 'success', 'message' => 'Cronjob updated']);
    }



    public function destroy($id)
    {
        Cronjob::destroy($id);
        return response()->json(['status' => 'success', 'message' => 'Cronjob deleted']);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:cronjobs,id',
        ]);

        $deleted = Cronjob::whereIn('id', $request->ids)->delete();

        return response()->json([
            'status' => 'success',
            'message' => "$deleted cronjob berhasil dihapus."
        ]);
    }

    /**
     * Eksekusi cronjob secara manual (langsung) dari Dashboard / halaman Cronjob.
     */
    public function runNow($id)
    {
        $cron = Cronjob::findOrFail($id);
        $utcNow = Carbon::now('UTC')->format('Y-m-d H:i:s');

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withoutVerifying()
                ->get($cron->url);

            $cron->update(['last_run_at' => $utcNow]);

            if ($cron->save_logs) {
                \App\Models\CronLog::create([
                    'cronjob_id' => $cron->id,
                    'status' => $response->successful() ? 'success' : 'error',
                    'response' => (string) $response->status(),
                    'response_body' => mb_substr($response->body(), 0, 10000),
                    'run_at' => $utcNow,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Cronjob \"{$cron->name}\" berhasil dieksekusi (HTTP {$response->status()}).",
            ]);
        } catch (\Throwable $e) {
            $cron->update(['last_run_at' => $utcNow]);

            if ($cron->save_logs) {
                \App\Models\CronLog::create([
                    'cronjob_id' => $cron->id,
                    'status' => 'error',
                    'response' => mb_substr($e->getMessage(), 0, 250),
                    'response_body' => null,
                    'run_at' => $utcNow,
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengeksekusi URL: ' . $e->getMessage(),
            ], 422);
        }
    }
}
