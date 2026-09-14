<?php

namespace Tests\Feature;

use App\Enums\JenisPermintaanUbah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusPermintaanUbah;
use App\Models\Lampiran;
use App\Models\PermintaanUbah;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\User;
use App\Models\Verifikasi;
use Database\Seeders\ContohLaporanSeeder;
use Database\Seeders\DataMasterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Berkas yang sudah dikirim tidak bisa ditarik siapa pun begitu saja.
 *
 * Aturannya dari pemilik sistem, disebutkan lugas:
 *
 *   "SETBA atau yang lainnya kecuali Satker itu sendiri yang mengupload tidak
 *   bisa menghapus berkasnya... dan Satker yang sudah terlanjur mengupload file
 *   tersebut dan dikirimkan tidak akan bisa menarik file tersebut sebelum
 *   dikirimkan ulang ke asalnya... pastikan tidak ada celah terhadap file
 *   dokumen bisa dihapus terutama setelah dikirimkan atau saat diurus UKI,
 *   Inspektorat atau semacamnya terutama saat laporan sudah diberi semacam
 *   verifikasi yang mutlak seperti CHV."
 *
 * Uji ini menutup celahnya satu per satu.
 */
class PermintaanUbahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DataMasterSeeder::class, ContohLaporanSeeder::class]);
        \App\Support\PautkanSasaran::jalankan();
    }

    /**
     * Baris satuan kerja yang berkasnya sudah lepas dari mejanya.
     *
     * Dibatasi pada satuan kerja yang punya penggunanya di data contoh: yang
     * diuji perbuatan orang, dan satuan kerja tanpa pengguna tidak bisa
     * berbuat apa-apa.
     */
    private function barisTerkirim(): Sasaran
    {
        $punyaPengguna = User::where('peran', PeranPengguna::SATKER)
            ->pluck('satker_id')->filter()->all();

        $s = Sasaran::whereIn('satker_id', $punyaPengguna)->get()
            ->first(fn ($x) => $x->posisi !== PosisiBerkas::SATKER);

        if (! $s) {
            $s = Sasaran::whereIn('satker_id', $punyaPengguna)->firstOrFail();
            $s->update(['posisi' => PosisiBerkas::SETBA_TINJAU->value]);
            $s->refresh();
        }

        return $s;
    }

    private function pemilik(Sasaran $s): User
    {
        return User::where('peran', PeranPengguna::SATKER)
            ->where('satker_id', $s->satker_id)->firstOrFail();
    }

    public function test_satuan_kerja_bisa_meminta_penarikan_sesudah_terkirim(): void
    {
        $s = $this->barisTerkirim();

        $this->actingAs($this->pemilik($s))
            ->post(route('ubah.ajukan', $s), [
                'jenis' => JenisPermintaanUbah::PENARIKAN->value,
                'alasan' => 'Nilai setoran salah ketik, perlu diperbaiki.',
            ])->assertRedirect();

        $minta = PermintaanUbah::latest('id')->firstOrFail();
        $this->assertSame(StatusPermintaanUbah::MENUNGGU, $minta->status);
        $this->assertSame($s->id, $minta->sasaran_id);

        /* Yang penting: berkasnya BELUM berpindah. Permintaan bukan
           perbuatan — ia menunggu keputusan. */
        $this->assertNotSame(PosisiBerkas::SATKER, $s->fresh()->posisi);
    }

    public function test_satuan_kerja_tidak_bisa_memutus_permintaannya_sendiri(): void
    {
        $s = $this->barisTerkirim();
        $pemilik = $this->pemilik($s);

        $this->actingAs($pemilik)->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::PENARIKAN->value,
            'alasan' => 'Nilai setoran salah ketik, perlu diperbaiki.',
        ]);

        $minta = PermintaanUbah::latest('id')->firstOrFail();

        $this->actingAs($pemilik)
            ->post(route('ubah.putus', $minta), ['putusan' => 'setuju'])
            ->assertForbidden();

        $this->assertSame(StatusPermintaanUbah::MENUNGGU, $minta->fresh()->status);
        $this->assertNotSame(PosisiBerkas::SATKER, $s->fresh()->posisi);
    }

    public function test_satuan_kerja_lain_tidak_bisa_mengajukan(): void
    {
        $s = $this->barisTerkirim();

        $lain = User::where('peran', PeranPengguna::SATKER)
            ->where('satker_id', '!=', $s->satker_id)->firstOrFail();

        $this->actingAs($lain)->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::PENARIKAN->value,
            'alasan' => 'Bukan urusan saya, tapi saya coba.',
        ])->assertForbidden();

        /* Dihitung pada barisnya sendiri, bukan seluruh tabel: data contoh
           memang memuat satu permintaan yang menunggu, dan uji yang
           menghitung global ikut gagal tiap kali data contohnya berubah. */
        $this->assertSame(0, PermintaanUbah::where('sasaran_id', $s->id)->count());
    }

    public function test_permintaan_ditolak_tidak_mengubah_apa_pun(): void
    {
        $s = $this->barisTerkirim();
        $rek = $s->rekomendasi();

        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::PENARIKAN->value,
            'alasan' => 'Nilai setoran salah ketik, perlu diperbaiki.',
        ]);

        $minta = PermintaanUbah::latest('id')->firstOrFail();
        $pemutus = User::where('peran', $rek->pemutusPerubahan())->firstOrFail();

        $this->actingAs($pemutus)->post(route('ubah.putus', $minta), [
            'putusan' => 'tolak', 'catatan' => 'Buktinya sudah benar.',
        ])->assertRedirect();

        $this->assertSame(StatusPermintaanUbah::DITOLAK, $minta->fresh()->status);
        $this->assertNotSame(PosisiBerkas::SATKER, $s->fresh()->posisi);
    }

    public function test_persetujuan_mengembalikan_berkas_ke_satuan_kerja(): void
    {
        $s = $this->barisTerkirim();
        $rek = $s->rekomendasi();

        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::PENARIKAN->value,
            'alasan' => 'Nilai setoran salah ketik, perlu diperbaiki.',
        ]);

        $minta = PermintaanUbah::latest('id')->firstOrFail();
        $pemutus = User::where('peran', $rek->pemutusPerubahan())->firstOrFail();

        $this->actingAs($pemutus)->post(route('ubah.putus', $minta), [
            'putusan' => 'setuju',
        ])->assertRedirect();

        /* Berkasnya kembali ke satuan kerja — bukan diubah oleh yang memutus.
           Yang menyunting isinya tetap pemiliknya sendiri. */
        $this->assertSame(PosisiBerkas::SATKER, $s->fresh()->posisi);
    }

    /**
     * Celah yang paling penting ditutup: sesudah CHV terbit, tidak ada
     * permintaan yang bisa diajukan maupun disetujui.
     */
    public function test_sesudah_chv_terbit_tidak_ada_yang_bisa_diubah(): void
    {
        $s = $this->barisTerkirim();
        $rek = $s->rekomendasi();

        // permintaan diajukan lebih dulu, selagi belum ada surat
        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::PENARIKAN->value,
            'alasan' => 'Nilai setoran salah ketik, perlu diperbaiki.',
        ])->assertRedirect();

        $minta = PermintaanUbah::latest('id')->firstOrFail();

        // lalu surat verifikasinya terbit
        $surat = Verifikasi::create([
            'jenis' => Verifikasi::CHV, 'periode' => 'Uji',
            'nomor_surat' => '99/CHV/UJI/2026', 'tgl_surat' => now()->toDateString(),
            'pejabat' => 'Inspektur Jenderal',
        ]);
        $rek->keputusan()->create([
            'verifikasi_id' => $surat->id,
            'hasil' => \App\Enums\HasilTelaah::M->value,
        ]);
        $rek->refresh();
        $this->assertTrue($rek->terkunciOlehSurat());

        $pemutus = User::where('peran', $rek->pemutusPerubahan())->firstOrFail();

        // menyetujui yang sudah telanjur diajukan: ditolak
        $this->actingAs($pemutus)->post(route('ubah.putus', $minta), [
            'putusan' => 'setuju',
        ])->assertStatus(422);

        $this->assertSame(StatusPermintaanUbah::MENUNGGU, $minta->fresh()->status);
        $this->assertNotSame(PosisiBerkas::SATKER, $s->fresh()->posisi);

        // mengajukan yang baru: juga ditolak
        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::BATAL_DOKUMEN->value,
            'alasan' => 'Mencoba membatalkan berkas sesudah surat terbit.',
        ])->assertStatus(422);
    }

    /**
     * Berkas hanya bisa ditarik lewat persetujuan, dan hanya berkas milik
     * baris yang diminta.
     */
    public function test_berkas_hanya_batal_lewat_persetujuan(): void
    {
        $s = $this->barisTerkirim();
        $rek = $s->rekomendasi();

        $berkas = Lampiran::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id' => $s->id,
            'nama_asli' => 'bukti-setor-uji.pdf',
            'nama_simpan' => 'uji.pdf',
            'diunggah_pada' => now(),
        ]);

        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::BATAL_DOKUMEN->value,
            'alasan' => 'Berkas ini keliru, bukan yang diminta.',
            'lampiran_sasaran_id' => $berkas->id,
        ])->assertRedirect();

        // sebelum diputus, berkasnya masih utuh
        $this->assertFalse($berkas->fresh()->ditarik());

        $minta = PermintaanUbah::latest('id')->firstOrFail();
        $pemutus = User::where('peran', $rek->pemutusPerubahan())->firstOrFail();

        $this->actingAs($pemutus)->post(route('ubah.putus', $minta), [
            'putusan' => 'setuju',
        ])->assertRedirect();

        $berkas->refresh();
        $this->assertTrue($berkas->ditarik());
        /* Catatan bahwa berkas pernah ada tetap — justru itu yang membuat
           penarikannya bisa dipertanggungjawabkan. */
        $this->assertNotNull($berkas->ditarik_pada);
        $this->assertSame(\App\Enums\SebabTarikBerkas::BATAL, $berkas->sebab_tarik);
    }

    public function test_berkas_satuan_kerja_lain_tidak_bisa_dibatalkan(): void
    {
        $s = $this->barisTerkirim();
        $rek = $s->rekomendasi();

        $lainnya = Sasaran::whereNotNull('satker_id')
            ->where('satker_id', '!=', $s->satker_id)->firstOrFail();
        $berkasOrangLain = Lampiran::create([
            'rekomendasi_id' => $rek->id,
            'sasaran_id' => $lainnya->id,
            'nama_asli' => 'punya-orang-lain.pdf',
            'nama_simpan' => 'lain.pdf',
            'diunggah_pada' => now(),
        ]);

        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::BATAL_DOKUMEN->value,
            'alasan' => 'Mencoba membatalkan berkas satuan kerja lain.',
            'lampiran_sasaran_id' => $berkasOrangLain->id,
        ])->assertStatus(422);

        $this->assertFalse($berkasOrangLain->fresh()->ditarik());
    }

    public function test_berkas_yang_masih_di_meja_sendiri_tidak_perlu_meminta(): void
    {
        $s = $this->barisTerkirim();
        $s->update(['posisi' => PosisiBerkas::SATKER->value]);
        $s->refresh();

        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::PENARIKAN->value,
            'alasan' => 'Padahal berkasnya masih di meja saya sendiri.',
        ])->assertStatus(422);

        $this->assertSame(0, PermintaanUbah::where('sasaran_id', $s->id)->count());
    }

    /**
     * Panelnya benar-benar tampil di halamannya.
     *
     * Aturan yang cuma dijaga pengendali tanpa pintu di layar berarti tidak ada
     * yang bisa memakainya — dan satuan kerja yang butuh mengoreksi kirimannya
     * akan menelepon Setba, persis keadaan yang mau dihindari.
     */
    public function test_panelnya_tampil_di_halaman_rekomendasi(): void
    {
        $s = $this->barisTerkirim();
        $rek = $s->rekomendasi();

        // satuan kerja pemiliknya melihat pintunya
        $this->actingAs($this->pemilik($s))->get(route('rekomendasi.show', $rek))
            ->assertOk()
            ->assertSee('Permintaan perubahan')
            ->assertSee('Perlu mengubah berkas yang sudah dikirim?');

        // sesudah diajukan, yang memutus melihat tombol putusannya
        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), [
            'jenis' => JenisPermintaanUbah::PENARIKAN->value,
            'alasan' => 'Nilai setoran salah ketik, perlu diperbaiki.',
        ]);

        $pemutus = User::where('peran', $rek->pemutusPerubahan())->firstOrFail();
        $this->actingAs($pemutus)->get(route('rekomendasi.show', $rek))
            ->assertOk()
            ->assertSee('Setujui')
            ->assertSee('Nilai setoran salah ketik, perlu diperbaiki.');
    }

    public function test_hanya_satu_permintaan_menunggu_pada_satu_waktu(): void
    {
        $s = $this->barisTerkirim();

        $isi = [
            'jenis' => JenisPermintaanUbah::PENARIKAN->value,
            'alasan' => 'Nilai setoran salah ketik, perlu diperbaiki.',
        ];

        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), $isi)
            ->assertRedirect();
        $this->actingAs($this->pemilik($s))->post(route('ubah.ajukan', $s), $isi)
            ->assertStatus(422);

        $this->assertSame(1, PermintaanUbah::where('sasaran_id', $s->id)->count());
    }
}
