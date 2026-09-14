<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi_bacas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notifikasi_id')->constrained('notifikasis')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('dibaca_pada');
            $table->unique(['notifikasi_id', 'user_id'], 'notifikasi_user_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_bacas');
    }
};
