<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permintaan_dokumens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->string('peran_peminta', 20);  // Setba atau UKI
            $table->foreignId('diminta_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_dokumens');
    }
};
