@extends('rangka')
@section('judul', 'Daftar laporan')
@section('isi')
@php
  use App\Support\Tampil;

  $urut = request('urut', 'perhatian');
  $arah = request('arah', 'naik');

  /* Tiga ukuran laporan — temuan, rekomendasi, dana — berdiri berdampingan
     sebagai angka rata kanan. Satuan kerja paling kanan: isinya berkeping dan
     panjangnya berbeda tiap baris; ditaruh di tengah, ia mendorong ketiga
     angka itu jadi berjauhan dan tidak lagi bisa dibandingkan sekilas. */
  $kolom = [
    'nomor'       => ['Laporan', false],
    'diterima'    => ['Diterima', false],
    'temuan'      => ['Jumlah temuan', true],
    'rekomendasi' => ['Jumlah rekomendasi', true],
    'dana'        => ['Dana dipulihkan', true],
    'satker'      => ['Satuan kerja terperiksa', false],
  ];

  $nilai = [
    'nomor'       => fn ($l) => $l->nomor,
    'diterima'    => fn ($l) => optional($l->tgl_terima)->timestamp ?? 0,
    'temuan'      => fn ($l) => $l->temuan->count(),
    'rekomendasi' => fn ($l) => $l->angka()['jml'],
    'dana'        => fn ($l) => $l->angka()['masuk'],
    'satker'      => fn ($l) => mb_strtolower($l->satkerDiperiksa()->first()?->nama ?? '~'),
  ];

  $baris = isset($nilai[$urut])
    ? ($arah === 'turun' ? $daftar->sortByDesc($nilai[$urut]) : $daftar->sortBy($nilai[$urut]))->values()
    : $daftar->values();

  $tautKolom = function ($k) use ($urut, $arah) {
      $q = request()->query();
      if ($urut !== $k)          { $q['urut'] = $k; $q['arah'] = 'naik'; }
      elseif ($arah === 'naik')  { $q['urut'] = $k; $q['arah'] = 'turun'; }
      else                       { unset($q['urut'], $q['arah']); }
      return request()->url() . (($q = http_build_query($q)) ? '?' . $q : '');
  };

  $grup = [[
    'kunci' => 'keadaan', 'aktif' => $keadaan, 'label' => 'Keadaan laporan',
    'keping' => [
      ['teks' => 'Masih berjalan', 'nilai' => 'jalan',  'jumlah' => $jumlah['jalan']],
      ['teks' => 'Sudah tuntas',   'nilai' => 'tuntas', 'jumlah' => $jumlah['tuntas']],
      ['teks' => 'Semua',          'nilai' => '',       'jumlah' => $jumlah['semua']],
    ],
  ]];
@endphp

@include('bagian.kepala-tabel', [
  'grup' => $grup,
  'cari' => $cari,
  'ph' => 'Cari nomor surat, satuan kerja, atau judul temuan',
  'tampil' => $daftar->count(),
  'semua' => $jumlah['semua'],
  'satuan' => 'laporan',
  'awas' => $perlu ? $perlu . ' rekomendasi perlu perhatian' : null,
])

<div class="tw">
  <table>
    <thead>
      <tr>
        <th class="num">No</th>
        @foreach($kolom as $k => [$nama, $angka])
          <th @class(['urutkan', 'num' => $angka, 'aktif' => $urut === $k])>
            <a href="{{ $tautKolom($k) }}">{{ $nama }}
              <span class="panahurut">{{ $urut === $k ? ($arah === 'naik' ? '↑' : '↓') : '⇅' }}</span>
            </a>
          </th>
        @endforeach
      </tr>
    </thead>
    <tbody>
    @forelse($baris as $i => $l)
      @php $a = $l->angka(); @endphp
      <tr class="bukaan" onclick="location.href='{{ route('laporan.show', $l) }}'">
        <td class="num mono" style="font-size:12px;color:var(--ink-3)">{{ $i + 1 }}</td>

        <td style="max-width:260px">
          <div style="display:flex;align-items:baseline;gap:8px">
            <span class="sumber sumber-{{ $l->sumber->value }}">{{ $l->sumber->value }}</span>
            <span class="mono" style="font-size:12.5px">{{ $l->nomor }}</span>
          </div>
          <div class="lbl" style="margin-top:3px">
            {{ $l->sumber->nama() }} &mdash; {{ $l->sumber->penerbit() }}
          </div>
        </td>

        {{-- Jaraknya ke tanggal pencatatan disebut di sini kalau lewat dari
             sepekan. Selama surat belum dicatat, tenggat jawabannya sudah
             berjalan tapi belum ada yang bisa mengerjakannya — dan itu tidak
             terlihat kalau angkanya cuma ada di halaman rincian. --}}
        <td class="mono" style="font-size:12px">
          {{ Tampil::tgl($l->tgl_terima) }}
          @php $jeda = $l->jedaPencatatan(); @endphp
          @if($jeda !== null && $jeda < 0)
            {{-- Tidak mungkin: laporannya tercatat sebelum suratnya diterima,
                 jadi salah satu tanggalnya salah. --}}
            <div class="jedacatat salah">dicatat {{ -$jeda }} hari sebelum diterima</div>
          @elseif($jeda !== null && $jeda > \App\Models\Laporan::BATAS_JEDA_CATAT)
            <div class="jedacatat">dicatat {{ $jeda }} hari kemudian</div>
          @endif
        </td>

        {{-- Seberapa besar laporannya. Tiga angka polos — keadaan tiap
             rekomendasi dibaca di layar Rekomendasi, bukan di sini. --}}
        <td class="num" style="font-size:15px;font-weight:600">{{ $a['temuan'] }}</td>

        <td class="num" style="font-size:15px;font-weight:600">
          {{ $a['jml'] }}
          @if($a['jml'] === 0)
            {{-- Satuan kerja yang namanya disebut di temuan tapi tidak
                 kebagian satu rekomendasi pun. Tanpa keterangan ini angka
                 nolnya terbaca seperti laporan kosong. --}}
            <div class="lbl">hanya pemantauan</div>
          @endif
        </td>

        <td class="num mono" style="font-size:12px">
          @if($a['tagihan'] > 0)
            {{ Tampil::rupiahSingkat($a['masuk']) }}
            <div class="lbl">dari {{ Tampil::rupiahSingkat($a['tagihan']) }}</div>
          @else
            <span class="lbl">&mdash;</span>
          @endif
        </td>

        <td style="font-size:12.5px;min-width:260px">
          <span class="kepingsatker">
            @forelse($l->satkerDiperiksa() as $s)
              @php $n = $l->jumlahTemuanSatker($s->id); @endphp
              <span class="keping">{{ $s->namaPendek() }}@if($n > 1)<i>{{ $n }} temuan</i>@endif</span>
            @empty
              <span class="lbl">&mdash;</span>
            @endforelse
          </span>
        </td>
      </tr>
    @empty
      <tr><td colspan="7" style="color:var(--ink-3);padding:22px">
        Tidak ada laporan yang cocok. Ubah kata kunci atau saringan.
      </td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
