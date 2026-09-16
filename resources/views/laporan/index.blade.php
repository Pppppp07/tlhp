@extends('rangka')
@section('judul', 'Daftar laporan')
@section('isi')
@php
  use App\Models\Laporan;
  use App\Support\Tampil;

  $berikut = fn ($k) => $urut !== $k ? "{$k}:naik" : ($arah === 'naik' ? "{$k}:turun" : 'perhatian');
  $judulUrut = fn ($k, $nama) => $urut !== $k ? 'Urutkan menurut '.mb_strtolower($nama)
    : ($arah === 'naik' ? 'Tekan lagi untuk membalik urutannya' : 'Tekan lagi untuk kembali ke urutan bawaan');
  /* Tiga ukuran laporan berdampingan sebagai angka, lalu satuan kerja paling
     kanan — yang isinya berkeping-keping ditaruh paling akhir. */
  $KOLOM = [['nomor', 'Laporan', false], ['diterima', 'Diterima', false],
    ['temuan', 'Jumlah temuan', true], ['rekomendasi', 'Jumlah rekomendasi', true],
    ['dana', 'Dana dipulihkan', true], ['satker', 'Satuan kerja terperiksa', false]];
@endphp

<div class="body">
  <form id="saring" method="get" action="{{ route('laporan.index') }}" class="kepalatabel">
    <input type="hidden" name="keadaan" value="{{ $keadaan }}">
    <input type="hidden" name="urut" value="{{ $urut === 'perhatian' ? 'perhatian' : $urut.':'.$arah }}">

    <div class="pilihgrup" role="group" aria-label="Keadaan laporan">
      @foreach($KEADAAN as $k => $v)
        @php $nyala = $keadaan === $k; @endphp
        <button type="submit" name="keadaan" value="{{ $nyala ? 'semua' : $k }}" aria-pressed="{{ $nyala ? 'true' : 'false' }}"
          @if($nyala) title="Tekan lagi untuk melihat semuanya" @endif>
          {{ $v['nama'] }}<span class="n">{{ $jumlah[$k] }}</span>
          @if($nyala && $k !== 'semua')<x-ikon n="X" :s="12" class="lepas" />@endif
        </button>
      @endforeach
    </div>

    <div class="barissaring">
      <span class="cari" style="flex:1;min-width:220px">
        <x-ikon n="Search" :s="14" />
        <input type="text" name="cari" value="{{ $cari }}" data-saring-langsung
          placeholder="Cari nomor surat, satuan kerja, atau judul temuan">
      </span>
    </div>

    <div class="hint" data-hitung-tampil>
      <span data-n-tampil>{{ $hasil->count() }}</span> laporan tampil
      <b style="color:var(--bad)" @if(! $perlu) hidden @endif data-perlu> · <span data-n-perlu>{{ $perlu }}</span> rekomendasi perlu perhatian</b>
      @if($hasil->count() !== $semua->count())<span> · disaring dari {{ $semua->count() }}</span>@endif
    </div>
  </form>

  <div class="tw">
    <table>
      <thead>
        <tr>
          <th class="num">No</th>
          @foreach($KOLOM as [$k, $nama, $angka])
            <th class="urutkan{{ $angka ? ' num' : '' }}{{ $urut === $k ? ' aktif' : '' }}">
              <button type="submit" form="saring" name="urut" value="{{ $berikut($k) }}" title="{{ $judulUrut($k, $nama) }}">
                <span>{{ $nama }}</span>
                <span class="panahurut" aria-hidden="true">{{ $urut === $k ? ($arah === 'naik' ? '↑' : '↓') : '⇅' }}</span>
              </button>
            </th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($hasil as $no => $lap)
          @php
            $a = $lap->angkaTersimpan;
            $j = $lap->jedaPencatatan();
            $cariTeks = mb_strtolower(implode(' ', [$lap->nomor, $lap->satkerDiperiksa()->map->nama->join(' '), $lap->sumber->nama(), $lap->temuan->map->judul->join(' ')]));
          @endphp
          <tr id="lap-{{ $lap->id }}" class="bukaan{{ $a['perlu'] > 0 ? ' awas' : '' }}" data-href="{{ route('laporan.show', $lap) }}"
            data-cari="{{ $cariTeks }}" data-perlu="{{ $a['perlu'] }}">
            <td class="num mono" style="font-size:12px;color:var(--ink-3)">
              <span class="panahbaris"><x-ikon n="ChevronRight" :s="13" /></span>
              <span data-no>{{ $no + 1 }}</span>
            </td>
            <td style="max-width:260px">
              <div style="display:flex;align-items:baseline;gap:8px">
                <x-sumber :j="$lap->sumber" />
                <a class="tautbaris mono" href="{{ route('laporan.show', $lap) }}" style="font-size:12.5px">{{ $lap->nomor }}</a>
              </div>
              <div class="lbl" style="margin-top:3px">{{ $lap->sumber->nama() }} — {{ $lap->sumber->penerbit() }}</div>
            </td>
            <td class="mono" style="font-size:12px">
              {{ Tampil::tgl($lap->tgl_terima) }}
              @if($j !== null && $j < 0)
                <div class="jedacatat salah">dicatat {{ -$j }} hari sebelum diterima</div>
              @elseif($j !== null && $j > Laporan::BATAS_JEDA_CATAT)
                <div class="jedacatat">dicatat {{ $j }} hari kemudian</div>
              @endif
            </td>
            <td class="num" style="font-size:15px;font-weight:600">{{ $a['temuan'] }}</td>
            <td class="num" style="font-size:15px;font-weight:600">
              {{ $a['jml'] }}
              @if($a['hanyaPantau'])<div class="lbl">hanya pemantauan</div>@endif
            </td>
            <td class="num mono" style="font-size:12px">
              @if($a['target'] > 0)
                {{ Tampil::rupiahSingkat($a['masuk']) }}
                <div class="lbl">dari {{ Tampil::rupiahSingkat($a['target']) }}</div>
              @else
                <span class="lbl">—</span>
              @endif
            </td>
            <td style="font-size:12.5px;min-width:260px"><x-daftar-satker :lap="$lap" /></td>
          </tr>
        @endforeach
        <tr data-kosong @if($hasil->isNotEmpty()) hidden @endif>
          <td colspan="7" style="color:var(--ink-3);padding:22px">
            {{ $semua->isEmpty() ? 'Belum ada laporan yang dicatat.' : 'Tidak ada laporan yang cocok. Ubah kata kunci atau saringan.' }}
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
@endsection
