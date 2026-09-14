<?php
use App\Models\{Laporan, Rekomendasi, Lampiran};
use App\Enums\{PeranPengguna, SumberLaporan, PosisiBerkas};

$garis = fn($t) => print("\n== $t ==\n");

$garis('Tiga tingkat');
foreach (Laporan::with('temuan.rekomendasi')->get() as $l) {
    printf("%s  %s  | %d temuan | %d rekomendasi | status simpulan: %s | tuntas: %s\n",
        $l->sumber->value, $l->nomor, $l->temuan->count(),
        $l->rekomendasi()->count(), $l->statusSimpulan()->value, $l->tuntas() ? 'ya' : 'belum');
}

$garis('Tenggat: LHA hari kerja vs LHP hari kalender');
foreach (Laporan::all() as $l) {
    printf("%s diterima %s -> tenggat jawab %s  (%d %s)\n",
        $l->sumber->value, $l->tgl_terima->format('d M Y'),
        $l->tenggatJawab()->format('d M Y'),
        $l->sumber->hariTenggat(), $l->sumber->pakaiHariKerja() ? 'hari kerja' : 'hari kalender');
}

$garis('Sumbu ketiga: progres, dihitung bukan disimpan');
$r = Rekomendasi::where('kode', 'REK-2026-014.1')->first();
$d = $r->progresDokumen();
printf("%s | status %s | posisi: %s\n", $r->kode, $r->status->value, $r->posisi->label());
printf("  dana     : Rp %s dari Rp %s, sisa Rp %s, lunas: %s\n",
    number_format($r->nilaiTerpulihkan(), 0, ',', '.'),
    number_format($r->nilai_pulih, 0, ',', '.'),
    number_format($r->sisaPemulihan(), 0, ',', '.'), $r->lunas() ? 'ya' : 'belum');
printf("  dokumen  : %d dari %d, lengkap: %s\n", $d[0], $d[1], $r->dokumenLengkap() ? 'ya' : 'belum');
printf("  boleh dikirim ke Setba: %s\n", $r->bolehDikirim() ? 'YA' : 'BELUM');

$garis('Satu surat, banyak keputusan');
foreach (\App\Models\Verifikasi::with('keputusan.rekomendasi')->get() as $v) {
    printf("%s (%s) -> %d keputusan: %s\n", $v->nomor_surat, $v->periode, $v->keputusan->count(),
        $v->keputusan->map(fn($k) => $k->rekomendasi->kode.'='.$k->hasil->value)->join(', '));
}

$garis('Antrean per peran, dari posisi berkas');
foreach (PeranPengguna::cases() as $p) {
    $n = Rekomendasi::diMeja($p)->count();
    if ($n) printf("  %-12s %d berkas\n", $p->value, $n);
}

$garis('Penarikan berkas membatalkan centang kelengkapan');
$lamp = Lampiran::where('nama_asli', 'rekap-selisih-per-pegawai.xlsx')->first();
$r->refresh(); $sebelum = $r->progresDokumen();
$lamp->tarik(\App\Enums\SebabTarikBerkas::PRIBADI);
$r->refresh(); $sesudah = $r->progresDokumen();
printf("sebelum: %d dari %d  ->  sesudah ditarik: %d dari %d\n", $sebelum[0], $sebelum[1], $sesudah[0], $sesudah[1]);
$lamp->refresh();
printf("berkas  : nama='%s'  label='%s'  sebab=%s\n",
    $lamp->nama_asli ?? '(dihapus)', $lamp->labelTampil(), $lamp->sebab_tarik->value);

$garis('Kunci setelah surat terbit');
foreach (['REK-2026-014.1', 'REK-2026-021.1'] as $k) {
    $x = Rekomendasi::where('kode', $k)->first();
    printf("%s terkunci oleh surat: %s | pemutus perubahan: %s\n",
        $k, $x->terkunciOlehSurat() ? 'ya' : 'belum', $x->pemutusPerubahan()->value);
}
