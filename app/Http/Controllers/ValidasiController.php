<?php

namespace App\Http\Controllers;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\RiwayatBerkas;
use App\Models\Sasaran;
use App\Models\Surat;
use App\Models\Telaah;
use App\Models\Verifikasi;
use App\Support\Kabar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * UKI menerbitkan Laporan Hasil Validasi (LHV) bernomor.
 *
 * Sebelum ini telaah UKI hanya jadi catatan di halaman rincian, satu per satu.
 * Kenyataan kertasnya berbeda: satu LHV bernomor memuat hasil validasi untuk
 * beberapa berkas sekaligus, persis seperti CHV Inspektorat. Nomor itu yang
 * dipakai menelusuri berkasnya di luar sistem.
 *
 * Bedanya dari CHV, dan bedanya penting:
 *
 *   LHV  memvalidasi berkas TIAP SATUAN KERJA — satu baris satu satker
 *   CHV  memutus SELURUH rekomendasi sekaligus
 *
 * Karena itu layar ini berdaftar sasaran, bukan rekomendasi.
 */
class ValidasiController extends Controller
{
    public function form()
    {
        $this->hanyaUki();

        return view('validasi/form', [
            'antre' => Sasaran::with(['satker', 'tindakan.rekomendasi.temuan.laporan',
                                      'permintaanDokumen.item', 'pemulihan'])
                ->where('posisi', PosisiBerkas::UKI->value)
                ->get()
                ->sortBy(fn ($s) => $s->tindakan?->rekomendasi?->kode)
                ->values(),
            'riwayat' => Verifikasi::lhv()->with('keputusan')
                ->latest('tgl_surat')->take(10)->get(),
        ]);
    }

    public function simpan(Request $req)
    {
        $this->hanyaUki();

        $data = $req->validate([
            'periode'          => ['required', 'string', 'max:40'],
            'nomor_surat'      => ['required', 'string', 'max:120', 'unique:verifikasis,nomor_surat'],
            'tgl_surat'        => ['required', 'date', 'before_or_equal:today'],
            'pejabat'          => ['required', 'string', 'max:160'],
            'putusan'          => ['required', 'array', 'min:1'],
            'putusan.*.hasil'  => ['nullable', 'in:M,BM'],
            'putusan.*.catatan' => ['nullable', 'string', 'max:2000'],
        ], [
            'nomor_surat.unique'        => 'Nomor surat itu sudah pernah dicatat. Satu surat cukup dicatat sekali.',
            'tgl_surat.before_or_equal' => 'Tanggal surat tidak boleh di masa depan.',
        ]);

        $dipilih = collect($data['putusan'])->filter(fn ($p) => ! empty($p['hasil']));
        if ($dipilih->isEmpty()) {
            return back()->withInput()
                ->withErrors(['putusan' => 'Pilih hasil untuk setidaknya satu berkas.']);
        }

        $sasaran = Sasaran::with(['satker', 'tindakan.rekomendasi.temuan.laporan'])
            ->whereIn('id', $dipilih->keys())
            ->where('posisi', PosisiBerkas::UKI->value)
            ->get()->keyBy('id');

        /* Kesimpulan wajib ditulis — telaah tanpa kesimpulan bukan telaah, dan
           yang membaca berikutnya perlu tahu apa yang dinilai. Diperiksa
           sebelum apa pun ditulis, supaya tidak ada surat setengah tercatat. */
        $galat = [];
        foreach ($dipilih as $id => $p) {
            if (! isset($sasaran[$id])) {
                $galat["putusan.$id.hasil"] = 'Berkas ini tidak sedang di meja UKI.';
                continue;
            }
            if (mb_strlen(trim((string) ($p['catatan'] ?? ''))) < 6) {
                $galat["putusan.$id.catatan"] =
                    'Kesimpulan wajib ditulis — tanpa itu tidak ada yang bisa dibaca Setba.';
            }
        }
        if ($galat) {
            return back()->withInput()->withErrors($galat);
        }

        DB::transaction(function () use ($data, $dipilih, $sasaran) {
            $surat = Verifikasi::create([
                'jenis'        => Verifikasi::LHV,
                'periode'      => $data['periode'],
                'nomor_surat'  => $data['nomor_surat'],
                'tgl_surat'    => $data['tgl_surat'],
                'pejabat'      => $data['pejabat'],
                'dicatat_oleh' => auth()->id(),
            ]);

            foreach ($dipilih as $id => $p) {
                $s = $sasaran[$id];
                $rek = $s->tindakan->rekomendasi;
                $hasil = HasilTelaah::from($p['hasil']);
                $satker = $s->satker?->namaPendek() ?? 'satuan kerja';
                $sumber = $rek->temuan->laporan->sumber;

                Telaah::create([
                    'rekomendasi_id' => $rek->id,
                    'sasaran_id'     => $s->id,
                    'tanggal'        => $data['tgl_surat'],
                    'oleh_id'        => auth()->id(),
                    'label_oleh'     => 'UKI',
                    'hasil'          => $hasil->value,
                    'nomor_surat'    => $data['nomor_surat'],
                    'tgl_surat'      => $data['tgl_surat'],
                    'perihal'        => 'Laporan Hasil Validasi '.$data['periode'],
                    'catatan'        => $p['catatan'],
                ]);

                /* Berkas yang dinilai cukup naik ke Setba untuk diteruskan;
                   yang belum pulang ke satuan kerjanya. Tanda memadai ditulis
                   di barisnya — ia penilaian atas SATU satuan kerja. */
                $ke = $hasil->memadai() ? PosisiBerkas::SETBA_TERUSKAN : PosisiBerkas::SATKER;
                $dari = $s->posisi;

                $s->update(['posisi' => $ke->value, 'hasil' => $hasil->value]);

                Surat::create([
                    'rekomendasi_id' => $rek->id,
                    'sasaran_id'     => $s->id,
                    'dari'           => 'UKI',
                    'ke'             => 'Setba',
                    'nomor'          => $data['nomor_surat'],
                    'tanggal'        => $data['tgl_surat'],
                    'tanggal_catat'  => now()->toDateString(),
                    'perihal'        => 'Laporan Hasil Validasi '.$data['periode'],
                    'dicatat_oleh'   => auth()->id(),
                ]);

                RiwayatBerkas::create([
                    'rekomendasi_id' => $rek->id,
                    'sasaran_id'     => $s->id,
                    'waktu'          => now(),
                    'aktor_id'       => auth()->id(),
                    'label_aktor'    => 'UKI',
                    'aksi'           => "LHV {$data['nomor_surat']} — berkas {$satker} dinilai "
                                        .mb_strtolower($hasil->nama($sumber)).'. '.$p['catatan'],
                    'posisi_dari'    => $dari?->value,
                    'posisi_ke'      => $ke->value,
                ]);

                Kabar::tulis($rek, "LHV {$data['nomor_surat']} — berkas {$satker} "
                    .mb_strtolower($hasil->nama($sumber)),
                    [PeranPengguna::SETBA, PeranPengguna::SATKER], 'r-telaah');
            }
        });

        return redirect()->route('validasi.form')->with('pesan',
            'LHV '.$data['nomor_surat'].' dicatat untuk '.$dipilih->count().' berkas.');
    }

    private function hanyaUki(): void
    {
        abort_unless(auth()->user()->peran === PeranPengguna::UKI, 403,
            'Hanya UKI yang menerbitkan Laporan Hasil Validasi.');
    }
}
