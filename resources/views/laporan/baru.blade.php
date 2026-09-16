@extends('rangka')
@section('judul', 'Catat laporan baru')
@section('isi')
@php
  use App\Support\Tampil;

  /* Catat laporan baru — padanan `FormBaru`. Satu formulir untuk ketiga
     langkahnya: menekan tombol apa pun membawa seluruh isian yang sedang
     tampak, jadi tidak ada ketikan yang hilang saat berpindah. */
  $n = $d['n'];
  $ok = $n === 1 ? $ok1 : $form->ok2($d);

  $langkah = [
    ['n' => 1, 'nama' => 'Surat laporan', 'ket' => 'Informasi surat yang diterima'],
    ['n' => 2, 'nama' => 'Temuan & rekomendasi', 'ket' => 'Catat temuan dan tindakannya'],
    ['n' => 3, 'nama' => 'Tinjau & kirim', 'ket' => 'Periksa lalu kirim ke satuan kerja'],
  ];

  /* Mengajukan laporan memecahnya jadi penugasan terpisah ke banyak satuan
     kerja sekaligus — tidak bisa ditarik lewat aplikasi. */
  $cek = fn ($teks) => '<li><span style="color:var(--ok);flex:none">&#10003;</span><span>'.e($teks).'</span></li>';
  $tanyaAjukan = [
    'judul' => 'Ajukan laporan ini?',
    'ket' => "Laporan akan pecah jadi {$ringkas['pen']} penugasan dari {$ringkas['rek']} rekomendasi, "
      ."untuk {$ringkas['sat']} satuan kerja. Tiap penugasan berjalan dengan tenggat dan posisinya sendiri.",
    'rincian' => '<ul class="ceklis">'
      .$cek("{$ringkas['tem']} temuan, {$ringkas['rek']} rekomendasi")
      .$cek("{$ringkas['sat']} satuan kerja menerima {$ringkas['pen']} penugasan")
      .$cek("{$ringkas['ten']} tanggal tenggat berbeda")
      .$cek('Nilai yang ditagih '.Tampil::rupiah($ringkas['nilai']))
      .'</ul>',
    'tombol' => 'Ya, ajukan laporan',
    'nada' => 'hijau',
  ];

  /* Meninggalkan formulir tidak pernah membuang isian diam-diam. Yang ada
     isinya disimpan sebagai draf; yang benar-benar ingin membuang memakai
     "Kosongkan formulir", dan itu bertanya dulu. */
  $tanyaKosong = [
    'judul' => 'Kosongkan formulir ini?',
    'ket' => 'Seluruh isian — data surat, temuan, dan rekomendasinya — dihapus, '
      .'termasuk draf yang tersimpan. Belum ada yang terkirim ke satuan kerja mana pun.',
    'tombol' => 'Ya, kosongkan',
    'nada' => 'merah',
  ];
@endphp

<div class="body">
  <form method="post" action="{{ route('laporan.baru.simpan') }}" data-form-baru>
    @csrf

    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:20px">
      <button class="taut" type="submit" name="aksi" value="tinggalkan">
        <x-ikon n="ArrowLeft" :s="16" /> Kembali ke beranda
      </button>
      <div style="flex:1"></div>
      @if($adaIsi)
        <button class="btn" type="submit" name="aksi" value="kosongkan" data-pastikan='@json($tanyaKosong)'>
          <x-ikon n="X" :s="15" /> Kosongkan formulir
        </button>
      @endif
      <button class="btn" type="submit" name="aksi" value="simpan-draf" @disabled(! $adaIsi)>
        <x-ikon n="Save" :s="15" /> Simpan draft
      </button>
      @if($n < 3)
        <button class="btn btn-p" type="submit" name="aksi" value="maju">
          Berikutnya <x-ikon n="ArrowRight" :s="15" />
        </button>
      @else
        <button class="btn btn-p" type="submit" name="aksi" value="ajukan" data-pastikan='@json($tanyaAjukan)'>
          Ajukan laporan <x-ikon n="Send" :s="15" />
        </button>
      @endif
    </div>

    @if($adaDraf && $d['disimpan'])
      <div class="akibat" style="margin-bottom:14px">
        <x-ikon n="Save" :s="15" />
        <span>
          Melanjutkan draf yang disimpan {{ Tampil::tgl($d['disimpan']) }}. Draf ini cuma
          terlihat oleh Setba dan belum terkirim ke satuan kerja mana pun.
        </span>
      </div>
    @endif

    {{-- Yang sudah lewat bercentang, yang sedang dikerjakan bergaris biru. --}}
    <div class="langkah">
      @foreach($langkah as $l)
        <div @class(['aktif' => $n === $l['n'], 'selesai' => $n > $l['n']])>
          <span class="bul">@if($n > $l['n'])<x-ikon n="Check" :s="13" />@else{{ $l['n'] }}@endif</span>
          <span>
            <span class="nm">{{ $l['nama'] }}</span>
            <span class="ket">{{ $l['ket'] }}</span>
          </span>
        </div>
      @endforeach
    </div>

    {{-- Satu lajur penuh. Rel kanannya dibuang — isinya mengulang persis apa
         yang ada di sebelahnya, dan yang membayarnya seluruh isian jadi sempit.
         Yang mengisi formulir sedang mengetik, bukan sedang membaca ringkasan. --}}
    <div class="lajurisi">
      @if($n === 1)
        @include('laporan.baru-surat')
      @elseif($n === 2)
        @include('laporan.baru-temuan')
      @else
        @include('laporan.baru-tinjau')
      @endif

      <div class="bilah">
        @if($n > 1)
          <button class="btn" type="submit" name="aksi" value="mundur">
            <x-ikon n="ArrowLeft" :s="14" /> Sebelumnya
          </button>
        @endif
        @if($n < 3)
          <button class="btn btn-p" type="submit" name="aksi" value="maju">
            Berikutnya: {{ $langkah[$n]['nama'] }} <x-ikon n="ArrowRight" :s="14" />
          </button>
        @else
          <button class="btn btn-ok" type="submit" name="aksi" value="ajukan" data-pastikan='@json($tanyaAjukan)'>
            <x-ikon n="Send" :s="14" /> Ajukan laporan
          </button>
        @endif
        <span class="ket">
          {{ $kurang ?: ($n === 3
            ? "{$ringkas['tem']} temuan, {$ringkas['rek']} rekomendasi, {$ringkas['sat']} satuan kerja"
            : 'Sudah lengkap') }}
        </span>
      </div>
    </div>
  </form>
</div>
@endsection
