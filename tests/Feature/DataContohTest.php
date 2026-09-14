<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Models\Laporan;
use App\Models\Rekomendasi;
use Database\Seeders\BanyakLaporanSeeder;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga kewarasan data contoh.
 *
 * Data contoh yang tidak masuk akal membuat seluruh tampilan di atasnya ikut
 * tidak bisa dipercaya — dan yang paling berbahaya, salahnya tidak kelihatan
 * sampai ada orang yang membaca angkanya sungguh-sungguh. Uji ini memeriksa
 * hal-hal yang mustahil terjadi di dunia nyata.
 */
class DataContohTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class, BanyakLaporanSeeder::class]);
    }

    public function test_skalanya_cukup_untuk_uji_beban(): void
    {
        $this->assertGreaterThanOrEqual(18, Laporan::count());
        $this->assertGreaterThanOrEqual(80, Rekomendasi::count());
    }

    public function test_tidak_ada_tanggal_di_masa_depan(): void
    {
        foreach (Laporan::all() as $l) {
            $this->assertFalse($l->tgl_surat->isFuture(), "{$l->nomor}: tanggal surat di masa depan");
            $this->assertFalse($l->tgl_terima->isFuture(), "{$l->nomor}: tanggal terima di masa depan");
            $this->assertFalse($l->dicatat_pada->isFuture(), "{$l->nomor}: dicatat di masa depan");
        }
    }

    public function test_urutan_tanggal_laporan_masuk_akal(): void
    {
        foreach (Laporan::all() as $l) {
            $this->assertTrue($l->tgl_surat->lessThanOrEqualTo($l->tgl_terima),
                "{$l->nomor}: surat diterima sebelum suratnya dibuat");
            $this->assertTrue($l->tgl_terima->lessThanOrEqualTo($l->dicatat_pada),
                "{$l->nomor}: dicatat sebelum suratnya diterima");
        }
    }

    /* Sumbu status hanya berarti kalau ia benar-benar hanya berubah lewat
       surat bernomor. Satu saja yang lolos tanpa surat, seluruh jaminan itu
       batal. */
    public function test_status_selain_bt_wajib_punya_surat_verifikasi(): void
    {
        $tanpa = Rekomendasi::with('keputusan')->get()
            ->filter(fn ($r) => $r->status !== StatusTindakLanjut::BT && $r->keputusan->isEmpty());

        $this->assertTrue($tanpa->isEmpty(),
            'berstatus tanpa surat: ' . $tanpa->pluck('kode')->join(', '));
    }

    public function test_hasil_bs_selalu_diberi_tenggat_baru(): void
    {
        $r = \App\Models\KeputusanVerifikasi::where('hasil', 'BS')->whereNull('tenggat_baru')->get();

        $this->assertTrue($r->isEmpty(),
            'keputusan BS tanpa tenggat baru: ' . $r->pluck('id')->join(', '));
    }

    public function test_setoran_tidak_melebihi_tagihan(): void
    {
        $lebih = Rekomendasi::with('pemulihan')->get()
            ->filter(fn ($r) => $r->nilaiTerpulihkan() > $r->nilai_pulih);

        $this->assertTrue($lebih->isEmpty(),
            'setoran melebihi tagihan: ' . $lebih->pluck('kode')->join(', '));
    }

    /* Berkas tidak bisa berada di luar satuan kerja tanpa bukti apa pun —
       tidak ada yang bisa diteruskan kalau isinya kosong sama sekali.
       Buktinya tidak harus berupa tanggapan tertulis: ada rekomendasi yang
       diselesaikan dengan menyetor uangnya, ada yang cukup mengunggah
       berkasnya. */
    public function test_berkas_yang_sudah_bergerak_punya_isi(): void
    {
        $kosong = Rekomendasi::with('tindakLanjut', 'pemulihan', 'lampiran')->get()
            ->filter(fn ($r) => $r->posisiTampil() !== PosisiBerkas::SATKER
                && $r->tindakLanjut->isEmpty()
                && $r->pemulihan->isEmpty()
                && $r->lampiran->isEmpty());

        $this->assertTrue($kosong->isEmpty(),
            'sudah bergerak tanpa bukti apa pun: ' . $kosong->pluck('kode')->join(', '));
    }

    public function test_riwayat_berkas_maju_terus(): void
    {
        foreach (Rekomendasi::with('riwayat')->get() as $r) {
            $sebelum = null;
            foreach ($r->riwayat as $j) {
                if ($sebelum) {
                    $this->assertTrue($j->waktu->greaterThanOrEqualTo($sebelum),
                        "{$r->kode}: riwayat mundur di {$j->waktu}");
                }
                $sebelum = $j->waktu;
            }
        }
    }

    /* Kalau setiap laporan hanya memeriksa satu satuan kerja, seluruh alasan
       memindahkan satker ke temuan jadi tidak terperagakan. */
    public function test_ada_laporan_yang_memeriksa_beberapa_satuan_kerja(): void
    {
        $banyak = Laporan::with('temuan.satkers')->get()
            ->filter(fn ($l) => $l->satkerDiperiksa()->count() > 1);

        $this->assertGreaterThanOrEqual(5, $banyak->count(),
            'terlalu sedikit laporan yang memeriksa lebih dari satu satuan kerja');
    }

    public function test_ada_rekomendasi_yang_penanggungnya_bukan_yang_diperiksa(): void
    {
        $beda = Rekomendasi::with('temuan')->get()
            ->filter(function ($r) {
                /* Yang menanggung perbaikan tidak selalu yang diperiksa.
                   Sekarang keduanya jamak, jadi yang dicari adalah rekomendasi
                   yang punya sasaran di luar daftar satuan kerja temuannya. */
                $diperiksa = $r->temuan->satkers->pluck('id');
                return $r->daftarSasaran()->contains(
                    fn ($x) => $x->satker_id && ! $diperiksa->contains($x->satker_id));
            });

        $this->assertGreaterThan(0, $beda->count(),
            'tidak ada satu pun rekomendasi yang penanggungnya berbeda dari satker terperiksa');
    }

    /* Semua sembilan posisi terisi: kalau ada yang kosong, layar untuk peran
       itu tidak pernah bisa dilihat isinya saat memperagakan sistem. */
    public function test_semua_posisi_terisi(): void
    {
        /* Posisi tingkat 1 ada di sasaran, tingkat 2 di rekomendasi.
           Keduanya digabung: yang diuji adalah "semua posisi terpakai", dan
           posisi memang tersimpan di dua tempat sekarang. */
        $ada = \App\Models\Sasaran::pluck('posisi')
            ->merge(Rekomendasi::whereNotNull('posisi')->pluck('posisi'))
            ->map(fn ($p) => $p instanceof PosisiBerkas ? $p->value : $p)
            ->unique();

        foreach (PosisiBerkas::cases() as $p) {
            $this->assertTrue($ada->contains($p->value), "posisi {$p->value} kosong di data contoh");
        }
    }
}
