@extends('rangka')
@section('judul', 'Sudah selesai')
@section('isi')
@php
  use App\Support\Tampil;

  /* Dipisah dari daftar berjalan supaya yang masih perlu dikerjakan tidak
     tenggelam di antara yang sudah tidak perlu disentuh lagi. */
  $kartuAngka = [
    ['l' => 'Sudah ditetapkan', 'v' => $tuntas->count(), 'k' => 'statusnya tidak berubah lagi',
     'w' => 'ok', 'ke' => ['sorot' => 0, 'nada' => 'ok']],
    ['l' => 'Sesuai rekomendasi', 'v' => $sesuai, 'k' => 'ditutup dengan surat bernomor',
     'w' => 'aksen', 'ke' => ['sorot' => 0, 'saring' => 'ss', 'nada' => 'aksen']],
    ['l' => 'Tidak dapat ditindaklanjuti', 'v' => $tidakDapat, 'k' => 'dengan alasan sah',
     'w' => 'warn', 'ke' => ['sorot' => 0, 'saring' => 'td', 'nada' => 'warn']],
    ['l' => 'Dana dipulihkan', 'v' => Tampil::rupiahSingkat($pulih),
     'k' => 'dari rekomendasi yang sudah tuntas', 'w' => 'jingga',
     'ke' => ['sorot' => 0, 'saring' => 'pulih', 'nada' => 'jingga']],
  ];
@endphp

<div class="statistik">
  @foreach($kartuAngka as $k)
    @php
      $bisa = $tuntas->isNotEmpty() && $k['v'] !== 0 && $k['v'] !== '0' && $k['v'] !== 'Rp 0';
    @endphp
    @if($bisa)
      <a class="stat bisa {{ $k['w'] }}" href="{{ route('selesai', $k['ke']) }}">
        <div class="l">{{ $k['l'] }}</div>
        <div class="v">{{ $k['v'] }}</div>
        <div class="k">{{ $k['k'] }}</div>
      </a>
    @else
      <div class="stat {{ $k['w'] }}">
        <div class="l">{{ $k['l'] }}</div>
        <div class="v">{{ $k['v'] }}</div>
        <div class="k">{{ $k['k'] }}</div>
      </div>
    @endif
  @endforeach
</div>

<x-daftar id="0" judul="Sudah ditetapkan" :isi="$tuntas"
  ket="Statusnya sudah final dan tidak berubah lagi."
  :disorot="$sorot !== null" :tandai="$tandai" :nada="$nada" />
@endsection
