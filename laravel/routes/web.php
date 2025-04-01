<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\CronjobController;

Route::get('/cronjobs', [CronjobController::class, 'index'])->name('cronjobs.index');
Route::post('/cronjobs/store', [CronjobController::class, 'store'])->name('cronjobs.store');
Route::post('/cronjobs/update/{id}', [CronjobController::class, 'update'])->name('cronjobs.update');
Route::delete('/cronjobs/delete/{id}', [CronjobController::class, 'destroy'])->name('cronjobs.destroy');
Route::get('/cronjobs/edit/{id}', [CronjobController::class, 'edit'])->name('cronjobs.edit');

use App\Http\Controllers\ApiKeyController;

Route::get('/apikeys', [ApiKeyController::class, 'index'])->name('apikeys.index');
Route::post('/apikeys/store', [ApiKeyController::class, 'store'])->name('apikeys.store');
Route::post('/apikeys/update/{id}', [ApiKeyController::class, 'update'])->name('apikeys.update');
Route::delete('/apikeys/delete/{id}', [ApiKeyController::class, 'destroy'])->name('apikeys.destroy');
Route::get('/apikeys/edit/{id}', [ApiKeyController::class, 'edit'])->name('apikeys.edit');



use App\Http\Controllers\CronLogController;

Route::get('/cronlogs/{id}', [CronLogController::class, 'index'])->name('cronlogs.index');
