<?php

namespace App\Http\Controllers;

use App\Models\Cronjob;

class CronLogController extends Controller
{
    public function index($id)
    {
        $cronjob = Cronjob::with('logs')->findOrFail($id);
        return view('cronjobs.logs', compact('cronjob'));
    }
}
