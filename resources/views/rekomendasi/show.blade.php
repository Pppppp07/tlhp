@extends('rangka')
@section('judul', 'Rincian rekomendasi')
@section('isi')
@php
  use App\Enums\HasilTelaah;
  use App\Enums\PeranPengguna as P;
  use App\Support\Tampil;

  $u = auth()->user();
  $peran = $u->peran;
  $balai = $peran === P::SATKER;
  $jenis = $lap->sumber;
  $baris = $r->daftarSasaran();
@endphp

<div class="body">
  <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
    <a class="taut" href="{{ $kembali }}"><x-ikon n="ArrowLeft" :s="16" /> Kembali</a>
    <div style="flex:1"></div>
    <a class="btn" href="{{ route('laporan.show', $lap) }}"><x-ikon n="Files" :s="15" /> Lihat laporan lengkap</a>
  </div>

  <div class="babak">
    <span class="no">1</span>
    <b>Laporan dan temuan</b>
    <x-info teks="Surat asalnya dan isi temuan yang melahirkan rekomendasi ini. Ketentuan yang mengikat satuan kerja ada di bagian Tindak lanjut di bawah." />
  </div>

  <div class="card lipatsatu" style="margin-bottom:20px">
    {{-- ---- laporan asal ---- --}}
    <div>
      <div class="judulkartu">
        <span class="ic-kotak"><x-ikon n="Files" :s="17" /></span>
        <h3>Laporan asal</h3>
      </div>
      @php
        $asli = $lap->suratAsli();
        $faktaLaporan = [
          ['l' => 'Nomor surat', 'v' => e($lap->nomor), 'mono' => true],
          ['l' => 'Diterbitkan', 'v' => e($jenis->penerbit())],
          ['l' => 'Diterima', 'v' => Tampil::tgl($lap->tgl_terima)],
          ['l' => 'Satuan kerja terperiksa', 'v' => $balai ? e($u->satker->namaPendek()) : view('components.daftar-satker', ['lap' => $lap])->render()],
          /* Surat aslinya tidak diberikan ke satuan kerja: satu surat memuat
             seluruh temuan pada seluruh satuan kerja. */
          ! $balai ? ['l' => 'Berkas surat asli', 'v' => $asli
              ? view('components.berkas', ['b' => $asli, 'jenis' => 'Surat laporan pemeriksaan', 'oleh' => 'Setba', 'tanggal' => $lap->tgl_terima])->render()
              : '<span class="belumada">belum ditautkan</span>'] : null,
        ];
      @endphp
      <x-fakta :isi="$faktaLaporan" />
    </div>

    {{-- ---- uraian temuan ---- --}}
    <div class="ruas">
      <div class="judulkartu">
        <span class="ic-kotak abu"><x-ikon n="FileText" :s="17" /></span>
        <h3>Uraian temuan</h3>
      </div>
      @php
        $faktaTemuan = [
          ['l' => 'Kategori temuan', 'ket' => [
              $jenis->melewatiSiptl()
                ? 'Penggolongan dari BPK, mengikuti bagian laporan keuangan yang kena dampaknya.'
                : 'Penggolongan dari Inspektorat, mengikuti sudut pemeriksaannya.',
              'Tertulis apa adanya dari surat laporannya, jadi tidak bisa diubah sendiri.',
              'Dipakai saat berkoordinasi dengan pemeriksa, dan saat menyusun rekap yang mereka minta.',
            ],
            'v' => '<span class="nilaikat"><i style="background:var(--aksen)"></i><span>'.e($tem->kategori?->nama).'</span></span>'],
          $tem->kategoriIntern ? ['l' => 'Kategori internal', 'ket' => [
              'Penggolongan kita sendiri, dipakai mengelompokkan temuan sejenis untuk rekap internal.',
              'Tidak ada di surat pemeriksaannya — diisi dan disetel Setba saat mencatat Laporan Baru.',
              'Daftarnya bisa ditambah dan diganti nama lewat menu Data master.',
            ], 'v' => view('components.tag-kategori', ['kat' => $tem->kategoriIntern, 'polos' => true])->render()] : null,
          ['l' => 'Sebab', 'v' => e($tem->sebab)],
          ['l' => 'Akibat', 'v' => e($tem->akibat)],
          $saudara->isNotEmpty() ? ['l' => 'Rekomendasi lain', 'ket' => [
              'Rekomendasi lain yang lahir dari temuan yang sama.',
              'Satu temuan bisa melahirkan beberapa rekomendasi, dan tiap rekomendasi bisa ditujukan ke satuan kerja yang berbeda — misalnya satu menyetor uangnya, satu lagi membenahi prosedurnya.',
              'Bisa ditekan untuk berpindah ke rekomendasi tersebut.',
            ], 'v' => view('rekomendasi.bagian.saudara', ['saudara' => $saudara, 'jenis' => $jenis])->render()] : null,
        ];
      @endphp
      <x-fakta :isi="$faktaTemuan" />
    </div>
  </div>

  <div class="babak">
    <span class="no">2</span>
    <b>Tindak lanjut</b>
    <x-info teks="Siapa mengerjakan apa, sudah sampai mana, dan statusnya." />
  </div>

  @if($baris->isNotEmpty())
    <div id="r-tindaklanjut" class="wadahtl" style="border:1px solid var(--line-2);border-radius:10px;overflow:hidden">
      @include('rekomendasi.bagian.kepala')

      <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--surface-2);border-bottom:1px solid var(--line-2);flex-wrap:wrap">
        <span class="lbl" style="margin:0">{{ $balai ? 'Yang harus saya kerjakan' : 'Rincian tindak lanjut' }}</span>
        <div style="flex:1"></div>
        <span style="font-size:12.5px;font-weight:700">
          {{ $baris->filter(fn ($x) => $x->hasil === HasilTelaah::M)->count() }} dari {{ $baris->count() }} {{ mb_strtolower(HasilTelaah::M->nama($jenis)) }}
        </span>
        <x-cap-hasil :jenis="$jenis" :hasil="$r->keadaanUnor()" />
        <x-info :teks="$balai ? [
          'Yang ditampilkan hanya kewajiban satuan kerja ini. Rekomendasi yang sama bisa membebani satuan kerja lain, dan bagian mereka bukan urusan di sini.',
          'Satu satuan kerja yang kena dua tindak lanjut memikul dua kewajiban, dan keduanya harus tuntas sendiri-sendiri.',
        ] : [
          'Rekomendasi baru dinilai '.mb_strtolower(HasilTelaah::M->nama($jenis)).' kalau seluruh penugasan di dalamnya sudah '.mb_strtolower(HasilTelaah::M->nama($jenis)).'.',
          'Dua dari tiga selesai tetap terhitung '.mb_strtolower(HasilTelaah::BM->nama($jenis)).' — tapi yang belum tetap disebut namanya di sini supaya bisa dikejar.',
          'Satu satuan kerja yang kena dua tindak lanjut terhitung dua penugasan, karena keduanya memang harus tuntas sendiri-sendiri.',
        ]" />
      </div>

      @foreach($r->tindakan as $k => $tk)
        @include('rekomendasi.bagian.tiket', ['tk' => $tk, 'k' => $k])
      @endforeach

      @if($r->nilaiRek() > 0 && ! $balai)
        <div style="display:flex;gap:10px;align-items:center;padding:10px 14px;border-top:1px solid var(--line-2);background:var(--surface-2)">
          <span class="lbl" style="margin:0">Jumlah</span>
          <div style="flex:1"></div>
          <span class="mono" style="font-size:13px;font-weight:700">{{ Tampil::rupiah($r->nilaiRek()) }}</span>
        </div>
      @endif
    </div>
  @endif

  @include('rekomendasi.bagian.urusan-siptl')

  <div id="r-perkembangan" class="bagian">
    @include('rekomendasi.bagian.riwayat-status')
  </div>

  @include('rekomendasi.bagian.arsip-jejak')
</div>
@endsection
