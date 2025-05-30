<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cronjob;

class CronjobApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Cronjob::query();

        if ($request->has('domain')) {
            $domain = $request->domain;
            $query->where('url', 'like', "%$domain%");
        }

        $cronjobs = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $cronjobs
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'url' => 'required|url',
            'schedule' => 'required',
            'active' => 'nullable|in:on,1,true',
        ]);

        $data['active'] = $request->has('active');

        // Skip jika URL mengandung localhost atau IP private
        if ($this->isLocalOrPrivate($data['url'])) {
            return response()->json([
                'status' => 'skipped',
                'message' => 'Cronjob tidak disimpan karena URL mengandung IP lokal atau localhost.'
            ]);
        }

        Cronjob::updateOrCreate(
            ['url' => $data['url']],
            $data
        );

        return response()->json(['status' => 'success', 'message' => 'Cronjob stored']);
    }
    private function isLocalOrPrivate($url)
    {
        $host = parse_url($url, PHP_URL_HOST);

        // Cek jika host = localhost
        if ($host === 'localhost') return true;

        // Cek jika host adalah IP address lokal
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // IP Private range
            if (
                preg_match('/^127\./', $host) ||                      // Loopback
                preg_match('/^10\./', $host) ||                       // Private A
                preg_match('/^192\.168\./', $host) ||                 // Private C
                preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $host) // Private B
            ) {
                return true;
            }
        }

        return false;
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
        ], [
            'url.unique' => 'URL ini sudah digunakan oleh cronjob lain.',
        ]);

        $data['active'] = $request->has('active');
        $cron->update($data);

        return response()->json(['status' => 'success', 'message' => 'Cronjob updated']);
    }


    public function destroy($id)
    {
        Cronjob::destroy($id);
        return response()->json(['status' => 'success', 'message' => 'Cronjob deleted']);
    }
}
