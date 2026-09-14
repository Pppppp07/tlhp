<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tanpa daftar apa yang diminta, progres "2 dari 4" tidak punya penyebut.
        Schema::create('item_permintaans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_dokumen_id')->constrained('permintaan_dokumens')->cascadeOnDelete();
            $table->string('nama');
            $table->boolean('terpenuhi')->default(false);
            $table->date('dipenuhi_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_permintaans');
    }
};
