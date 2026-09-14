{{-- Satu tabel rekap. Kolomnya diberikan pemanggil supaya dua bentuk yang
     penyebutnya berbeda tidak dipaksa jadi satu bagian yang tahu segalanya.

     Kolom bertanda `bar` digambar berikut bilahnya — di lembar mereka itu kolom
     "% Selesai" dengan data bar, dan bilah itu yang membuat satuan kerja dengan
     tumpukan terbanyak langsung ketahuan tanpa membaca angkanya satu per satu.
     Angkanya tetap dicetak di atas bilahnya: bilah tanpa angka memaksa mata
     menaksir, dan yang ditanya orang angkanya. --}}
@php use App\Support\Tampil; @endphp

@php
  $sel = function ($k, $baris) {
    if (! empty($k['bar'])) {
      $v = (float) ($baris[$k['k']] ?? 0);
      return '<span class="barbagi"><i style="width:' . min(100, round($v, 1)) . '%"></i>'
        . '<b>' . number_format($v, 1, ',', '.') . '%</b></span>';
    }
    $v = $baris[$k['k']] ?? '';
    return e(! empty($k['rp']) ? Tampil::rupiahSingkat((int) $v) : $v);
  };
@endphp

<section class="kartu tabelrekap">
  <div class="judulkartu" style="padding:14px 17px 10px;margin:0">
    <h3>{{ $judul }}</h3>
    <x-info :teks="$ket" />
  </div>

  <div class="tw">
    <table>
      <thead>
        <tr>
          @foreach($kepala as $k)
            <th @class(['num' => ! empty($k['num'])])>{{ $k['nama'] }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($baris as $b)
          <tr>
            @foreach($kepala as $k)
              <td @class(['num' => ! empty($k['num']), 'mono' => ! empty($k['mono'])])>
                {!! $sel($k, $b) !!}
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
      @isset($jumlah)
        <tfoot>
          <tr>
            @foreach($kepala as $k)
              <td @class(['num' => ! empty($k['num']), 'mono' => ! empty($k['mono'])])>
                {!! $sel($k, $jumlah) !!}
              </td>
            @endforeach
          </tr>
        </tfoot>
      @endisset
    </table>
  </div>
</section>
