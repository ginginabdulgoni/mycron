<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SystemTask;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Update System.
 *
 * Panel ini tidak menjalankan git/pm2 sendiri (PHP berjalan sebagai www, worker milik root).
 * Panel hanya menulis permintaan ke tabel system_tasks; node-worker (system.js) yang
 * mengeksekusinya lalu menulis log + heartbeat kembali ke database.
 */
class SystemUpdateController extends Controller
{
    public function index()
    {
        return view('system_update.index', [
            'tableReady' => Schema::hasTable('system_tasks'),
        ]);
    }

    /** Status worker + versi kode + riwayat task (dipanggil berkala oleh halaman). */
    public function status()
    {
        $tz = setting('timezone', config('app.timezone', 'UTC'));
        $s = Setting::whereIn('key', [
            'worker_heartbeat', 'worker_started_at', 'worker_pid', 'worker_jobs_count',
            'worker_git_head', 'worker_git_subject', 'worker_git_date', 'worker_branch', 'worker_node_version',
        ])->pluck('value', 'key');

        $heartbeat = (int) ($s['worker_heartbeat'] ?? 0);
        $startedAt = (int) ($s['worker_started_at'] ?? 0);
        $online = $heartbeat > 0 && (time() - $heartbeat) <= config('deploy.heartbeat_timeout');

        $tasks = Schema::hasTable('system_tasks')
            ? SystemTask::orderByDesc('id')->limit(15)->get()->map(fn (SystemTask $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'status' => $t->status,
                'requested_by' => $t->requested_by,
                'log' => $t->log,
                'created_at' => $t->created_at?->copy()->setTimezone($tz)->format('d/m/Y H:i:s'),
                'finished_at' => $t->finished_at?->copy()->setTimezone($tz)->format('d/m/Y H:i:s'),
                'duration' => $t->started_at && $t->finished_at ? $t->started_at->diffInSeconds($t->finished_at) : null,
            ])
            : collect();

        return response()->json([
            'worker' => [
                'online' => $online,
                'last_seen' => $heartbeat ? Carbon::createFromTimestamp($heartbeat)->setTimezone($tz)->diffForHumans() : null,
                'started_at' => $startedAt ? Carbon::createFromTimestamp($startedAt)->setTimezone($tz)->format('d/m/Y H:i') : null,
                'uptime' => $startedAt ? Carbon::createFromTimestamp($startedAt)->diffForHumans(null, true) : null,
                'pid' => $s['worker_pid'] ?? null,
                'jobs' => $s['worker_jobs_count'] ?? null,
                'node' => $s['worker_node_version'] ?? null,
            ],
            'version' => [
                'hash' => $s['worker_git_head'] ?? null,
                'subject' => $s['worker_git_subject'] ?? null,
                'date' => $s['worker_git_date'] ?? null,
                'branch' => $s['worker_branch'] ?? config('deploy.branch'),
            ],
            'tasks' => $tasks,
            'busy' => $tasks->contains(fn ($t) => in_array($t['status'], SystemTask::ACTIVE_STATUSES, true)),
        ]);
    }

    /** Bandingkan versi terpasang dengan GitHub dan tampilkan daftar commit baru. */
    public function checkUpdate()
    {
        $installed = Setting::where('key', 'worker_git_head')->value('value');
        $repo = config('deploy.github_repo');
        $branch = config('deploy.branch');

        $github = function (string $path) {
            $req = Http::withHeaders(['User-Agent' => 'mycron-update-check', 'Accept' => 'application/vnd.github+json'])
                ->timeout(15);
            if ($token = config('deploy.github_token')) {
                $req = $req->withToken($token);
            }
            return $req->get('https://api.github.com/repos/' . $path);
        };

        $latest = Cache::remember('system-update.latest', 30, function () use ($github, $repo, $branch) {
            $res = $github("{$repo}/commits/{$branch}");
            return $res->successful() ? $res->json() : null;
        });

        if (! $latest) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menghubungi GitHub. Coba lagi beberapa saat.'], 502);
        }

        $latestHash = $latest['sha'] ?? null;
        $commits = [];

        if ($installed && $latestHash && $installed !== $latestHash) {
            $cmp = $github("{$repo}/compare/{$installed}...{$latestHash}");
            if ($cmp->successful()) {
                $commits = collect($cmp->json('commits', []))->reverse()->map(fn ($c) => [
                    'hash' => substr($c['sha'], 0, 7),
                    'message' => strtok($c['commit']['message'] ?? '', "\n"),
                    'author' => $c['commit']['author']['name'] ?? null,
                    'date' => isset($c['commit']['author']['date'])
                        ? Carbon::parse($c['commit']['author']['date'])->setTimezone(setting('timezone', 'UTC'))->format('d/m/Y H:i')
                        : null,
                ])->values()->all();
            }
        }

        return response()->json([
            'status' => 'success',
            'installed_hash' => $installed,
            'latest_hash' => $latestHash,
            'latest_message' => strtok($latest['commit']['message'] ?? '', "\n"),
            'up_to_date' => $installed !== null && $installed === $latestHash,
            'unknown_installed' => $installed === null,
            'commits' => $commits,
        ]);
    }

    /** Antrekan aksi untuk dikerjakan worker: update | restart | reload. */
    public function enqueue(Request $request)
    {
        $data = $request->validate(['type' => 'required|in:update,restart,reload']);

        if (! Schema::hasTable('system_tasks')) {
            return response()->json(['status' => 'error', 'message' => 'Tabel system_tasks belum ada. Jalankan migrate atau restart worker sekali via SSH.'], 409);
        }

        $active = SystemTask::whereIn('status', SystemTask::ACTIVE_STATUSES)->first();
        if ($active) {
            return response()->json([
                'status' => 'error',
                'message' => "Masih ada proses #{$active->id} ({$active->type}) yang berjalan. Tunggu sampai selesai.",
            ], 429);
        }

        $task = SystemTask::create([
            'type' => $data['type'],
            'status' => 'pending',
            'requested_by' => optional($request->user())->name,
        ]);

        Cache::forget('system-update.latest');

        return response()->json([
            'status' => 'success',
            'task_id' => $task->id,
            'message' => 'Permintaan dikirim ke worker. Biasanya mulai diproses dalam 5 detik.',
        ]);
    }
}
