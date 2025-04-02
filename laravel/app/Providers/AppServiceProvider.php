<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // cek apakah tabel settings sudah tersedia
        if (Schema::hasTable('settings')) {
            $tz = \App\Models\Setting::where('key', 'timezone')->value('value') ?? 'UTC';

            Config::set('app.timezone', $tz);
            date_default_timezone_set($tz);
        }
        Gate::define('admin', fn ($user) => $user->role === 'admin');
        // untuk Carbon (Laravel helper)
    }
}
