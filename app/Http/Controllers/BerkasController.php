<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\Lampiran;

class BerkasController extends Controller
{
    /**
     * Berkas tidak pernah disajikan lewat tautan langsung ke folder
     * penyimpanan. Rute ini memeriksa dulu apakah yang meminta memang berhak
     * melihatnya, baru berkasnya dikirim.
     */
    public function show(Lampiran $lampiran)
    {
        if ($lampiran->ditarik()) {
            abort(410, 'Berkas ini sudah ditarik dan isinya dihapus.');
        }

        /* Satuan kerja hanya boleh membuka berkas yang menyangkut barisnya
           sendiri. Berkas tanpa sasaran menempel pada rekomendasi atau laporan
           seutuhnya — dan yang terakhir itu justru surat pemeriksaan aslinya,
           yang memuat temuan seluruh satuan kerja. */
        if (auth()->user()->peran === PeranPengguna::SATKER) {
            $satker = auth()->user()->satker_id;

            if ($lampiran->laporan_id && ! $lampiran->sasaran_id) {
                abort(403, 'Surat pemeriksaan asli memuat temuan seluruh satuan kerja.');
            }

            if ($lampiran->sasaran_id) {
                abort_unless($lampiran->sasaran?->satker_id === $satker, 403,
                    'Berkas ini milik satuan kerja lain.');
            } elseif ($rek = $lampiran->rekomendasi) {
                abort_unless($rek->daftarSasaran()->contains('satker_id', $satker), 403,
                    'Berkas ini milik satuan kerja lain.');
            }
        }

        /* Data contoh belum punya berkas sungguhan di penyimpanan, jadi yang
           ditampilkan keterangannya. Begitu unggahan sungguhan dipasang,
           baris ini berganti Storage::download(). */
        return view('berkas', ['b' => $lampiran]);
    }
}
