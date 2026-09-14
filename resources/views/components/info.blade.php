@props(['teks'])

@php
  /* Boleh satu kalimat atau beberapa poin. Kalau beberapa, tiap poin berdiri
     sendiri — keterangan panjang yang dijejalkan jadi satu paragraf tidak ada
     yang membacanya sampai habis. */
  $poin = is_array($teks) ? $teks : [$teks];
  $penuh = implode(' ', $poin);
@endphp

{{-- `title` bukan hiasan: kalau JavaScript mati, itulah satu-satunya cara isi
     keterangan ini tetap bisa dibaca. --}}
<span class="infoikon">
  <button type="button" title="{{ $penuh }}" aria-label="Keterangan">!</button>
  <span class="isi" role="note">
    @foreach($poin as $p)<span>{{ $p }}</span>@endforeach
  </span>
</span>
