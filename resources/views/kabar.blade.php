@extends('rangka')
@section('judul', 'Pemberitahuan')
@section('isi')
@php
  use App\Support\Kabar;
  use App\Support\Tampil;
@endphp

@php
  $baris = function ($k, $redup) {
    return [$k, $redup];
  };
@endphp

<div class="kartu" style="margin-bottom:18px;display:flex;gap:16px;align-items:baseline;flex-wrap:wrap">
  <span class="mono" style="font-size:26px;font-weight:700;line-height:1">{{ $belum->count() }}</span>
  <span style="font-size:14px;font-weight:600">kabar belum dibaca</span>
  <div style="flex:1"></div>
  @if($belum->isNotEmpty())
    <form method="post" action="{{ route('kabar.semua') }}">@csrf
      <button class="btn btn-s" type="submit">Tandai semua terbaca</button>
    </form>
  @endif
</div>

@forelse($belum as $k)
  @include('bagian/baris-kabar', ['k' => $k, 'redup' => false])
@empty
  <div class="kartu" style="color:var(--ink-3);font-size:13px">Tidak ada kabar baru.</div>
@endforelse

@if($sudah->isNotEmpty())
  <details style="margin-top:20px">
    <summary class="lbl" style="cursor:pointer;padding:6px 0">
      Sudah dibaca &middot; {{ $sudah->count() }}
    </summary>
    <div style="margin-top:12px">
      @foreach($sudah as $k)
        @include('bagian/baris-kabar', ['k' => $k, 'redup' => true])
      @endforeach
    </div>
  </details>
@endif
@endsection
