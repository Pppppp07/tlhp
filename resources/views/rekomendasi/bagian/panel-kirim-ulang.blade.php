@php
  use App\Support\Tampil;

  /* Meja pemberkasan ulang, per baris — padanan panelKirimUlangBaris. Setba
     menyetel dokumen yang diminta, berpegang pada pernyataan yang menolak. */
  $dariSiapa = $x->kembali_dari ?: 'pemeriksa';
  $dokAwal = $x->dokumen_diminta ?: [''];
  $lewat = $x->batas_perbaikan && $x->batas_perbaikan->copy()->startOfDay()->lt(now()->startOfDay());
  $pastikan = [
    'judul' => 'Kirim ulang berkas '.$x->satker->namaPendek().'?',
    'ket' => 'Berkas kembali ke meja '.$x->satker->namaPendek().' untuk pemberkasan ulang, bersama alasan penolakan '.$dariSiapa
      .($x->batas_perbaikan ? ' dan batas waktunya ('.Tampil::tgl($x->batas_perbaikan).')' : '')
      .'. Satuan kerja lain pada rekomendasi ini tidak ikut.',
    'tombol' => 'Ya, kirim ulang', 'nada' => 'kuning',
  ];
@endphp

<form method="post" action="{{ route('sasaran.kirimUlang', $x) }}" id="r-terus" class="tindakan dibaris rata" data-form-kirim-ulang
  data-satker="{{ $x->satker->namaPendek() }}" data-dari="{{ $dariSiapa }}" data-batas="{{ $x->batas_perbaikan ? Tampil::tgl($x->batas_perbaikan) : '' }}">
  @csrf
  <div class="isian">
    <div>
      <span class="lbl"><x-ikon n="RotateCcw" :s="13" /> Pemberkasan ulang</span>
      <div style="font-size:var(--t3);font-weight:600">
        {{ $x->satker->namaPendek() }}
        <x-info :teks="[
          'Berkas yang ditolak UKI atau Inspektorat tidak langsung pulang ke satuan kerja. Setba yang mengirimkannya ulang.',
          'Alasan dan batas waktunya ditetapkan yang menolak, dan tidak diubah di sini.',
          'Keterangan Setba boleh dikosongkan. Gunanya memperjelas maksud penolakan supaya satuan kerja mudah memperbaikinya.',
          'Yang dikirim ulang hanya satuan kerja pada baris ini. Yang lain tetap di tempatnya.',
        ]" />
      </div>
    </div>
    <div>
      <span class="lbl">Ditolak {{ $dariSiapa }}</span>
      <div style="font-size:13px;line-height:1.55">
        {{ $x->alasan_perbaikan ?: 'Tanpa catatan.' }}
        @if($x->batas_perbaikan)
          <div class="lbl" style="margin:4px 0 0;{{ $lewat ? 'color:var(--bad)' : '' }}">
            perbaiki paling lambat {{ Tampil::tgl($x->batas_perbaikan) }}{{ $lewat ? ' — sudah lewat' : '' }}
          </div>
        @endif
      </div>
    </div>
    <div>
      <span class="lbl">Dokumen yang diminta
        <x-info :teks="[
          $x->dokumen_diminta ? 'Diisi dari permintaan '.$dariSiapa.'. Periksa dan sesuaikan kalau perlu.'
            : $dariSiapa.' tidak menyebut dokumen. Isi kalau alasannya memang meminta dokumen tertentu.',
          'Satuan kerja wajib melampirkan seluruhnya sebelum bisa mengirim ulang ke Setba.',
        ]" />
      </span>
      @include('rekomendasi.bagian.daftar-isian', ['nama' => 'dokumen', 'nilai' => $dokAwal, 'placeholder' => 'Nama dokumen', 'tambah' => 'Tambah dokumen'])
    </div>
    <div>
      <span class="lbl">Keterangan tambahan dari Setba — boleh dikosongkan</span>
      <textarea name="keterangan" style="min-height:54px" placeholder="Perjelas maksud catatan {{ $dariSiapa }} supaya satuan kerja mudah memperbaikinya."></textarea>
    </div>
    <div>
      <span class="lbl"></span>
      <div style="display:flex;gap:9px;flex-wrap:wrap">
        <button type="submit" class="btn btn-p" data-pastikan='@json($pastikan)'>
          <x-ikon n="RotateCcw" :s="14" /> Kirim ulang ke satuan kerja
        </button>
      </div>
    </div>
  </div>
</form>
