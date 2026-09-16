@props(['rek'])

@php
  $u = auth()->user();
  $jenis = $rek->jenis();
  $k = \App\Support\TindakLanjutRingkas::kemajuan($rek, $u->peran, $u->satker_id);
@endphp

@if(! $k['dari'])
  <span class="lbl">—</span>
@else
  <div class="kemajuan">
    <b>{{ $k['selesai'] }} dari {{ $k['dari'] }} selesai</b>
    <span class="batang" aria-hidden="true"><i style="width:{{ round($k['selesai'] / $k['dari'] * 100) }}%"></i></span>
    <span class="lbl">
      @if($jenis->melewatiSiptl())
        Rangkuman SIPTL: {{ $k['rangkuman'] }}
      @else
        Rangkuman Itjen: {{ \App\Enums\HasilTelaah::from($k['rangkuman'])->nama($jenis) }}
      @endif
    </span>
  </div>
@endif
