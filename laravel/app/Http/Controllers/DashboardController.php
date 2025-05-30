<?php

namespace App\Http\Controllers;

use App\Models\Cronjob;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Menampilkan halaman dashboard.
     */
    public function index()
    {
        // Hitung total cron yang aktif dan tidak aktif
        $totalActive = Cronjob::where('active', 1)->count();
        $totalInactive = Cronjob::where('active', 0)->count();

        // Kirim data ke view
        return view('dashboard', compact('totalActive', 'totalInactive'));
    }
}
