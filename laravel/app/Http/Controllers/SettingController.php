<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::pluck('value', 'key');
        return view('settings.index', compact('settings'));
    }

    public function store(Request $request)
    {
        $data = $request->only([
            'company_name',
            'timezone',
            'clear_logs_schedule',
            'auto_delete_rto_days',
            'rto_excluded_domains'
        ]);

        // Convert checkboxes ke 1/0
        $data['clear_logs_active'] = $request->has('clear_logs_active') ? 1 : 0;
        $data['auto_delete_rto_active'] = $request->has('auto_delete_rto_active') ? 1 : 0;

        // Validate auto_delete_rto_days
        if (isset($data['auto_delete_rto_days'])) {
            $data['auto_delete_rto_days'] = max(1, min(365, (int)$data['auto_delete_rto_days']));
        }

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
