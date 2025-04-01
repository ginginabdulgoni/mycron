<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyApiKey;
use App\Http\Controllers\Api\CronjobApiController;

// Route::middleware(VerifyApiKey::class)->group(function () {
//     Route::post('/cronjobs', [CronjobApiController::class, 'store']);
//     Route::get('/cronjobs', [CronjobApiController::class, 'index']);
// });


Route::get('/cronjobs', [CronjobApiController::class, 'index']);         // list
Route::post('/cronjobs', [CronjobApiController::class, 'store']);        // insert
Route::get('/cronjobs/{id}', [CronjobApiController::class, 'show']);     // (opsional) detail
Route::put('/cronjobs/{id}', [CronjobApiController::class, 'update']);   // update
Route::delete('/cronjobs/{id}', [CronjobApiController::class, 'destroy']); // delete
