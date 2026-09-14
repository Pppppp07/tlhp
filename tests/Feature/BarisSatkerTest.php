<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Models\User;
use App\Support\Terlihat;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rincian tindak lanjut yang dibentangkan di dalam barisnya.
 *
 * Sejak dokumen, pemulihan nilai, dan laporan satuan kerja pindah dari kartu
 * rangkuman ke dalam baris tiap satuan kerja, halaman ini menggelar keterangan
 * per satuan kerja di satu tabel. Dua hal harus dijaga:
 *
 *   1. Satuan kerja tidak melihat baris satuan kerja lain — termasuk namanya.
 *   2. Catatan yang belum bertanda satuan kerja tidak hilang dari halaman.
 */
class BarisSatkerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
        /* Langkah yang sama yang dijalankan DatabaseSeeder sesudah menyemai.
           Tanpa ini catatan tindak lanjut tidak punya barisnya, dan uji ini
           justru menguji keadaan yang tidak pernah terjadi. */
        \App\Support\PautkanSasaran::jalankan();
    }

    public function test_satuan_kerja_tidak_melihat_baris_satuan_kerja_lain(): void
    {
        $diperiksa = 0;

        foreach (User::where('peran', PeranPengguna::SATKER)->get() as $u) {
            $milik = $u->satker->namaPendek();
            /* "Sekretariat Badan" adalah nama satuan kerja SEKALIGUS sebutan
               peran Setba. Kalimat "bola ada di tangan Sekretariat Badan"
               menyebut sekretariatnya, bukan satuan kerjanya — jadi nama itu
               tidak bisa dipakai menguji kebocoran. Dikecualikan di sini, dan
               nama satuan kerja lain tetap diperiksa penuh. */
            $sebutanPeran = collect(PeranPengguna::cases())
                ->map(fn ($p) => $p->nama())->all();

            $lain = Satker::where('id', '!=', $u->satker_id)->get()
                ->map(fn ($s) => $s->namaPendek())
                ->reject(fn ($n) => $n === $milik || in_array($n, $sebutanPeran, true))
                ->values();

            foreach (Rekomendasi::all() as $rek) {
                $this->actingAs($u);
                if (! Terlihat::untuk($u)->bolehLihatRekomendasi($rek)) {
                    continue;
                }

                $isi = $this->actingAs($u)->get(route('rekomendasi.show', $rek))
                    ->assertOk()->getContent();

                /* Hanya badan halaman. Pemilih peran di kepala memang memuat
                   seluruh satuan kerja, dan itu memang begitu rancangannya. */
                $badan = $this->badan($isi);
                $diperiksa++;

                foreach ($lain as $nama) {
                    $pos = strpos($badan, $nama);
                    if ($pos !== false) {
                        $cuplik = preg_replace('/\s+/', ' ',
                            substr($badan, max(0, $pos - 260), 420));
                        $this->fail("{$milik} melihat nama {$nama} pada rekomendasi "
                            . "{$rek->id}:
{$cuplik}");
                    }
                }
                $this->assertTrue(true);
            }
        }

        $this->assertGreaterThan(0, $diperiksa, 'tidak ada halaman yang teruji');
    }

    /**
     * Halaman rincian laporan disapu dengan aturan yang sama.
     *
     * Di sinilah blok temuan menggelar tabel rekomendasi berikut rincian yang
     * dibentangkan — jalur yang sama, tapi lewat pintu yang berbeda.
     */
    public function test_rincian_laporan_tidak_menyebut_satuan_kerja_lain(): void
    {
        $sebutanPeran = collect(PeranPengguna::cases())->map(fn ($p) => $p->nama())->all();
        $diperiksa = 0;

        foreach (User::where('peran', PeranPengguna::SATKER)->get() as $u) {
            $milik = $u->satker->namaPendek();
            $lain = Satker::where('id', '!=', $u->satker_id)->get()
                ->map(fn ($s) => $s->namaPendek())
                ->reject(fn ($n) => $n === $milik || in_array($n, $sebutanPeran, true))
                ->values();

            foreach (\App\Models\Laporan::all() as $lap) {
                if (! Terlihat::untuk($u)->bolehLihatLaporan($lap)) {
                    continue;
                }

                $badan = $this->badan(
                    $this->actingAs($u)->get(route('laporan.show', $lap))
                        ->assertOk()->getContent()
                );
                $diperiksa++;

                foreach ($lain as $nama) {
                    $pos = strpos($badan, $nama);
                    if ($pos !== false) {
                        $cuplik = preg_replace('/\s+/', ' ',
                            substr($badan, max(0, $pos - 260), 420));
                        $this->fail("{$milik} melihat nama {$nama} pada laporan "
                            . "{$lap->nomor}:
{$cuplik}");
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $diperiksa, 'tidak ada laporan yang teruji');
    }

    public function test_catatan_tanpa_sasaran_tetap_tampil(): void
    {
        /* Keadaannya dibuat sendiri, tidak ditunggu muncul di data contoh:
           yang diuji justru penanganan catatan yang TIDAK bisa dipastikan
           barisnya, dan itu keadaan yang jarang — uji yang melewati diri
           sendiri saat datanya kebetulan rapi tidak menjaga apa pun. */
        $r = Rekomendasi::whereHas('permintaanDokumen')->get()
            ->first(fn ($x) => $x->permintaanDokumen->isNotEmpty());
        $this->assertNotNull($r, 'data contoh tidak punya permintaan dokumen sama sekali');

        $r->permintaanDokumen->first()->update(['sasaran_id' => null]);
        $r->refresh();

        $setba = User::where('peran', PeranPengguna::SETBA)->firstOrFail();
        $isi = $this->actingAs($setba)->get(route('rekomendasi.show', $r))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Belum bertanda satuan kerja', $isi);

        foreach ($r->permintaanDokumen->whereNull('sasaran_id')->flatMap->item as $it) {
            $this->assertStringContainsString(e($it->nama), $isi,
                "butir dokumen \"{$it->nama}\" hilang dari halaman");
        }
    }

    public function test_baris_memuat_rincian_satuan_kerjanya(): void
    {
        $r = Rekomendasi::whereHas('tanggapan', fn ($q) => $q->whereNotNull('sasaran_id'))
            ->first();
        $this->assertNotNull($r, 'data contoh tidak punya laporan bertanda sasaran');

        $setba = User::where('peran', PeranPengguna::SETBA)->firstOrFail();
        $isi = $this->actingAs($setba)->get(route('rekomendasi.show', $r))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Dilaporkan', $isi);

        foreach ($r->tanggapan->whereNotNull('sasaran_id') as $t) {
            $this->assertStringContainsString(e($t->uraian), $isi,
                'laporan satuan kerja hilang sesudah pindah ke dalam barisnya');
        }
    }

    /**
     * Kolom tabel menyebut KEADAAN berkasnya, bukan nama unitnya.
     *
     * "Setba" tidak menjawab apa yang sedang terjadi: berkas di mejanya bisa
     * berarti tanggapan perlu ditinjau atau hasil telaah perlu diteruskan, dan
     * keduanya pekerjaan yang berbeda oleh orang yang sama.
     */
    public function test_kolom_posisi_menyebut_keadaan_bukan_nama_unit(): void
    {
        $s = \App\Models\Sasaran::whereNotNull('satker_id')->firstOrFail();
        $s->update(['posisi' => \App\Enums\PosisiBerkas::SETBA_TERUSKAN->value]);

        $setba = User::where('peran', PeranPengguna::SETBA)->firstOrFail();
        $isi = $this->actingAs($setba)
            ->get(route('rekomendasi.show', $s->rekomendasi()))->assertOk()->getContent();

        $this->assertStringContainsString('Hasil telaah UKI perlu diteruskan', $isi);
    }

    /**
     * Kolom Status menulis kodenya, nama panjangnya di gelembung judul.
     *
     * Kolomnya sempit; "Belum memadai" memaksa tabelnya melar. Yang belum
     * ditelaah tetap dibedakan dari yang belum memadai — menuliskan "BM" pada
     * baris yang belum pernah dilihat siapa pun berarti mengaku Inspektorat
     * sudah menilainya kurang.
     */
    public function test_kolom_status_ringkas_dan_membedakan_yang_belum_ditelaah(): void
    {
        $s = \App\Models\Sasaran::whereNotNull('satker_id')->firstOrFail();
        $rek = $s->rekomendasi();
        $setba = User::where('peran', PeranPengguna::SETBA)->firstOrFail();

        // belum ditelaah
        $s->update(['hasil' => null]);
        $this->actingAs($setba)->get(route('rekomendasi.show', $rek))->assertOk()
            ->assertSee('Belum ditelaah — belum ada yang menilainya', false);

        // sudah ditelaah: kodenya, dengan nama panjangnya di gelembung
        $s->update(['hasil' => \App\Enums\HasilTelaah::M->value]);
        $isi = $this->actingAs($setba)
            ->get(route('rekomendasi.show', $rek))->assertOk()->getContent();
        $this->assertStringContainsString('title="Memadai"', $isi);
    }

    /**
     * Tombol Aksi berbunyi menurut apa yang bisa dikerjakan peran ini di baris
     * ini — dan ia saklar sungguhan, bukan hiasan.
     */
    public function test_tombol_aksi_berbunyi_menurut_perannya(): void
    {
        $s = \App\Models\Sasaran::whereNotNull('satker_id')->firstOrFail();
        $rek = $s->rekomendasi();
        $setba = User::where('peran', PeranPengguna::SETBA)->firstOrFail();

        /* Isinya dibaca dari sel Aksi-nya sendiri, bukan dari seluruh halaman:
           kata "Lihat" dan "Kerjakan" juga muncul di keterangan lain, dan
           mencarinya di seluruh halaman membuat ujinya lulus karena hal yang
           salah. */
        $aksi = function () use ($setba, $rek, $s) {
            $isi = $this->actingAs($setba)
                ->get(route('rekomendasi.show', $rek))->assertOk()->getContent();
            preg_match('/data-aksi="s'.$s->id.'"(.*?)<\/button>/s', $isi, $m);

            return trim(preg_replace('/\s+/', ' ', strip_tags($m[1] ?? '')));
        };

        // di meja Setba: Kerjakan
        $s->update(['posisi' => \App\Enums\PosisiBerkas::SETBA_TINJAU->value]);
        $this->assertStringContainsString('Kerjakan', $aksi());

        // bukan di mejanya: Lihat
        $s->update(['posisi' => \App\Enums\PosisiBerkas::UKI->value]);
        $this->assertStringContainsString('Lihat', $aksi());
    }

    /**
     * Tanpa skrip, rinciannya tetap terbaca.
     *
     * `hidden` yang dipasang di markup berarti halamannya kosong kalau skripnya
     * gagal dimuat — dan berkas skripnya sendiri menyatakan ia penambah, bukan
     * penopang.
     */
    public function test_rincian_baris_tidak_disembunyikan_di_markup(): void
    {
        $setba = User::where('peran', PeranPengguna::SETBA)->firstOrFail();
        $rek = Rekomendasi::first();

        $isi = $this->actingAs($setba)
            ->get(route('rekomendasi.show', $rek))->assertOk()->getContent();

        $this->assertStringContainsString('<tr class="lebar"', $isi);
        $this->assertDoesNotMatchRegularExpression('/<tr class="lebar"[^>]*\shidden/', $isi,
            'baris rincian disembunyikan di markup — tanpa skrip ia tidak pernah terbaca');
    }

    /** Badan halaman saja, tanpa kepala dan pemilih peran. */
    private function badan(string $html): string
    {
        $awal = strpos($html, '<div class="badan">');
        $this->assertNotFalse($awal, 'pembungkus badan halaman tidak ketemu');

        return substr($html, $awal);
    }
}
