<?php

namespace App\Http\Controllers;

use App\Models\Cronjob;

class CronLogController extends Controller
{
    public function index($id)
    {
        $cronjob = Cronjob::findOrFail($id);

        // Ambil lognya dengan paginate, default 10 entry
        $logs = $cronjob->logs()->orderByDesc('run_at')->paginate(10);

        return view('cronjobs.logs', compact('cronjob', 'logs'));
    }


    public function clear($id)
    {
        $cronjob = Cronjob::findOrFail($id);
        $cronjob->logs()->delete();

        return redirect()->route('cronlogs.index', $id)->with('success', 'Log berhasil dihapus.');
    }
}
