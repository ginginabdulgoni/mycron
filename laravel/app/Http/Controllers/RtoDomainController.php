<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RtoDomain;
use App\Models\Cronjob;
use Illuminate\Support\Facades\DB;

class RtoDomainController extends Controller
{
    public function index()
    {
        $rtoDomains = RtoDomain::orderBy('last_checked_at', 'desc')->get();

        // Get cronjob counts for each domain
        foreach ($rtoDomains as $domain) {
            $domain->cronjob_count = Cronjob::where('url', 'LIKE', '%' . $domain->domain . '%')->count();
        }

        // Get timezone from settings
        $timezone = setting('timezone', config('app.timezone', 'UTC'));

        return view('rto_domains.index', compact('rtoDomains', 'timezone'));
    }

    public function deleteCronjobsByDomain($id)
    {
        $rtoDomain = RtoDomain::findOrFail($id);

        // Delete all cronjobs that contain this domain
        $deleted = Cronjob::where('url', 'LIKE', '%' . $rtoDomain->domain . '%')->delete();

        // Delete the RTO domain record
        $rtoDomain->delete();

        return response()->json([
            'status' => 'success',
            'message' => "$deleted cronjob(s) dengan domain {$rtoDomain->domain} berhasil dihapus."
        ]);
    }

    public function destroy($id)
    {
        $rtoDomain = RtoDomain::findOrFail($id);
        $rtoDomain->delete();

        return response()->json([
            'status' => 'success',
            'message' => "Domain {$rtoDomain->domain} berhasil dihapus dari daftar RTO."
        ]);
    }

    public function bulkDeleteCronjobs(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:rto_domains,id',
        ]);

        $rtoDomains = RtoDomain::whereIn('id', $request->ids)->get();
        $totalDeleted = 0;
        $domains = [];

        foreach ($rtoDomains as $rtoDomain) {
            $deleted = Cronjob::where('url', 'LIKE', '%' . $rtoDomain->domain . '%')->delete();
            $totalDeleted += $deleted;
            $domains[] = $rtoDomain->domain;

            // Delete the RTO domain record as well
            $rtoDomain->delete();
        }

        $domainList = implode(', ', $domains);

        return response()->json([
            'status' => 'success',
            'message' => "$totalDeleted cronjob(s) dari domain ($domainList) berhasil dihapus."
        ]);
    }
}
