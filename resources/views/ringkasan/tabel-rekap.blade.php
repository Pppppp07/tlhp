@php
  use App\Support\Tampil;

  /* Satu tabel rekap — padanan `TabelRekap`. Kolomnya diberikan pemanggil
     supaya dua bentuk yang penyebutnya berbeda tidak dipaksa jadi satu
     komponen yang tahu segalanya.

     Kolom bertanda `bar` digambar berikut bilahnya — di lembar mereka itu
     kolom "% Selesai" dengan data bar, dan bilah itu yang membuat satuan kerja
     dengan tumpukan terbanyak langsung ketahuan tanpa membaca angkanya satu
     per satu. */
  $sel = function (array $k, array $b) {
    $v = $b[$k['k']] ?? 0;
    if (! empty($k['bar'])) {
      return '<span class="barbagi"><i style="width:'.min(100, (float) $v).'%"></i>'
        .'<b>'.str_replace('.', ',', number_format((float) $v, 1, '.', '')).'%</b></span>';
    }

    return e(! empty($k['rp']) ? Tampil::rupiahSingkat($v) : $v);
  };
@endphp

<section class="panel lebar tabelrekap">
  <div class="kepala">
    <h3>{{ $judul }}</h3>
    <x-info :teks="$ket" />
  </div>
  <div class="tw">
    <table>
      <thead>
        <tr>
          @foreach($kepala as $k)
            <th @class(['num' => ! empty($k['num'])]) @isset($k['ket']) title="{{ $k['ket'] }}" @endisset>{{ $k['nama'] }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($baris as $b)
          <tr>
            @foreach($kepala as $k)
              <td @class(['num' => ! empty($k['num']), 'mono' => ! empty($k['mono'])])>{!! $sel($k, $b) !!}</td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
      @isset($jumlah)
        <tfoot>
          <tr>
            @foreach($kepala as $k)
              <td @class(['num' => ! empty($k['num']), 'mono' => ! empty($k['mono'])])>{!! $sel($k, $jumlah) !!}</td>
            @endforeach
          </tr>
        </tfoot>
      @endisset
    </table>
  </div>
</section>
