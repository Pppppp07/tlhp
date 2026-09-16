@props(['lap', 'maks' => null])

@php
  /* Satuan kerja yang diperiksa, sebagai keping. `maks` meringkas: dua nama
     pertama, sisanya "+N lagi" (RingkasSatker di prototipe). */
  $d = $lap->satkerDiperiksa();
@endphp

@if($d->isEmpty())
  —
@else
  <span class="kepingsatker">
    @foreach($maks ? $d->take($maks) : $d as $x)
      @php $n = $lap->jumlahTemuanSatker($x->id); @endphp
      <span class="keping">{{ $x->namaPendek() }}@if(! $maks && $n > 1)<i>{{ $n }} temuan</i>@endif</span>
    @endforeach
    @if($maks && $d->count() > $maks)
      <span class="keping lain">+{{ $d->count() - $maks }} lagi</span>
    @endif
  </span>
@endif
