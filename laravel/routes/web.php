<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});


use App\Http\Controllers\CronjobController;



use App\Http\Controllers\ApiKeyController;





use App\Http\Controllers\CronLogController;
use App\Http\Controllers\RtoDomainController;

use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SystemUpdateController;


Route::get('/login', [UserAuthController::class, 'loginForm'])->name('login');
Route::post('/login', [UserAuthController::class, 'login']);
Route::post('/logout', [UserAuthController::class, 'logout'])->name('logout');
Route::get('/register', [UserAuthController::class, 'registerForm']);
Route::post('/register', [UserAuthController::class, 'register']);

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');



    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');


    Route::get('/cronjobs', [CronjobController::class, 'index'])->name('cronjobs.index');
    Route::get('/cronjobs/data', [CronjobController::class, 'getData'])->name('cronjobs.data');
    Route::post('/cronjobs/store', [CronjobController::class, 'store'])->name('cronjobs.store');
    Route::post('/cronjobs/update/{id}', [CronjobController::class, 'update'])->name('cronjobs.update');

    Route::get('/cronjobs/edit/{id}', [CronjobController::class, 'edit'])->name('cronjobs.edit');





    Route::get('/cronlogs/{id}', [CronLogController::class, 'index'])->name('cronlogs.index');
    Route::delete('/cronlogs/{id}/clear', [\App\Http\Controllers\CronLogController::class, 'clear'])->name('cronlogs.clear');

    // RTO Domains
    Route::get('/rto-domains', [RtoDomainController::class, 'index'])->name('rto-domains.index');

    // --- Hanya untuk admin ---
    Route::middleware('can:admin')->group(function () {
        Route::get('/apikeys', [ApiKeyController::class, 'index'])->name('apikeys.index');
        Route::post('/apikeys/store', [ApiKeyController::class, 'store'])->name('apikeys.store');
        Route::post('/apikeys/update/{id}', [ApiKeyController::class, 'update'])->name('apikeys.update');
        Route::delete('/apikeys/delete/{id}', [ApiKeyController::class, 'destroy'])->name('apikeys.destroy');
        Route::get('/apikeys/edit/{id}', [ApiKeyController::class, 'edit'])->name('apikeys.edit');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'store'])->name('settings.store');

        Route::delete('/cronjobs/delete/{id}', [CronjobController::class, 'destroy'])->name('cronjobs.destroy');
        Route::post('/cronjobs/bulk-delete', [\App\Http\Controllers\CronjobController::class, 'bulkDelete'])->name('cronjobs.bulk-delete');

        // RTO Domains - Admin only
        Route::delete('/rto-domains/{id}', [RtoDomainController::class, 'destroy'])->name('rto-domains.destroy');
        Route::delete('/rto-domains/{id}/delete-cronjobs', [RtoDomainController::class, 'deleteCronjobsByDomain'])->name('rto-domains.delete-cronjobs');
        Route::post('/rto-domains/bulk-delete-cronjobs', [RtoDomainController::class, 'bulkDeleteCronjobs'])->name('rto-domains.bulk-delete-cronjobs');

        // Manajemen User
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users/store', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/edit/{id}', [UserController::class, 'edit'])->name('users.edit');
        Route::post('/users/update/{id}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/delete/{id}', [UserController::class, 'destroy'])->name('users.destroy');

        // Update System
        Route::get('/system-update', [SystemUpdateController::class, 'index'])->name('system-update.index');
        Route::get('/system-update/check', [SystemUpdateController::class, 'checkUpdate'])->name('system-update.check');
        Route::post('/system-update/pull', [SystemUpdateController::class, 'pull'])->name('system-update.pull');
    });
});
