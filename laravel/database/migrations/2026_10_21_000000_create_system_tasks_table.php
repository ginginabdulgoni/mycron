<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Worker juga membuat tabel ini sendiri (CREATE TABLE IF NOT EXISTS) saat start.
        if (Schema::hasTable('system_tasks')) {
            return;
        }

        Schema::create('system_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // update | restart | reload
            $table->string('status', 20)->default('pending')->index(); // pending | running | restarting | success | failed
            $table->string('requested_by')->nullable();
            $table->mediumText('log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_tasks');
    }
};
