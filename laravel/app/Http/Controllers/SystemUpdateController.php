<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class SystemUpdateController extends Controller
{
    public function index()
    {
        $mirror = config('deploy.mirror_path');
        $current = null;

        if (is_dir($mirror . '/.git')) {
            $result = Process::path($mirror)->run(['git', 'log', '-1', '--pretty=format:%H|%s|%ci']);
            if ($result->successful() && trim($result->output()) !== '') {
                [$hash, $message, $date] = array_pad(explode('|', trim($result->output()), 3), 3, null);
                $current = ['hash' => $hash, 'message' => $message, 'date' => $date];
            }
        }

        return view('system_update.index', compact('current'));
    }

    public function checkUpdate()
    {
        $mirror = config('deploy.mirror_path');
        $localHash = null;

        if (is_dir($mirror . '/.git')) {
            $result = Process::path($mirror)->run(['git', 'rev-parse', 'HEAD']);
            if ($result->successful()) {
                $localHash = trim($result->output());
            }
        }

        $response = Http::withHeaders(['User-Agent' => 'mycron-update-check'])
            ->timeout(15)
            ->get('https://api.github.com/repos/' . config('deploy.github_repo') . '/commits/' . config('deploy.branch'));

        if (! $response->successful()) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menghubungi GitHub API.'], 502);
        }

        $latestHash = $response->json('sha');
        $latestMessage = $response->json('commit.message');

        return response()->json([
            'status' => 'success',
            'local_hash' => $localHash,
            'latest_hash' => $latestHash,
            'latest_message' => $latestMessage,
            'up_to_date' => $localHash !== null && $localHash === $latestHash,
        ]);
    }

    public function pull()
    {
        $lock = Cache::lock('system-update-pull', 300);

        if (! $lock->get()) {
            return response()->json(['status' => 'error', 'message' => 'Proses update lain sedang berjalan, coba lagi sebentar.'], 429);
        }

        try {
            $mirror = config('deploy.mirror_path');
            $branch = config('deploy.branch');
            $basePath = base_path();
            $nodeWorkerPath = config('deploy.node_worker_path');

            $gitStep = is_dir($mirror . '/.git')
                ? ['git', 'pull', 'origin', $branch]
                : ['git', 'clone', '--branch', $branch, config('deploy.repo_url'), $mirror];

            $steps = [
                ['label' => 'Git pull dari GitHub', 'command' => $gitStep, 'cwd' => is_dir($mirror . '/.git') ? $mirror : null],
                ['label' => 'Sync kode Laravel', 'command' => ['rsync', '-a', '--delete', '--exclude=vendor', '--exclude=.env', '--exclude=node_modules', rtrim($mirror, '/') . '/laravel/', rtrim($basePath, '/') . '/'], 'cwd' => null],
                ['label' => 'Sync kode Node Worker', 'command' => ['rsync', '-a', '--exclude=node_modules', '--exclude=.env', rtrim($mirror, '/') . '/node-worker/', rtrim($nodeWorkerPath, '/') . '/'], 'cwd' => null],
                ['label' => 'Composer install', 'command' => ['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'], 'cwd' => $basePath],
                ['label' => 'Database migrate', 'command' => ['php', 'artisan', 'migrate', '--force'], 'cwd' => $basePath],
                ['label' => 'Cache config', 'command' => ['php', 'artisan', 'config:cache'], 'cwd' => $basePath],
                ['label' => 'Cache route', 'command' => ['php', 'artisan', 'route:cache'], 'cwd' => $basePath],
                ['label' => 'Cache view', 'command' => ['php', 'artisan', 'view:cache'], 'cwd' => $basePath],
            ];

            $log = [];

            foreach ($steps as $step) {
                $process = Process::timeout(300);
                if ($step['cwd']) {
                    $process = $process->path($step['cwd']);
                }

                $result = $process->run($step['command']);

                $log[] = [
                    'label' => $step['label'],
                    'ok' => $result->successful(),
                    'output' => trim($result->output() . "\n" . $result->errorOutput()),
                ];

                if (! $result->successful()) {
                    return response()->json(['status' => 'error', 'message' => $step['label'] . ' gagal.', 'log' => $log], 500);
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Update berhasil diterapkan. Jika ada perubahan di node-worker, restart manual via SSH: systemctl restart schedule-node-worker',
                'log' => $log,
            ]);
        } finally {
            $lock->release();
        }
    }
}
