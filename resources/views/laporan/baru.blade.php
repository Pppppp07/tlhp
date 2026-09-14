@extends('rangka')
@section('judul', 'Catat laporan baru')
@section('isi')
@php
  use App\Support\Tampil;
  use App\Enums\SumberLaporan;

  $n = $d['langkah'];
  $s = $d['surat'];
  $sumber = SumberLaporan::from($s['sumber']);

  $langkah = [
    ['n' => 1, 'nama' => 'Surat laporan', 'ket' => 'Informasi surat yang diterima'],
    ['n' => 2, 'nama' => 'Temuan & rekomendasi', 'ket' => 'Catat temuan dan tindakannya'],
    ['n' => 3, 'nama' => 'Tinjau & kirim', 'ket' => 'Periksa lalu kirim ke satuan kerja'],
  ];

  $jmlTemuan = count($d['temuan']);
  $jmlRekom = collect($d['temuan'])->sum(fn ($t) => count($t['rekom']));
  $jmlSatker = collect($d['temuan'])->pluck('satker')->filter()->unique()->count();
@endphp

<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px">
  <form method="post" action="{{ route('laporan.baru.batal') }}">@csrf
    <button class="taut" type="submit"><x-ik nama="panah-kiri" ukuran="16" /> Batalkan pencatatan</button>
  </form>
  <div style="flex:1"></div>
</div>

{{-- Stepper: yang sudah lewat bercentang, yang sedang dikerjakan bergaris. --}}
<div class="langkah">
  @foreach($langkah as $l)
    <div @class(['aktif' => $n === $l['n'], 'selesai' => $n > $l['n']])>
      <span class="bul">@if($n > $l['n'])<x-ik nama="centang" ukuran="13" />@else{{ $l['n'] }}@endif</span>
      <span>
        <span class="nm">{{ $l['nama'] }}</span>
        <span class="ket">{{ $l['ket'] }}</span>
      </span>
    </div>
  @endforeach
</div>

@if($errors->any())
  <div class="pesan bad">
    Isian belum bisa disimpan: {{ $errors->first() }}
  </div>
@endif

<div class="dua">
  <div>
    @if($n === 1)
      @include('laporan/baru-surat')
    @elseif($n === 2)
      @include('laporan/baru-temuan')
    @else
      @include('laporan/baru-tinjau')
    @endif
  </div>

  {{-- Rel kanan mengulang apa yang sudah diisi, supaya pengisi tidak perlu
       menggulir balik ke langkah sebelumnya untuk memastikan. --}}
  <aside class="relkanan">
    <div class="kartu rapat">
      <div class="judulkartu">
        <span class="ic-kotak"><x-ik nama="berkas-teks" ukuran="17" /></span>
        <h3>Informasi surat</h3>
        @if($lengkap1)<span class="cap cap-SS">Lengkap</span>@endif
      </div>
      <div style="display:grid;gap:11px">
        <div><div class="lbl">Sumber laporan</div>
          <div style="font-size:13px">{{ $sumber->nama() }}</div></div>
        <div><div class="lbl">Nomor surat</div>
          <div class="mono" style="font-size:12.5px">{{ $s['nomor'] ?: 'belum diisi' }}</div></div>
        <div><div class="lbl">Tanggal surat diterima</div>
          <div style="font-size:13px">{{ $s['tgl_terima'] ? Tampil::tgl($s['tgl_terima']) : 'belum diisi' }}</div></div>
        <div><div class="lbl">Tenggat jawaban</div>
          <div style="font-size:13px">
            @if($s['tgl_terima'])
              {{ Tampil::tgl(\App\Models\Rekomendasi::hitungTenggat(
                   \Carbon\Carbon::parse($s['tgl_terima']), $sumber)) }}
              <div class="lbl" style="margin-top:2px">
                {{ $sumber->hariTenggat() }} {{ $sumber->pakaiHariKerja() ? 'hari kerja' : 'hari kalender' }}
                &middot; dasar {{ $sumber->dasarHukum() }}
              </div>
            @else
              menunggu tanggal diterima
            @endif
          </div></div>
      </div>
    </div>

    @if($n > 1)
      <div class="kartu rapat">
        <div class="judulkartu">
          <span class="ic-kotak hijau"><x-ik nama="papan-cek" ukuran="17" /></span>
          <h3>Isi laporan</h3>
          @if(! $kurang2)<span class="cap cap-SS">Lengkap</span>@endif
        </div>
        <div class="tenggat"><div><b>Temuan</b></div><span class="mono">{{ $jmlTemuan }}</span></div>
        <div class="tenggat"><div><b>Rekomendasi</b></div><span class="mono">{{ $jmlRekom }}</span></div>
        <div class="tenggat"><div><b>Satuan kerja diperiksa</b></div><span class="mono">{{ $jmlSatker }}</span></div>
        @if($kurang2)
          <div class="lbl" style="margin-top:10px;color:var(--bad);line-height:1.5">{{ $kurang2 }}</div>
        @endif
      </div>
    @endif
  </aside>
</div>
@endsection
