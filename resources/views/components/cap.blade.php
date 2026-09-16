@props(['s' => null, 'jenis' => null, 'rek' => null])

@php
  /* Lencana status BPK. Jenis yang tidak pernah sampai ke SIPTL — LHA — tidak
     punya status BPK; yang berlaku baginya putusan Inspektorat, dan itu yang
     ditampilkan. Penjaganya di sini, bukan di tiap pemanggil. */
  $jenis = $jenis instanceof \App\Enums\SumberLaporan ? $jenis : \App\Enums\SumberLaporan::tryFrom((string) $jenis);
  $s = $s instanceof \App\Enums\StatusTindakLanjut ? $s : \App\Enums\StatusTindakLanjut::tryFrom((string) $s);
@endphp

@if($jenis && ! $jenis->melewatiSiptl())
  @if($rek)
    @php $h = $rek->keadaanUnor(); @endphp
    <span class="cap {{ $h->cap() }}">{{ $h->nama($jenis) }}</span>
  @endif
@elseif($s)
  <span class="cap {{ $s->cap() }}">{{ $s->value }} · {{ $s->pendek() }}</span>
@endif
