<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cronjob;

class CronjobController extends Controller
{
    public function index()
    {

        $cronjobs = Cronjob::orderBy('created_at', 'desc')->get();
        return view('cronjobs.ajax', compact('cronjobs'));
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
}
