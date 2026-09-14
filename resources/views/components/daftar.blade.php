@props([
  'id', 'judul', 'isi', 'ket' => null,
  'tutup' => false,          // bawaannya tertutup
  'kelompok' => null,        // 'pegang' — dipecah menurut siapa yang memegang
  'disorot' => false, 'tandai' => [], 'nada' => 'aksen',
])

@php
  /* Terjemahan komponen `Daftar` di prototipe. Dipakai <details>, bukan tombol
     ber-JavaScript: melipat daftar adalah hal yang sudah bisa dilakukan HTML
     sendiri, dan yang bisa dikerjakan tanpa skrip sebaiknya tidak memakai skrip.

     Dibuka paksa saat sedang ditunjuk — kalau tetap tertutup, penandanya
     menyorot kepala daftar tanpa memperlihatkan isinya. */
  $adaTanda = $disorot && $isi->contains(fn ($r) => in_array($r->id, $tandai, true));
  $buka = ! $tutup || $adaTanda;

  /* Dibaca lewat posisiTampil(): kolom posisi rekomendasi bernilai NULL
     selama tingkat 1 berjalan, dan membacanya mentah membuat seluruh daftar
     jatuh ke satu kelompok "tidak dipegang siapa pun". */
  $grup = $kelompok === 'pegang'
    ? $isi->groupBy(fn ($r) => $r->posisiTampil()?->sebutanPemegang() ?? 'Belum ditugaskan')
    : null;
@endphp

<section id="blok-{{ $id }}" style="margin-bottom:20px;scroll-margin-top:96px"
  @class(['sorot-bagian' => $disorot])>
  <details @if($buka) open @endif>
    <summary class="blokjudul">
      <span class="panah">&rsaquo;</span>
      {{ $judul }}
      <span class="n">{{ $isi->count() }}</span>
    </summary>

    @if($ket)
      <p style="margin:0 0 12px;font-size:12.5px;color:var(--ink-3);line-height:1.5">{{ $ket }}</p>
    @endif

    @if($isi->isEmpty())
      <div class="kosong">Tidak ada.</div>
    @elseif($grup)
      {{-- Dipecah menurut siapa yang memegang berkasnya. Tanpa ini "Sedang
           menunggu" jadi satu tumpukan panjang yang tidak memberi tahu
           menunggu siapa. --}}
      @foreach($grup as $nama => $anggota)
        @php
          $telatGrup = $anggota->filter(fn ($r) => $r->lewatTenggat() > 0)->count();
          $bukaGrup = $disorot && $anggota->contains(fn ($r) => in_array($r->id, $tandai, true));
        @endphp
        <details class="kelompok" @if($bukaGrup) open @endif>
          <summary>
            <span class="panah">&rsaquo;</span>
            {{ $nama }}
            <span class="n">{{ $anggota->count() }}</span>
            @if($telatGrup)<span class="telat">{{ $telatGrup }} lewat tenggat</span>@endif
          </summary>
          <div style="display:grid;gap:8px;padding:10px 0 4px">
            @foreach($anggota as $r)
              @include('bagian/kartu-rekomendasi', [
                'r' => $r,
                'ditandai' => $disorot && in_array($r->id, $tandai, true),
                'nada' => $nada,
              ])
            @endforeach
          </div>
        </details>
      @endforeach
    @else
      <div style="display:grid;gap:8px">
        @foreach($isi as $r)
          @include('bagian/kartu-rekomendasi', [
            'r' => $r,
            'ditandai' => $disorot && in_array($r->id, $tandai, true),
            'nada' => $nada,
          ])
        @endforeach
      </div>
    @endif
  </details>
</section>
