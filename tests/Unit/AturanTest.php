<?php

namespace Tests\Unit;

use App\Enums\HasilTelaah;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\Rekomendasi;
use App\Support\PetaData;
use App\Support\Tampil;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Aturan yang tidak menyentuh basis data: rangkuman status, posisi paling
 * belakang, hitungan tenggat, dan cara menulis angka.
 *
 * Semuanya disalin dari prototipe, jadi yang diuji di sini sekaligus menjaga
 * dua artefak itu tetap menyebut hal yang sama.
 */
class AturanTest extends TestCase
{
    /* ================= status BPK ================= */

    public function test_rangkuman_status_bpk_dimenangkan_yang_paling_belakang(): void
    {
        $s = fn (array $kode) => Rekomendasi::rangkumBpk(
            array_map(fn ($k) => $k ? StatusTindakLanjut::from($k) : null, $kode))->value;

        $this->assertSame('SS', $s(['SS', 'SS']));
        $this->assertSame('BS', $s(['SS', 'BS']));
        $this->assertSame('BT', $s(['SS', 'BT']));
        /* Baris yang belum diunggah terhitung BT — BPK belum melihat apa pun. */
        $this->assertSame('BT', $s(['SS', null]));
        /* TD tertutup seperti SS; rekomendasinya TD hanya kalau seluruhnya TD. */
        $this->assertSame('TD', $s(['TD', 'TD']));
        $this->assertSame('SS', $s(['TD', 'SS']));
        $this->assertSame('BT', $s([]));
    }

    /* ================= posisi ================= */

    public function test_posisi_paling_belakang_yang_menahan_seluruhnya(): void
    {
        $p = fn (array $kode) => PosisiBerkas::palingBelakang(
            array_map(fn ($k) => PosisiBerkas::from($k), $kode))->value;

        $this->assertSame('satker', $p(['satker', 'uki', 'tuntas']));
        $this->assertSame('uki', $p(['uki', 'inspektorat', 'tuntas']));
        $this->assertSame('tuntas', $p(['tuntas', 'tuntas']));
        /* Tanpa baris sama sekali: masih di satuan kerja, belum bergerak. */
        $this->assertSame('satker', PosisiBerkas::palingBelakang([])->value);
    }

    public function test_sebutan_posisi_menyebut_keadaan_bukan_nama_unit(): void
    {
        $this->assertSame('Menunggu tanggapan satuan kerja', PosisiBerkas::SATKER->label());
        $this->assertSame('Dikembalikan — menunggu dikirim ulang Setba', PosisiBerkas::SETBA_KEMBALI->label());
        $this->assertSame('Sudah selesai diperiksa', PosisiBerkas::TUNTAS->label());
    }

    /* ================= sebutan hasil ================= */

    public function test_sebutan_hasil_ikut_jenis_laporannya(): void
    {
        $this->assertSame('Memadai', HasilTelaah::M->nama(SumberLaporan::LHP));
        $this->assertSame('Sesuai', HasilTelaah::M->nama(SumberLaporan::LHA));
        $this->assertSame('Belum memadai', HasilTelaah::BM->nama(SumberLaporan::LHP));
        $this->assertSame('Belum sesuai', HasilTelaah::BM->nama(SumberLaporan::LHA));

        /* Singkatannya ikut sebutannya: "S", bukan "SS" — dua huruf itu milik
           BPK dan artinya lain. */
        $this->assertSame('M', HasilTelaah::M->kode(SumberLaporan::LHP));
        $this->assertSame('S', HasilTelaah::M->kode(SumberLaporan::LHA));
        $this->assertSame('BS', HasilTelaah::BM->kode(SumberLaporan::LHA));
    }

    /* ================= tenggat ================= */

    public function test_tenggat_lhp_hari_kalender_dan_lha_hari_kerja(): void
    {
        $mulai = Carbon::parse('2026-08-05');

        $this->assertSame('2026-10-04',
            Rekomendasi::hitungTenggat($mulai, SumberLaporan::LHP)->toDateString());

        /* 30 hari kerja dari Rabu 5 Agustus 2026 — akhir pekan dilewati. */
        $lha = Rekomendasi::hitungTenggat($mulai, SumberLaporan::LHA);
        $this->assertSame('2026-09-16', $lha->toDateString());
        $this->assertFalse($lha->isWeekend());
    }

    public function test_selisih_hari_dihitung_dari_tanggalnya_saja(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 23:30:00'));

        $this->assertSame(12, Rekomendasi::selisih(Carbon::parse('2026-08-05 01:00:00')));
        $this->assertSame(-3, Rekomendasi::selisih(Carbon::parse('2026-08-20')));
        $this->assertNull(Rekomendasi::selisih(null));

        Carbon::setTestNow();
    }

    /* ================= cara menulis ================= */

    public function test_rupiah_ditulis_sama_dengan_prototipe(): void
    {
        $this->assertSame('Rp 18.600.000', Tampil::rupiah(18600000));
        $this->assertSame('—', Tampil::rupiah(0));
        $this->assertSame('lunas', Tampil::rupiahSisa(0));

        $this->assertSame('Rp 0', Tampil::rupiahSingkat(0));
        $this->assertSame('Rp 24 jt', Tampil::rupiahSingkat(24000000));
        $this->assertSame('Rp 1.5 M', Tampil::rupiahSingkat(1500000000));
        $this->assertSame('Rp 2 M', Tampil::rupiahSingkat(2000000000));
        $this->assertSame('Rp 500.000', Tampil::rupiahSingkat(500000));
    }

    public function test_tanggal_memakai_singkatan_bulan_prototipe(): void
    {
        $this->assertSame('05 Agu 2026', Tampil::tgl('2026-08-05'));
        $this->assertSame('—', Tampil::tgl(null));
    }

    public function test_lama_telat_dipendekkan_begitu_angkanya_tak_terbayangkan(): void
    {
        $this->assertSame('lewat 12 hari', Tampil::lamaTelat(12));
        $this->assertSame('lewat 4 bulan', Tampil::lamaTelat(120));
        $this->assertSame('lewat setahun', Tampil::lamaTelat(400));
        $this->assertSame('lewat 3 tahun', Tampil::lamaTelat(1200));
    }

    /* ================= pita peta data ================= */

    public function test_pita_keterlambatan_dan_umur_berkas(): void
    {
        $this->assertSame('Tanpa tenggat', PetaData::pitaHari(null));
        $this->assertSame('Belum jatuh tempo', PetaData::pitaHari(-5));
        $this->assertSame('Jatuh tempo hari ini', PetaData::pitaHari(0));
        $this->assertSame('Lewat 1–30 hari', PetaData::pitaHari(30));
        $this->assertSame('Lewat 31–90 hari', PetaData::pitaHari(31));
        $this->assertSame('Lewat di atas 180 hari', PetaData::pitaHari(400));

        $this->assertSame('0–3 bulan', PetaData::pitaUmur(90));
        $this->assertSame('Lebih dari setahun', PetaData::pitaUmur(400));
        $this->assertSame('Tidak diketahui', PetaData::pitaUmur(null));
    }

    public function test_bagian_dihitung_bulat_dan_aman_terhadap_nol(): void
    {
        $this->assertSame('50%', PetaData::bagian(1, 2));
        $this->assertSame('0%', PetaData::bagian(3, 0));
        $this->assertSame('33%', PetaData::bagian(1, 3));
    }
}
