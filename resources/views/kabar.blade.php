@extends('rangka')
@section('judul', 'Pemberitahuan')
@section('isi')
@php
  use App\Support\Kabar;

  $u = auth()->user();
@endphp

<div class="body">
  <div class="fokus">
    <span class="jml">{{ $belum->count() }}</span>
    <span class="apa">kabar belum dibaca</span>
    @if($belum->isNotEmpty())
      <form method="post" action="{{ route('kabar.semua') }}" style="margin-left:auto;align-self:center">
        @csrf
        <button type="submit" class="btn btn-s"><x-ikon n="Check" :s="13" /> Tandai semua terbaca</button>
      </form>
    @endif
  </div>

  <div style="display:grid;gap:10px;margin-bottom:24px">
    @forelse($belum as $k)
      @include('bagian.baris-kabar', ['k' => $k, 'redup' => false])
    @empty
      <div class="kosong">Tidak ada kabar baru.</div>
    @endforelse
  </div>

  <button type="button" class="blokjudul" aria-expanded="false" aria-controls="kabar-sudah" data-buka-blok>
    <span class="panah">›</span>
    Sudah dibaca
    <span class="n">{{ $sudah->count() }}</span>
  </button>
  <div id="kabar-sudah" style="display:grid;gap:10px" hidden>
    @forelse($sudah as $k)
      @include('bagian.baris-kabar', ['k' => $k, 'redup' => true])
    @empty
      <div class="kosong">Belum ada.</div>
    @endforelse
  </div>
</div>
@endsection
