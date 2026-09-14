<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\Sasaran;
use Database\Seeders\BanyakLaporanSeeder;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data contoh harus masuk akal, bukan sekadar terisi.
 *
 * Berkas tidak bisa dinilai memadai kalau satuan kerjanya tidak pernah
 * melaporkan apa pun, dan tidak ada yang bisa diteruskan kalau tidak ada yang
 * dikirim. Data contoh yang melanggar itu bukan cuma jelek dipandang — ia
 * membuat yang memperagakan sistem ini kehilangan kepercayaan pada angkanya,
 * dan pertanyaan pertama yang muncul selalu "kok bisa begitu?".
 *
 * Sebelum penjaga ini ada, 26 dari 94 baris sudah lepas dari meja satuan kerja
 * — sebagian bertanda memadai — tanpa satu pun laporan, berkas, atau telaah.
 * Sebabnya penyemai membuat jejaknya untuk baris pertama saja, sementara
 * posisinya diberikan ke semua baris.
 */
class KeutuhanDataContohTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            DataMasterSeeder::class,
            ContohLaporanSeeder::class,
            BanyakLaporanSeeder::class,
        ]);
    }

    /** Baris yang sudah lepas dari meja satuan kerjanya. */
    private function sudahBergerak()
    {
        return Sasaran::with('tanggapan', 'lampiran', 'telaah', 'pemulihan',
            'satker', 'tindakan')->get()
            ->reject(fn ($s) => $s->posisi === PosisiBerkas::SATKER);
    }

    public function test_yang_sudah_bergerak_punya_jejaknya_sendiri(): void
    {
        $kosong = $this->sudahBergerak()->reject(fn ($s) => $s->tanggapan->isNotEmpty()
            || $s->lampiran->isNotEmpty()
            || $s->telaah->isNotEmpty()
            || $s->pemulihan->isNotEmpty());

        $this->assertTrue($kosong->isEmpty(),
            'baris yang sudah lepas dari satuan kerja tanpa jejak apa pun: '
            . $kosong->map(fn ($s) => 'rek '.$s->tindakan->rekomendasi_id.' baris '.$s->id
                .' ('.($s->satker?->namaPendek() ?? '-').', '.$s->posisi->value.')')
                ->take(6)->join('; '));
    }

    /**
     * Tanda memadai tidak pernah muncul sendiri — ia lahir dari telaah.
     *
     * Baris bertanda memadai tanpa satu pun catatan penilaian membuat
     * pembacanya wajar bertanya dari mana tandanya datang.
     */
    public function test_tanda_hasil_selalu_punya_telaahnya(): void
    {
        $tanpa = $this->sudahBergerak()
            ->filter(fn ($s) => $s->hasil !== null && $s->telaah->isEmpty());

        $this->assertTrue($tanpa->isEmpty(),
            'baris bertanda hasil tanpa telaah: '
            . $tanpa->map(fn ($s) => 'rek '.$s->tindakan->rekomendasi_id.' baris '.$s->id)
                ->take(6)->join('; '));
    }

    /**
     * Laporan satuan kerja menyebut satuan kerjanya sendiri.
     *
     * Rekomendasi yang dipikul tiga satuan kerja punya tiga laporan terpisah;
     * laporan yang menggantung tanpa baris membuat ketiganya terbaca sebagai
     * satu daftar tanpa pemilik.
     */
    public function test_tiap_laporan_satuan_kerja_menyebut_barisnya(): void
    {
        $lepas = \App\Models\Tanggapan::whereNull('sasaran_id')
            ->whereNotNull('rekomendasi_id')->count();

        $this->assertSame(0, $lepas,
            "{$lepas} laporan satuan kerja tidak menyebut baris mana pun");
    }

    /**
     * Nilai rekomendasi selalu jumlah bagian tiap satuan kerjanya.
     *
     * Dua angka untuk satu hal cepat atau lambat berbeda, dan yang menemukannya
     * biasanya BPK.
     */
    public function test_nilai_rekomendasi_selaras_dengan_bagian_satuan_kerjanya(): void
    {
        $selisih = \App\Models\Rekomendasi::with('tindakan.sasaran')->get()
            ->reject(fn ($r) => (int) $r->nilai_pulih === (int) $r->daftarSasaran()->sum('nilai'));

        $this->assertTrue($selisih->isEmpty(),
            'nilai rekomendasi tidak selaras: '
            . $selisih->map(fn ($r) => "rek {$r->id} ({$r->nilai_pulih} vs "
                . $r->daftarSasaran()->sum('nilai').')')->take(6)->join('; '));
    }

    /**
     * Tiap bentuk tindak lanjut punya satuan kerjanya.
     *
     * Bentuk tanpa satu pun satuan kerja tidak pernah tampil di tabel — ia cuma
     * menambah baris di basis data tanpa mengubah apa pun di layar.
     */
    public function test_tiap_bentuk_tindak_lanjut_punya_satuan_kerjanya(): void
    {
        $kosong = \App\Models\Tindakan::with('sasaran')->get()
            ->filter(fn ($t) => $t->sasaran->isEmpty());

        $this->assertTrue($kosong->isEmpty(),
            'bentuk tindak lanjut tanpa satuan kerja: '
            . $kosong->map(fn ($t) => "tindakan {$t->id} pada rek {$t->rekomendasi_id}")
                ->take(6)->join('; '));
    }
}
