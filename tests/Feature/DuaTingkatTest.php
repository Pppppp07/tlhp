<?php

namespace Tests\Feature;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\Verifikasi;
use App\Models\Temuan;
use App\Models\Tindakan;
use App\Models\User;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga bentuk baru: satu rekomendasi, banyak satuan kerja, dua tingkat gerak.
 *
 * Yang diuji di sini adalah hal-hal yang tidak bisa ditegakkan constraint basis
 * data, dan yang kalau salah tidak melempar galat apa pun — hanya menghasilkan
 * angka yang meyakinkan dan keliru.
 */
class DuaTingkatTest extends TestCase
{
    use RefreshDatabase;

    private Rekomendasi $rek;
    private Sasaran $medan;
    private Sasaran $politeknik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            \Database\Seeders\DataMasterSeeder::class,
            \Database\Seeders\ContohLaporanSeeder::class,
        ]);

        /* Rekomendasi buatan yang dipikul dua satuan kerja — data contoh
           menghasilkannya secara acak, dan uji tidak boleh bergantung pada
           lemparan dadu. */
        $lap = Laporan::first();
        $a = Satker::where('kode', 'BPK-BDG')->firstOrFail();
        $b = Satker::where('kode', 'BPK-MKS')->firstOrFail();

        $tem = Temuan::create([
            'laporan_id' => $lap->id,
            'kode' => 'TMN-UJI-001',
            'judul' => 'Kelebihan pembayaran pada dua satuan kerja',
            'nilai' => 300_000_000,
        ]);
        $tem->satkers()->attach([$a->id, $b->id]);

        $this->rek = Rekomendasi::create([
            'temuan_id' => $tem->id,
            'kode' => 'REK-UJI-001.1',
            'nomor_urut' => 1,
            'uraian' => 'Menagih dan menyetorkan kelebihan pembayaran ke kas negara.',
            'nilai_pulih' => 300_000_000,
            'tenggat_jawab' => now()->addDays(30)->toDateString(),
            'status' => StatusTindakLanjut::BT->value,
            'posisi' => null,
        ]);

        $tindakan = Tindakan::create(['rekomendasi_id' => $this->rek->id, 'urutan' => 1]);

        $this->medan = Sasaran::create([
            'tindakan_id' => $tindakan->id, 'satker_id' => $a->id,
            'nilai' => 200_000_000, 'posisi' => PosisiBerkas::SATKER->value,
        ]);
        $this->politeknik = Sasaran::create([
            'tindakan_id' => $tindakan->id, 'satker_id' => $b->id,
            'nilai' => 100_000_000, 'posisi' => PosisiBerkas::SATKER->value,
        ]);
    }

    private function akun(string $surel): User
    {
        return User::where('email', $surel)->firstOrFail();
    }

    /* ================================================================
       TINGKAT 1 — tiap satuan kerja berjalan sendiri
       ================================================================ */

    public function test_berkas_tiap_satuan_kerja_berjalan_sendiri(): void
    {
        $this->medan->update(['posisi' => PosisiBerkas::INSPEKTORAT->value]);

        $this->assertSame(PosisiBerkas::INSPEKTORAT, $this->medan->refresh()->posisi);
        $this->assertSame(PosisiBerkas::SATKER, $this->politeknik->refresh()->posisi,
            'memindahkan satu baris tidak boleh menyeret baris satuan kerja lain');
    }

    /* Yang disebut adalah sasaran yang paling tertinggal: itu yang menentukan
       rekomendasinya masih jauh atau tinggal sedikit. Menyebut yang paling maju
       membuat rekomendasi yang separuh satkernya belum mulai tampak hampir
       selesai. */
    public function test_posisi_rekomendasi_mengikuti_yang_paling_tertinggal(): void
    {
        $this->medan->update(['posisi' => PosisiBerkas::INSPEKTORAT->value]);

        $this->assertSame(PosisiBerkas::SATKER, $this->rek->fresh()->posisiTampil());
    }

    public function test_kolom_posisi_rekomendasi_kosong_selama_tingkat_satu(): void
    {
        $this->assertNull($this->rek->fresh()->posisi,
            'gerak tingkat 1 tidak boleh menyentuh kolom posisi rekomendasi');
    }

    /* ================================================================
       GERBANG TINGKAT 2
       ================================================================ */

    public function test_tingkat_dua_tertutup_selama_ada_satu_yang_belum_tuntas(): void
    {
        $this->medan->update([
            'posisi' => PosisiBerkas::TUNTAS->value, 'hasil' => HasilTelaah::M->value,
        ]);

        $this->assertFalse($this->rek->fresh()->bolehMasukTingkat2(),
            'satu satuan kerja yang belum tuntas menutup gerbangnya');
    }

    public function test_tingkat_dua_terbuka_setelah_semuanya_tuntas(): void
    {
        foreach ([$this->medan, $this->politeknik] as $s) {
            $s->update(['posisi' => PosisiBerkas::TUNTAS->value, 'hasil' => HasilTelaah::M->value]);
        }

        $r = $this->rek->fresh();
        $this->assertTrue($r->semuaTuntas());
        $this->assertTrue($r->bolehMasukTingkat2());
    }

    /* Gerbangnya dibuka surat CHV, bukan sistem. Rekomendasi yang seluruh
       satkernya tuntas tetap belum bergerak sampai suratnya dicatat — tanpa
       surat, tidak ada dasar hukum untuk menyebutnya memadai. */
    public function test_semua_tuntas_belum_memindahkan_apa_pun(): void
    {
        foreach ([$this->medan, $this->politeknik] as $s) {
            $s->update(['posisi' => PosisiBerkas::TUNTAS->value, 'hasil' => HasilTelaah::M->value]);
        }

        $this->assertNull($this->rek->fresh()->posisi,
            'yang memindahkan ke SIPTL adalah surat CHV, bukan tuntasnya satuan kerja');
    }

    /* CHV terbit dari panel verifikasi Inspektorat, bukan dari layar
       tersendiri milik Setba. Peta tindakan prototipe menyebutnya apa adanya:
       Setba meneruskan, mengunggah, dan menyalin status BPK - tidak ada CHV
       di sana. */
    public function test_surat_chv_diterbitkan_inspektorat_dan_memindahkan_rekomendasinya(): void
    {
        // Satu satuan kerja sudah memadai; yang satunya sedang diverifikasi.
        $this->medan->update(['posisi' => PosisiBerkas::TUNTAS->value, 'hasil' => HasilTelaah::M->value]);
        $this->politeknik->update(['posisi' => PosisiBerkas::INSPEKTORAT->value]);

        $this->actingAs($this->akun('inspektorat@contoh.test'))
            ->post(route('sasaran.telaah', $this->politeknik), [
                'hasil' => 'M',
                'catatan' => 'Seluruh kewajiban terpenuhi, tidak ada yang menggantung.',
                'nomor_surat' => 'UJI-CHV/2026/001',
                'tgl_surat' => now()->toDateString(),
                'periode' => 'Triwulan III 2026',
            ])->assertRedirect();

        $surat = Verifikasi::where('nomor_surat', 'UJI-CHV/2026/001')->firstOrFail();
        $this->assertSame(Verifikasi::CHV, $surat->jenis);

        $r = $this->rek->fresh();
        $this->assertSame(HasilTelaah::M, $r->putusan());
        $this->assertSame(PosisiBerkas::SIPTL, $r->posisi,
            'CHV memadai membuka gerbang tingkat 2');
    }

    /* Putusan memadai selalu membawa nomor surat. Tanpa suratnya tidak ada
       dasar hukum untuk menyebutnya memadai - yang tersisa cuma centang di
       layar. */
    public function test_verifikasi_memadai_tanpa_nomor_surat_ditolak(): void
    {
        $this->politeknik->update(['posisi' => PosisiBerkas::INSPEKTORAT->value]);

        $this->actingAs($this->akun('inspektorat@contoh.test'))
            ->post(route('sasaran.telaah', $this->politeknik), [
                'hasil' => 'M',
                'catatan' => 'Seluruh kewajiban terpenuhi.',
            ])->assertSessionHasErrors('nomor_surat');

        $this->assertNull($this->politeknik->refresh()->hasil);
    }

    /* Kata Pak Iwan: "kalau di saat 3 satker itu belum beres, dia dianggap
       belum memadai semua." Suratnya boleh terbit, tapi bunyinya belum
       memadai - dan rekomendasinya tidak bergerak. */
    public function test_surat_berbunyi_belum_memadai_kalau_ada_satker_yang_belum(): void
    {
        // Medan belum ditandai sama sekali.
        $this->medan->update(['posisi' => PosisiBerkas::SATKER->value, 'hasil' => null]);
        $this->politeknik->update(['posisi' => PosisiBerkas::INSPEKTORAT->value]);

        $this->actingAs($this->akun('inspektorat@contoh.test'))
            ->post(route('sasaran.telaah', $this->politeknik), [
                'hasil' => 'M',
                'catatan' => 'Bagian Politeknik PU sudah lengkap seluruhnya.',
                'nomor_surat' => 'UJI-CHV/2026/002',
                'tgl_surat' => now()->toDateString(),
            ])->assertRedirect();

        $r = $this->rek->fresh();

        $this->assertSame(HasilTelaah::BM, $r->putusan(),
            'satu satuan kerja yang belum beres membuat seluruh rekomendasi belum memadai');
        $this->assertNull($r->posisi, 'rekomendasinya tidak boleh bergerak');

        // Tanda per baris tetap jujur: bagiannya sendiri memang memadai.
        $this->assertSame(HasilTelaah::M, $this->politeknik->refresh()->hasil);
    }

    public function test_pengakuan_membuat_surat_yang_janggal_tetap_tercatat(): void
    {
        $this->medan->update(['posisi' => PosisiBerkas::SATKER->value, 'hasil' => null]);
        $this->politeknik->update(['posisi' => PosisiBerkas::INSPEKTORAT->value]);

        $this->actingAs($this->akun('inspektorat@contoh.test'))
            ->post(route('sasaran.telaah', $this->politeknik), [
                'hasil' => 'M',
                'catatan' => 'Suratnya tetap terbit atas pertimbangan pimpinan.',
                'nomor_surat' => 'UJI-CHV/2026/003',
                'tgl_surat' => now()->toDateString(),
                'diakui' => 1,
            ])->assertRedirect();

        $k = $this->rek->fresh()->keputusan->last();
        $this->assertTrue($k->diakui_masih_terbuka,
            'kejanggalannya harus tercatat, bukan hilang');
    }

    /* Setba tidak punya urusan CHV sama sekali - layarnya memang tidak ada. */
    public function test_setba_tidak_punya_layar_chv(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('surat.form'),
            'rute layar CHV milik Setba seharusnya sudah tidak ada');
    }

    /* ================================================================
       DUA SUMBU PENILAIAN
       ================================================================ */

    public function test_telaah_menandai_satu_baris_saja(): void
    {
        $this->medan->update(['posisi' => PosisiBerkas::INSPEKTORAT->value]);

        $this->actingAs($this->akun('inspektorat@contoh.test'))
            ->post(route('sasaran.telaah', $this->medan), [
                'hasil' => 'M',
                'catatan' => 'Bukti setor lengkap dan cocok dengan nilai bagiannya.',
                'nomor_surat' => 'UJI-CHV/2026/010',
                'tgl_surat' => now()->toDateString(),
            ])->assertRedirect();

        $this->assertSame(HasilTelaah::M, $this->medan->refresh()->hasil);
        $this->assertNull($this->politeknik->refresh()->hasil,
            'tanda memadai satu satuan kerja tidak boleh menular ke yang lain');
    }

    public function test_telaah_tidak_menyentuh_status_bpk(): void
    {
        $this->medan->update(['posisi' => PosisiBerkas::INSPEKTORAT->value]);

        $this->actingAs($this->akun('inspektorat@contoh.test'))
            ->post(route('sasaran.telaah', $this->medan), [
                'hasil' => 'M', 'catatan' => 'Bukti setor sudah lengkap semuanya.',
                'nomor_surat' => 'UJI-CHV/2026/011',
                'tgl_surat' => now()->toDateString(),
            ]);

        $this->assertSame(StatusTindakLanjut::BT, $this->rek->fresh()->status,
            'status BPK hanya berubah lewat SIPTL, bukan lewat telaah');
    }

    public function test_status_bpk_disalin_dari_siptl(): void
    {
        $this->rek->update(['posisi' => PosisiBerkas::BPK->value]);

        $this->actingAs($this->akun('setba@contoh.test'))
            ->post(route('rekomendasi.bpk', $this->rek), [
                'hasil' => 'SS', 'catatan' => 'Seluruh kelebihan pembayaran sudah disetor.',
            ])->assertRedirect();

        $r = $this->rek->fresh();
        $this->assertSame(StatusTindakLanjut::SS, $r->status);
        $this->assertSame(StatusTindakLanjut::SS, $r->siptl_status);
        $this->assertSame(PosisiBerkas::SELESAI, $r->posisi);
        $this->assertNotNull($r->siptl_dicatat_pada);
    }

    /* ================================================================
       KERAHASIAAN — satuan kerja hanya melihat barisnya
       ================================================================ */

    public function test_satuan_kerja_hanya_melihat_barisnya_sendiri(): void
    {
        $bandung = User::where('email', 'bandung@contoh.test')->firstOrFail();
        $terlihat = Terlihat::untuk($bandung);

        $r = Rekomendasi::with('sasaran.satker')->find($this->rek->id);
        $terlihat->pangkasRekomendasi($r);

        $this->assertCount(1, $r->daftarSasaran());
        $this->assertSame($this->medan->id, $r->daftarSasaran()->first()->id);
    }

    /* Angka utuhnya menyebut berapa besar beban satuan kerja sebelah — dan itu
       bukan urusannya, walau rekomendasinya satu. */
    public function test_nilai_dikecilkan_jadi_bagian_pembacanya(): void
    {
        $bandung = User::where('email', 'bandung@contoh.test')->firstOrFail();

        $r = Rekomendasi::with('sasaran.satker')->find($this->rek->id);
        Terlihat::untuk($bandung)->pangkasRekomendasi($r);

        $this->assertSame(200_000_000, (int) $r->nilai_pulih,
            'satuan kerja tidak boleh membaca tagihan satuan kerja lain');
    }

    public function test_satuan_kerja_ditolak_menyentuh_baris_satker_lain(): void
    {
        $bandung = User::where('email', 'bandung@contoh.test')->firstOrFail();

        $this->actingAs($bandung)
            ->post(route('tanggapan.simpan', $this->politeknik), [
                'uraian' => 'Mencoba mengisi bagian satuan kerja lain.',
                'tanggal' => now()->toDateString(),
            ])->assertForbidden();
    }

    /* ================================================================
       KESELARASAN ANGKA
       ================================================================ */

    public function test_jumlah_nilai_sasaran_sama_dengan_tagihan_rekomendasi(): void
    {
        $this->assertTrue($this->rek->fresh()->nilaiSelaras());

        $meleset = Rekomendasi::with('sasaran')->get()
            ->reject(fn ($r) => $r->nilaiSelaras())
            ->map->kode->all();

        $this->assertSame([], $meleset,
            'jumlah nilai sasaran harus sama dengan nilai_pulih rekomendasinya');
    }

    /* Kelunasan adalah TANDA, bukan penguncian. Pemulihan dana bisa memakan
       bertahun-tahun; menahan berkasnya sampai lunas berarti tidak ada yang
       bisa memeriksa kemajuannya selama itu. */
    public function test_berkas_boleh_dikirim_walau_belum_lunas(): void
    {
        $bandung = User::where('email', 'bandung@contoh.test')->firstOrFail();

        $this->assertFalse($this->medan->lunas());

        $this->actingAs($bandung)
            ->post(route('tanggapan.simpan', $this->medan), [
                'uraian' => 'Sudah menagih ke penyedia, setoran tahap pertama menyusul.',
                'tanggal' => now()->toDateString(),
                'kirim' => 1,
            ])->assertRedirect();

        $this->assertSame(PosisiBerkas::SETBA_TINJAU, $this->medan->refresh()->posisi);
    }
    /**
     * Setba tidak punya wewenang mengembalikan berkas.
     *
     * Kata Mas Naufal di rapat, "Kita nggak punya hak untuk menolak"; kata Mbak
     * Puspi, "untuk ngecek kebenaran dokumennya bukan di kita. Kita cuma ada
     * apa nggak."
     *
     * Yang boleh Setba cuma meminta dokumen yang memang belum ada. Menilai
     * benar-tidaknya isi dokumen wewenang UKI dan Inspektorat.
     */
    public function test_setba_tidak_bisa_mengembalikan_berkas(): void
    {
        $this->medan->update(['posisi' => PosisiBerkas::SETBA_TINJAU->value]);

        $setba = User::where('peran', PeranPengguna::SETBA)->firstOrFail();

        $this->actingAs($setba)
            ->post(route('sasaran.kembalikan', $this->medan), [
                'alasan' => 'Buktinya menurut saya kurang meyakinkan.',
            ])->assertForbidden();

        $this->assertSame(PosisiBerkas::SETBA_TINJAU, $this->medan->fresh()->posisi);

        // dan tombolnya memang tidak ada di halamannya
        $isi = $this->actingAs($setba)
            ->get(route('rekomendasi.show', $this->rek))->assertOk()->getContent();
        $this->assertStringNotContainsString('Alasan pengembalian', $isi);

        /* Yang boleh dilakukannya tetap ada: meminta dokumen yang belum ada.
           Itu juga memundurkan berkasnya, tapi karena kurang — bukan karena
           dinilai salah. */
        $this->assertStringContainsString('Minta dokumen tambahan', $isi);
    }

    public function test_uki_tetap_bisa_mengembalikan(): void
    {
        $this->medan->update(['posisi' => PosisiBerkas::UKI->value]);

        $uki = User::where('peran', PeranPengguna::UKI)->firstOrFail();

        $this->actingAs($uki)
            ->post(route('sasaran.kembalikan', $this->medan), [
                'alasan' => 'Berita acara belum ditandatangani pengelola BMN.',
            ])->assertRedirect();

        $this->assertSame(PosisiBerkas::SATKER, $this->medan->fresh()->posisi);
    }

}
