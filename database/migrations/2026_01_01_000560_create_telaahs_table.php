<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hasil telaah UKI dan verifikasi Inspektorat. Berdiri sendiri dari
        // tindak lanjut satuan kerja karena penulisnya pihak lain, dan yang
        // membaca perlu tahu siapa yang menilai apa.
        //
        // Sebelumnya keduanya cuma menekan satu tombol "bukti cukup" tanpa bisa
        // menulis apa pun — berkasnya lewat, kesimpulannya tidak ke mana-mana.
        Schema::create('telaahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label_oleh');          // "UKI" atau "Inspektorat"
            $table->text('catatan');               // wajib — telaah tanpa kesimpulan bukan telaah

            // Catatan telaah tersendiri kerap ada dan perlu ikut tersimpan.
            // Berkasnya tetap masuk lampiran; ini penunjuknya.
            $table->foreignId('lampiran_id')->nullable()->constrained('lampirans')->nullOnDelete();

            $table->timestamps();
            $table->index(['rekomendasi_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telaahs');
    }
};
