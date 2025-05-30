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
            'clear_logs_schedule'
        ]);

        // Convert checkbox (clear_logs_active) ke 1/0
        $data['clear_logs_active'] = $request->has('clear_logs_active') ? 1 : 0;

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
