<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom yang ada di prototipe tapi belum pernah dibuat di sini.
 *
 * Tiga di antaranya soal SIPTL. Selama ini yang tersimpan hanya kapan Setba
 * mengunggah; hasil yang keluar dari SIPTL — yang justru menjadi status resmi
 * rekomendasinya — tidak punya tempat sama sekali, dan Setba tidak punya
 * layar untuk mencatatnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekomendasis', function (Blueprint $table) {
            // Rujukan penomoran pada surat LHP aslinya, mis. "II.1.1.a".
            // Diketik ikut surat, bukan dihasilkan sistem.
            $table->string('ref_lhp', 40)->nullable()->after('kode');

            // Sifat temuan menurut LHP: informasi kerugian negara, kekurangan
            // volume, dan seterusnya. Dari daftar baku.
            $table->foreignId('sifat_id')->nullable()->after('uraian')
                  ->constrained('referensis')->nullOnDelete();

            // Hasil yang dibaca Setba dari SIPTL. Bukan hasil hitungan sistem:
            // BPK yang menetapkannya, dan Setba menyalinnya apa adanya.
            $table->string('siptl_status', 2)->nullable()->after('siptl_tanda_terima');
            $table->text('siptl_catatan')->nullable()->after('siptl_status');
            // Kapan Setba mencatatnya, beda dari kapan statusnya keluar.
            $table->date('siptl_dicatat_pada')->nullable()->after('siptl_catatan');
        });
    }

    public function down(): void
    {
        Schema::table('rekomendasis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sifat_id');
            $table->dropColumn(['ref_lhp', 'siptl_status', 'siptl_catatan', 'siptl_dicatat_pada']);
        });
    }
};
