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
