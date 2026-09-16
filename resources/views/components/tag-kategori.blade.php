@props(['kat' => null, 'polos' => false])

@php
  /* `kat` model Referensi kategori internal. Warnanya dari data master, jadi
     menambah kategori tidak perlu menyentuh tampilan. */
  $mati = $kat && ! $kat->aktif;
  $ket = $mati ? 'Kategori ini sudah tidak aktif di data master' : null;
  $w = $kat?->warnaLabel() ?? \App\Enums\WarnaLabel::ABU;
@endphp

@if(! $kat)
  @if($polos)
    <span class="lbl" style="margin:0">belum dipilih</span>
  @else
    <span class="tagkat dalam">belum dipilih</span>
  @endif
@elseif($polos)
  <span class="nilaikat" @if($ket) title="{{ $ket }}" @endif>
    <i style="background:{{ $w->padat() }}"></i>
    <span>{{ $kat->nama }}{{ $mati ? ' · nonaktif' : '' }}</span>
  </span>
@else
  <span class="tagkat" style="background:{{ $w->isi() }};color:{{ $w->teks() }};border-color:{{ $w->garis() }}"
    @if($ket) title="{{ $ket }}" @endif>{{ $kat->nama }}{{ $mati ? ' · nonaktif' : '' }}</span>
@endif
