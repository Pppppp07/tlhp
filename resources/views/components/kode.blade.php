@props(['ket' => 'Kode', 'isi'])

{{-- Kode sistem tidak menyebut dirinya sendiri. REK-2026-014.1 yang berdiri
     telanjang gampang tertukar dengan nomor rekomendasi pada laporan — padahal
     yang satu penomoran pemeriksa, yang satu nomor urut di aplikasi ini.
     Keterangannya ditempel jadi satu supaya keduanya terbaca sebagai satu
     benda, bukan dua kata yang kebetulan bersebelahan. --}}
<span class="kode">
  <span class="k">{{ $ket }}</span>
  <span class="mono i">{{ $isi }}</span>
</span>
