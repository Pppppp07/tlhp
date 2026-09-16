@props(['isi', 'kelas' => null])

{{-- Daftar keterangan bersusun ke bawah. Tiap butir: `l` namanya, `v` isinya
     (HTML yang sudah aman), `k` keterangan tambahan di belakang isi, `ket`
     keterangan panjang di balik ikon Info, `mono` untuk angka dan tanggal,
     `c` warna. Butir bernilai palsu dilewati. --}}
<dl class="fakta{{ $kelas ? ' '.$kelas : '' }}">
  @foreach(array_filter($isi) as $m)
    <div>
      <dt>{{ $m['l'] }}@if(! empty($m['ket']))<x-info :teks="$m['ket']" />@endif</dt>
      <dd>
        <span @class(['mono' => ! empty($m['mono'])]) @if(! empty($m['c'])) style="color:{{ $m['c'] }}" @endif>{!! $m['v'] !!}</span>
        @if(! empty($m['k']))<span class="ket"> &middot; {{ $m['k'] }}</span>@endif
      </dd>
    </div>
  @endforeach
</dl>
