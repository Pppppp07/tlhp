@extends('rangka')
@section('judul', 'Rekomendasi')
@section('isi')
@php
  use App\Support\Tampil;

  $urut = request('urut', 'kode');
  $arah = request('arah', 'naik');

  $kolom = [
    'uraian'  => 'Uraian',
    'satker'  => 'Satuan kerja',
    'posisi'  => 'Posisi berkas',
    'tenggat' => 'Tenggat jawab',
    'status'  => 'Status',
  ];

  $nilai = [
    'uraian'  => fn ($r) => mb_strtolower($r->uraian),
    'satker'  => fn ($r) => mb_strtolower($r->daftarSasaran()->map(fn ($x) => $x->satker?->nama)->filter()->first() ?? '~'),
    'posisi'  => fn ($r) => $r->posisiTampil()?->tahap() ?? 0,
    'tenggat' => fn ($r) => optional($r->tenggat_jawab)->timestamp ?? 0,
    'status'  => fn ($r) => array_search($r->status, \App\Enums\StatusTindakLanjut::cases(), true),
  ];

  $baris = isset($nilai[$urut])
    ? ($arah === 'turun' ? $daftar->sortByDesc($nilai[$urut]) : $daftar->sortBy($nilai[$urut]))->values()
    : $daftar->sortBy('kode')->values();

  /* Tiga keadaan sekali putar: tekan mengurutkan, tekan lagi membalik, sekali
     lagi kembali ke urutan bawaan. */
  $tautKolom = function ($k) use ($urut, $arah) {
      $q = request()->query();
      if ($urut !== $k)          { $q['urut'] = $k; $q['arah'] = 'naik'; }
      elseif ($arah === 'naik')  { $q['urut'] = $k; $q['arah'] = 'turun'; }
      else                       { unset($q['urut'], $q['arah']); }
      return request()->url() . (($q = http_build_query($q)) ? '?' . $q : '');
  };

  /* Dua deret keping, persis seperti prototipe: yang atas menyempitkan
     lingkupnya, yang bawah memilih keranjangnya. */
  $grup = [
    [
      'kunci' => 'jenis', 'aktif' => $jenis, 'label' => 'Jenis laporan',
      'keping' => [
        ['teks' => 'Semua', 'nilai' => '', 'jumlah' => $jumlahJenis['']],
        ['teks' => 'LHP', 'nilai' => 'LHP', 'jumlah' => $jumlahJenis['LHP']],
        ['teks' => 'LHA', 'nilai' => 'LHA', 'jumlah' => $jumlahJenis['LHA']],
      ],
    ],
    [
      'kunci' => 'keadaan', 'aktif' => $keadaan, 'label' => 'Keranjang',
      'keping' => collect($KERANJANG)->map(fn ($k, $kunci) => [
        'teks' => $k['nama'], 'nilai' => $kunci,
        'jumlah' => $jumlahKeranjang[$kunci] ?? 0,
        'pemisah' => $k['pemisah'] ?? false,
      ])->values()->all(),
    ],
  ];

  $pilih = [];
  if ($daftarSatker->isNotEmpty()) {
      $pilih[] = ['nama' => 'satker', 'nilai' => $satker,
        'opsi' => ['' => 'Semua satuan kerja']
          + $daftarSatker->mapWithKeys(fn ($s) => [$s->id => $s->namaPendek()])->all()];
  }
  $pilih[] = ['nama' => 'status', 'nilai' => $status,
    'opsi' => ['' => 'Semua status']
      + collect($daftarStatus)->mapWithKeys(fn ($s) => [$s->value => $s->value . ' · ' . $s->nama()])->all()];
@endphp

@include('bagian.kepala-tabel', [
  'grup' => $grup,
  'cari' => $cari,
  'ph' => 'Cari nomor LHP, kode, temuan, rekomendasi, Ref LHP',
  'pilih' => $pilih,
  'tampil' => $daftar->count(),
  'semua' => $semuaJumlah,
  'satuan' => 'rekomendasi',
  'awas' => $perlu ? $perlu . ' lewat tenggat' : null,
])

<div class="tw">
  <table>
    <thead>
      <tr>
        <th class="num">No</th>
        @foreach($kolom as $k => $nama)
          <th @class(['urutkan', 'aktif' => $urut === $k])>
            <a href="{{ $tautKolom($k) }}">{{ $nama }}
              <span class="panahurut">{{ $urut === $k ? ($arah === 'naik' ? '↑' : '↓') : '⇅' }}</span>
            </a>
          </th>
        @endforeach
      </tr>
    </thead>
    <tbody>
    @forelse($baris as $i => $r)
      @php
        $telat = $r->lewatTenggat();
        $satkerBaris = $r->daftarSasaran();
      @endphp
      @php $ditandai = $tandai === $r->id; @endphp
      {{-- Sekali klik langsung ke rinciannya, seperti prototipe. Uraiannya
           sendiri tulisan biasa: kalau ia tautan, seluruh kolom jadi biru
           bergaris dan mata kehilangan tempat berpijak — padahal yang bisa
           ditekan justru seluruh barisnya. --}}
      {{-- Rinciannya dibentangkan di dalam barisnya, bukan dibuka sebagai
           halaman lain: yang dibandingkan orang adalah baris-baris ini satu
           sama lain. Yang perlu bertindak menekan "Buka selengkapnya" di
           dalam rinciannya. --}}
      <tr @class(['bukaan', 'tandai' => $ditandai, $nada => $ditandai])
          @if($ditandai) id="r-{{ $r->id }}" @endif
          data-buka="rk{{ $r->id }}">
        <td class="num mono" style="font-size:12px;color:var(--ink-3)">
          <span class="panahbaris"><x-ik nama="panah-kanan" ukuran="13" /></span>
          {{ $i + 1 }}
        </td>

        {{-- Lencana jenis laporan ikut di sini bersama hilangnya kolom Kode —
             ia keterangan atas uraiannya, bukan kolom tersendiri. --}}
        <td style="max-width:420px">
          <div style="display:flex;align-items:baseline;gap:8px">
            <span class="sumber sumber-{{ $r->temuan->laporan->sumber->value }}">{{ $r->temuan->laporan->sumber->value }}</span>
            <span style="font-size:13px">{{ $r->uraian }}</span>
          </div>
          <div class="lbl" style="margin-top:3px">
            {{ $r->temuan->judul }}
            &middot; <span class="mono">{{ $r->kode }}</span>
          </div>
        </td>

        {{-- Seluruh namanya disebut, bukan diringkas jadi angka. "5 satuan
             kerja" memaksa membuka barisnya cuma untuk tahu siapa saja. --}}
        <td style="font-size:12.5px">
          <span class="kepingsatker">
            @forelse($satkerBaris as $x)
              <span class="keping">{{ $x->satker?->namaPendek() }}</span>
            @empty
              <span class="lbl">belum ditugaskan</span>
            @endforelse
          </span>
        </td>

        {{-- Langsung unit yang memegangnya. Kalimat lengkapnya tetap ada di
             gelembung judulnya, jadi tidak ada keterangan yang hilang. --}}
        <td style="font-size:12.5px" title="{{ $r->posisiTampil()?->label() }}">
          {{ $r->pemegangTampil() }}
          @if($satkerBaris->count() > 1)
            <span class="kepingsatker" style="margin-top:4px">
              @foreach($r->sebaranPosisi() as $nama => $n)
                <span class="keping">{{ $nama }} <i>{{ $n }}</i></span>
              @endforeach
            </span>
          @endif
        </td>

        <td class="mono" style="font-size:12px;@if($telat)color:var(--verm)@endif">
          {{ Tampil::tgl($r->tenggat_jawab) }}
          @if($telat)<div class="lbl" style="color:var(--verm)">lewat {{ $telat }} hari</div>@endif
        </td>

        <td><span class="cap cap-{{ $r->status->value }}">{{ $r->status->value }}</span></td>
      </tr>

      <tr class="lebar" id="rk{{ $r->id }}">
        <td colspan="{{ count($kolom) + 1 }}">
          @include('bagian/rinci-rekomendasi', ['r' => $r])
        </td>
      </tr>
    @empty
      <tr><td colspan="6" style="color:var(--ink-3);padding:22px">
        Tidak ada rekomendasi yang cocok. Ubah kata kunci atau saringan.
      </td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
