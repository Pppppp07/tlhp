@extends('rangka')
@section('judul', 'Rekomendasi saya')
@section('isi')

<p style="margin:0 0 18px;font-size:12.5px;color:var(--ink-3);max-width:70ch;line-height:1.55">
  Berkas yang menyangkut Anda, dibagi menurut sudah sampai mana &mdash; masih di meja Anda,
  sudah berlanjut ke pihak lain, atau sudah ditetapkan.
</p>

@foreach($blok as $i => [$judul, $ket, $isi])
  {{-- "Sudah ditetapkan" bawaannya tertutup: itu arsip, bukan pekerjaan. --}}
  <x-daftar :id="$i" :judul="$judul" :ket="$ket" :isi="$isi" :tutup="$i === 2"
    :disorot="(string) $sorot === (string) $i" :tandai="$tandai" :nada="$nada" />
@endforeach
@endsection
