<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan perubahan menyebut BARIS satuan kerja yang dimaksudnya.
 *
 * Tanpa itu, permintaan "tarik pengiriman" pada rekomendasi yang dipikul tiga
 * satuan kerja tidak menyebut kiriman siapa yang ditarik — dan yang memutus
 * harus menebak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permintaan_ubahs', function (Blueprint $t) {
            $t->foreignId('sasaran_id')->nullable()->after('rekomendasi_id')
                ->constrained('sasarans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permintaan_ubahs', function (Blueprint $t) {
            $t->dropConstrainedForeignId('sasaran_id');
        });
    }
};
