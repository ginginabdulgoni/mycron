<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Buat akun admin default. Kredensial bisa di-override lewat .env:
     * ADMIN_NAME, ADMIN_EMAIL, ADMIN_PASSWORD.
     * Aman dijalankan berulang: password akun yang sudah ada tidak ditimpa.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@mycron.local');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'role' => 'admin',
            ]
        );

        if ($user->role !== 'admin') {
            $user->update(['role' => 'admin']);
        }

        $this->command?->info($user->wasRecentlyCreated
            ? "Admin dibuat: {$email}"
            : "Admin sudah ada: {$email} (password tidak diubah)");
    }
}
