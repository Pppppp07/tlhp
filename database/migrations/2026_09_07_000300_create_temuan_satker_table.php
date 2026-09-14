<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu temuan bisa mengenai beberapa satuan kerja sekaligus.
 *
 * Kolom `temuans.satker_id` tunggal memaksa satu temuan dipecah jadi beberapa
 * baris supaya bisa menyebut semua satuan kerjanya — dan sesudah dipecah,
 * nilai temuannya ikut terhitung berkali-kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temuan_satker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temuan_id')->constrained('temuans')->cascadeOnDelete();
            $table->foreignId('satker_id')->constrained('satkers')->cascadeOnDelete();
            $table->unique(['temuan_id', 'satker_id'], 'temuan_satker_unik');
        });

        // Isi lama dipindahkan dulu, baru kolomnya dibuang.
        if (Schema::hasColumn('temuans', 'satker_id')) {
            DB::table('temuans')->whereNotNull('satker_id')->orderBy('id')
                ->chunk(200, function ($baris) {
                    DB::table('temuan_satker')->insertOrIgnore(
                        $baris->map(fn ($t) => [
                            'temuan_id' => $t->id,
                            'satker_id' => $t->satker_id,
                        ])->all()
                    );
                });

            /* Tiga langkah, dan urutannya mengikat. MySQL menolak membuang
               indeks yang masih dipakai kunci asing; SQLite menolak membuang
               kolom yang masih dipakai indeks. Jadi: lepas kuncinya, buang
               indeksnya, baru buang kolomnya. */
            Schema::table('temuans', function (Blueprint $table) {
                $table->dropForeign(['satker_id']);
            });
            Schema::table('temuans', function (Blueprint $table) {
                $table->dropIndex(['satker_id']);
            });
            Schema::table('temuans', function (Blueprint $table) {
                $table->dropColumn('satker_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('temuans', function (Blueprint $table) {
            $table->foreignId('satker_id')->nullable()->after('laporan_id')
                  ->constrained('satkers')->nullOnDelete();
        });

        // Kembalikan satu satuan kerja saja — yang pertama. Turun tangga ini
        // memang tidak bisa utuh: bentuk lamanya tidak sanggup menyimpannya.
        DB::table('temuan_satker')->orderBy('temuan_id')->orderBy('id')
            ->get()->groupBy('temuan_id')->each(function ($baris, $temuanId) {
                DB::table('temuans')->where('id', $temuanId)
                    ->update(['satker_id' => $baris->first()->satker_id]);
            });

        Schema::dropIfExists('temuan_satker');
    }
};
