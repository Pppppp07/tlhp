<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memindahkan penugasan dari rekomendasi ke sasaran.
 *
 * Satu-satunya migrasi di rangkaian ini yang memindahkan data lama. Untuk tiap
 * rekomendasi yang sudah ada dibuatkan satu `tindakans` dan satu `sasarans`,
 * lalu kolom lamanya dibuang.
 *
 * Arti `rekomendasis.posisi` ikut berubah di sini. Dulu ia menyimpan sembilan
 * posisi sekaligus — enam yang ditempuh satuan kerja dan tiga yang ditempuh
 * rekomendasinya sendiri. Sekarang enam yang pertama pindah ke
 * `sasarans.posisi`, dan kolom ini hanya menyimpan tiga sisanya. `NULL`
 * berarti tingkat 2 belum berjalan — itu keadaan yang dulu tidak bisa
 * diungkapkan sama sekali.
 */
return new class extends Migration
{
    /** Enam posisi yang ditempuh berkas tiap satuan kerja. */
    private const TINGKAT_1 = [
        'satker', 'setba_tinjau', 'uki', 'setba_teruskan', 'inspektorat', 'tuntas',
    ];

    /** Posisi lama yang namanya berubah. `menunggu_surat` tidak pernah punya
        padanan di prototipe; yang paling dekat adalah `tuntas` — berkas satuan
        kerja ini sudah beres, tinggal menunggu yang lain. */
    private const GANTI_NAMA = ['menunggu_surat' => 'tuntas'];

    public function up(): void
    {
        if (Schema::hasColumn('rekomendasis', 'satker_id')) {
            $waktu = now();

            DB::table('rekomendasis')->orderBy('id')->chunk(200, function ($baris) use ($waktu) {
                foreach ($baris as $r) {
                    $tindakanId = DB::table('tindakans')->insertGetId([
                        'rekomendasi_id' => $r->id,
                        'bentuk_id'      => $r->bentuk_id ?? null,
                        'urutan'         => 1,
                        'created_at'     => $waktu,
                        'updated_at'     => $waktu,
                    ]);

                    // Rekomendasi tanpa satuan kerja tidak bisa punya sasaran.
                    // Tindakannya tetap dibuat supaya bentuknya tidak hilang.
                    if (! $r->satker_id) {
                        continue;
                    }

                    $posisi = self::GANTI_NAMA[$r->posisi] ?? $r->posisi;
                    $tingkat1 = in_array($posisi, self::TINGKAT_1, true);

                    DB::table('sasarans')->insert([
                        'tindakan_id' => $tindakanId,
                        'satker_id'   => $r->satker_id,
                        'nilai'       => $r->nilai_pulih ?? 0,
                        // Berkas yang sudah masuk tingkat 2 berarti bagian
                        // satuan kerjanya memang sudah tuntas.
                        'posisi'      => $tingkat1 ? $posisi : 'tuntas',
                        'hasil'       => null,
                        'catatan'     => null,
                        'created_at'  => $waktu,
                        'updated_at'  => $waktu,
                    ]);
                }
            });

            // Sama seperti temuans: kunci dulu, indeks, baru kolomnya.
            Schema::table('rekomendasis', function (Blueprint $table) {
                $table->dropForeign(['satker_id']);
                $table->dropForeign(['bentuk_id']);
            });
            Schema::table('rekomendasis', function (Blueprint $table) {
                $table->dropIndex(['satker_id']);
            });
            Schema::table('rekomendasis', function (Blueprint $table) {
                $table->dropColumn(['satker_id', 'bentuk_id']);
            });
        }

        Schema::table('rekomendasis', function (Blueprint $table) {
            $table->string('posisi', 20)->nullable()->default(null)->change();
        });

        // Yang masih memegang posisi tingkat 1 dikosongkan: gerak itu sekarang
        // milik sasarannya, dan rekomendasinya memang belum bergerak.
        DB::table('rekomendasis')
            ->whereIn('posisi', array_merge(self::TINGKAT_1, array_keys(self::GANTI_NAMA)))
            ->update(['posisi' => null]);
    }

    public function down(): void
    {
        Schema::table('rekomendasis', function (Blueprint $table) {
            $table->foreignId('satker_id')->nullable()->after('kunci_angsur')
                  ->constrained('satkers')->nullOnDelete();
            $table->foreignId('bentuk_id')->nullable()->after('uraian')
                  ->constrained('referensis')->nullOnDelete();
        });

        // Turun tangga hanya bisa mengembalikan sasaran pertama tiap
        // rekomendasi. Bentuk lamanya memang tidak sanggup menyimpan sisanya.
        DB::table('sasarans')
            ->join('tindakans', 'sasarans.tindakan_id', '=', 'tindakans.id')
            ->select('tindakans.rekomendasi_id', 'tindakans.bentuk_id',
                     'sasarans.satker_id', 'sasarans.posisi', 'sasarans.id')
            ->orderBy('sasarans.id')
            ->get()
            ->groupBy('rekomendasi_id')
            ->each(function ($baris, $rekId) {
                $x = $baris->first();
                DB::table('rekomendasis')->where('id', $rekId)->update([
                    'satker_id' => $x->satker_id,
                    'bentuk_id' => $x->bentuk_id,
                    'posisi'    => DB::raw("COALESCE(posisi, ".DB::getPdo()->quote($x->posisi).")"),
                ]);
            });

        DB::table('rekomendasis')->whereNull('posisi')->update(['posisi' => 'satker']);

        Schema::table('rekomendasis', function (Blueprint $table) {
            $table->string('posisi', 20)->default('satker')->nullable(false)->change();
        });

        DB::table('sasarans')->delete();
        DB::table('tindakans')->delete();
    }
};
