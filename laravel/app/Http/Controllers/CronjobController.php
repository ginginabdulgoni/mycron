<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cronjob;

class CronjobController extends Controller
{
    public function index()
    {
        $cronjobs = Cronjob::latest()->get();
        return view('cronjobs.ajax', compact('cronjobs'));
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
        Cronjob::create($data);

        return response()->json(['status' => 'success', 'message' => 'Cronjob created']);
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
            'url' => 'required|url',
            'schedule' => 'required',
            'active' => 'nullable|in:on,1,true',
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
