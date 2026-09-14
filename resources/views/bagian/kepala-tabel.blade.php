{{-- Kepala tabel: keranjang, kotak cari, dan baris keterangan.

     Bentuknya disalin dari prototipe supaya kedua layar yang memakainya —
     Rekomendasi dan Daftar laporan — tidak perlu dipelajari dua kali.

     Di prototipe kepingnya menyetel keadaan React. Di sini ia tautan yang
     membawa keadaannya di alamat, jadi penyaringannya dikerjakan peladen:
     halamannya tetap berfungsi penuh tanpa JavaScript, dan tampilan yang
     sedang dilihat bisa disalin-kirim apa adanya.

     Butuh:
       $grup    array of ['kunci' => ..., 'keping' => [[teks, jumlah, nilai]], 'aktif' => ...]
       $cari    nilai kotak cari
       $ph      placeholder kotak cari
       $pilih   (opsional) array of ['nama' => ..., 'nilai' => ..., 'opsi' => [nilai => teks]]
       $tampil  berapa baris tampil
       $semua   berapa baris seluruhnya
       $satuan  'rekomendasi' atau 'laporan'
       $awas    (opsional) kalimat merah di baris keterangan
--}}
@php
  /* Alamat untuk satu keping: menyalin seluruh saringan yang sedang berlaku,
     lalu mengganti satu di antaranya. Keping yang sedang menyala menghapus
     kuncinya sendiri — menekannya berarti melepas saringan itu. */
  $taut = function (string $kunci, $nilai) {
      $q = request()->query();
      if ($nilai === null || $nilai === '') { unset($q[$kunci]); }
      else { $q[$kunci] = $nilai; }
      unset($q['page']);
      return request()->url() . (($x = http_build_query($q)) ? '?' . $x : '');
  };
@endphp

<div class="kepalatabel">
  @foreach($grup as $g)
    <div class="pilihgrup" role="group" @isset($g['label']) aria-label="{{ $g['label'] }}" @endisset>
      @foreach($g['keping'] as $k)
        @php
          $nyala = (string) ($g['aktif'] ?? '') === (string) $k['nilai'];
          /* Keping bawaan (nilainya kosong) tidak bisa dilepas — ia justru
             keadaan tanpa saringan. */
          $lepas = $nyala && $k['nilai'] !== '';
        @endphp
        @if(! empty($k['pemisah']))<span class="pemisah"></span>@endif
        <a href="{{ $taut($g['kunci'], $lepas ? null : $k['nilai']) }}"
           aria-pressed="{{ $nyala ? 'true' : 'false' }}"
           @if($lepas) title="Tekan lagi untuk melepas saringan ini" @endif>
          {{ $k['teks'] }}
          @isset($k['jumlah'])<span class="n">{{ $k['jumlah'] }}</span>@endisset
          @if($lepas)<span class="lepas" aria-hidden="true">&times;</span>@endif
        </a>
      @endforeach
    </div>
  @endforeach

  <form method="get" class="barissaring">
    {{-- Saringan keping ikut terbawa saat mencari: kotak cari mempersempit
         apa yang sedang dilihat, bukan menggantinya. --}}
    @foreach(request()->query() as $k => $v)
      @continue($k === 'cari' || in_array($k, array_column($pilih ?? [], 'nama'), true))
      <input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? '' : $v }}">
    @endforeach

    <span class="cari">
      <x-ik nama="cari" ukuran="14" />
      <input type="text" name="cari" value="{{ $cari }}" placeholder="{{ $ph }}">
    </span>

    @foreach($pilih ?? [] as $p)
      <select name="{{ $p['nama'] }}" onchange="this.form.submit()">
        @foreach($p['opsi'] as $nilai => $teks)
          <option value="{{ $nilai }}" @selected((string) $p['nilai'] === (string) $nilai)>{{ $teks }}</option>
        @endforeach
      </select>
    @endforeach

    {{-- Tetap ada supaya penyaringnya bisa dipakai tanpa JavaScript: tanpa
         tombol ini, mengganti pemilih tidak pernah terkirim. --}}
    <button class="btn" type="submit">Saring</button>
  </form>

  <div class="hint">
    {{ $tampil }} {{ $satuan }} tampil
    @if(! empty($awas))<b style="color:var(--bad)"> &middot; {{ $awas }}</b>@endif
    @if($tampil !== $semua) &middot; disaring dari {{ $semua }} @endif
  </div>
</div>
