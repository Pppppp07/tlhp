@extends('rangka')
@section('judul', 'Ringkasan')
@section('isi')
@php
  use App\Enums\HasilTelaah;
  use App\Enums\SumberLaporan;
  use App\Support\Tampil;
  use App\Support\PetaData;

  /* Warna keadaan, bukan warna identitas. Vermilion dan brass di palet ini
     hanya terpisah ΔE 4,1 di mata yang buta warna merah-hijau — praktis kembar.
     Karena itu tiap potongan status selalu ditempeli kodenya sendiri: yang
     membedakan hurufnya, bukan warnanya. */
  $warna = ['BT' => 'var(--brass)', 'BS' => 'var(--verm)', 'SS' => 'var(--stamp)', 'TD' => 'var(--slate)'];
  $urutStatus = ['BT', 'BS', 'SS', 'TD'];
  $namaStatus = ['BT' => 'Belum ditindaklanjuti', 'BS' => 'Belum sesuai',
                 'SS' => 'Sudah sesuai', 'TD' => 'Tidak dapat ditindaklanjuti'];

  $sumber = SumberLaporan::tryFrom($jenisUtama) ?? SumberLaporan::LHP;
  $kataM  = HasilTelaah::M->nama($sumber);
  $kataBM = HasilTelaah::BM->nama($sumber);
@endphp

{{-- Judulnya jumlah yang BELUM MEMADAI, bukan yang terlambat.

     Mbak Puspi menolak keterlambatan ditonjolkan: "kayaknya enggak perlu
     dimunculin ... di bagian bawah aja enggak apa-apa." Alasannya disebut Mas
     Naufal — "Pusat 4 tuh udah berapa tahun tuh, udah 4 tahun." Temuan lama
     tidak akan pernah berhenti terlambat, jadi kepalanya akan merah selamanya,
     dan merah yang tidak pernah berubah berhenti dibaca orang.

     Gantinya angka yang memang mereka pakai memutuskan. Catatan lembar mereka:
     "Fokus utama dapat diarahkan pada Satker dengan backlog 'Belum Selesai'
     tertinggi." --}}
<div class="kartu" style="margin-bottom:16px;display:flex;gap:16px;align-items:baseline;flex-wrap:wrap">
  <span class="mono" style="font-size:30px;font-weight:700;line-height:1">{{ $belumMemadai }}</span>
  <span style="font-size:14px;font-weight:600">
    rekomendasi {{ mb_strtolower($kataBM) }}
    <span class="lbl" style="display:block;margin-top:4px;font-weight:400;line-height:1.5">
      dari {{ $total }} rekomendasi pada {{ $jumlahLaporan }} laporan
      @if($tumpukan)
        &middot; tumpukan terbanyak di {{ $tumpukan['satker'] }} ({{ $tumpukan['belum'] }} penugasan)
      @endif
    </span>
  </span>
</div>

{{-- Lingkup laporan di paling atas, sebelum apa pun yang lain.

     Mbak Puspi: "di atasnya mungkin, jadi sebelum milih ini dia mau LHP laporan
     pemeriksaan apa." Gabungannya sengaja dipertahankan — "jangan dihilangin
     juga, jadi ini buat kayak monev-nya."

     Berupa tautan, bukan tombol berskrip: tanpa JavaScript pun tetap jalan, dan
     lingkup yang sedang dilihat ikut di alamat halaman sehingga bisa disalin. --}}
<div class="pilihgrup" role="group" aria-label="Jenis laporan" style="margin-bottom:14px">
  @foreach([['semua', 'Semua'], ['LHP', 'LHP'], ['LHA', 'LHA']] as [$k, $n])
    <a href="{{ route('ringkasan', array_merge(request()->query(), ['lingkup' => $k])) }}"
      @if($lingkup === $k) aria-current="page" @endif
      title="{{ $k === 'semua' ? 'Kedua jenis laporan sekaligus — dipakai untuk monev'
        : 'Hanya '.SumberLaporan::from($k)->nama().' dari '.SumberLaporan::from($k)->penerbit() }}">
      {{ $n }}<span class="n">{{ $jumlahJenis[$k] }}</span>
    </a>
  @endforeach
</div>

{{-- Satu baris keterangan lingkup, sepadan dengan baris "Sumber data: PTL
     BPSDM · Tanggal pengolahan ..." di kepala lembar mereka. Yang membaca angka
     harus tahu angka apa yang sedang dibacanya. --}}
<div class="asalangka">
  {{ $jumlahLaporan }} laporan pemeriksaan
  @if($jumlahLhp && $jumlahLhp < $jumlahLaporan)
    &middot; {{ $jumlahLhp }} LHP dan {{ $jumlahLaporan - $jumlahLhp }} LHA
  @elseif($jumlahLaporan)
    &middot; seluruhnya {{ $jumlahLhp ? 'LHP' : 'LHA' }}
  @endif
  &middot; {{ $jumlahTemuan }} temuan bernilai {{ Tampil::rupiahSingkat($nilaiTemuan) }}
  <x-info :teks="[
    'Jumlah rekomendasi dihitung sekali per Ref IDT, sama seperti rekap lembar pemantauan.',
    'Nilai temuan dihitung sekali per temuan — satu temuan bisa melahirkan empat rekomendasi, dan menjumlahkannya empat kali melipatgandakan angka yang dilaporkan ke atas.',
  ]" />
</div>

{{-- Dua blok bertumpuk, persis susunan dasbor mereka. Tiap keranjang punya dua
     angka: jumlahnya dan rupiahnya. Itu "dua parameter" yang disebut Bang
     Kamal: "cuma ada dua parameter kan? Total rekomendasi sama total nilai
     rekomendasi."

     Blok BPK hilang kalau lingkupnya LHA — LHA tidak pernah masuk SIPTL, jadi
     menggambar pitanya berarti memajang keranjang yang tidak pernah bisa
     terisi. --}}
@if($adaLhp)
  @include('bagian/blok-status', [
    'judul' => 'Menurut BPK · SIPTL',
    'ket' => 'Putusan resmi di SIPTL, disalin Setba. Menghitung seluruh unit organisasi Kementerian, bukan BPSDM saja. Hanya LHP — LHA berhenti di Inspektorat.',
    'total' => $jumlahBpk,
    'totalRp' => $nilaiBpk,
    /* Urutannya SS, BS, BT — dari yang paling beres. Itu urutan lembar mereka,
       dan yang dibaca duluan memang kabar baiknya. */
    'kolom' => collect(['SS', 'BS', 'BT', 'TD'])
      ->filter(fn ($k) => $statusJml[$k] > 0 || $k !== 'TD')
      ->map(fn ($k) => ['nama' => $k.' · '.$namaStatus[$k], 'n' => $statusJml[$k],
                        'rp' => $statusRp[$k], 'warna' => $warna[$k]])->values(),
    'sisa' => $sisaBpk,
    'beda' => $beda,
    'tugas' => null,
  ])
@endif

@include('bagian/blok-status', [
  'judul' => 'Menurut BPSDM · verifikasi Inspektorat',
  'ket' => 'Keadaan bagian BPSDM saja: memadai kalau seluruh satuan kerja pada rekomendasi itu sudah memadai — status terburuk menang, sama seperti kolom Status Rekomendasi Unor di lembar pemantauan.',
  'total' => $total,
  'totalRp' => $nilaiRek,
  'kolom' => collect([
    ['nama' => $kataM,  'n' => $unorJml['M'],  'rp' => $unorRp['M'],  'warna' => 'var(--stamp)'],
    ['nama' => $kataBM, 'n' => $unorJml['BM'], 'rp' => $unorRp['BM'], 'warna' => 'var(--verm)'],
  ]),
  'sisa' => $sisaItjen,
  'beda' => null,
  'tugas' => $tugas,
  'kataM' => $kataM,
  'kataBM' => $kataBM,
])

{{-- Dua tabel rekap yang mereka pakai menghadap ke luar. Ditaruh tetap, bukan
     sebagai panel yang bisa diatur: bentuk yang dipakai melapor tidak boleh
     bisa terganti tidak sengaja.

     PENYEBUTNYA BERBEDA, dan itu yang paling gampang salah baca —
     per satuan kerja menghitung PENUGASAN, per tahun menghitung REKOMENDASI. --}}
@include('bagian/tabel-rekap', [
  'judul' => 'Pemantauan per satuan kerja',
  'ket' => [
    'Satu baris satu satuan kerja. Yang dihitung PENUGASAN — satu satuan kerja pada satu bentuk tindak lanjut — jadi jumlahnya lebih besar daripada jumlah rekomendasi.',
    'Di lembar pemantauan mereka tabel ini berjudul "Reff IDT per Satker" dan totalnya 312, bukan 123.',
    'Diurutkan dari yang tumpukan belum selesainya paling banyak. Itu memang gunanya — catatan mereka sendiri berbunyi "Fokus utama dapat diarahkan pada Satker dengan backlog Belum Selesai tertinggi."',
    'Sisa nilai bukan uang yang belum disetor, melainkan nilai yang belum diakui.',
  ],
  'kepala' => [
    ['k' => 'satker', 'nama' => 'Satuan kerja'],
    ['k' => 'tugas', 'nama' => 'Penugasan', 'num' => true],
    ['k' => 'memadai', 'nama' => $kataM, 'num' => true],
    ['k' => 'belum', 'nama' => $kataBM, 'num' => true],
    ['k' => 'persen', 'nama' => '% '.mb_strtolower($kataM), 'bar' => true],
    ['k' => 'sisaItjen', 'nama' => 'Sisa nilai · Inspektorat', 'num' => true, 'mono' => true, 'rp' => true],
    ['k' => 'sisaBpk', 'nama' => 'Sisa nilai · SIPTL', 'num' => true, 'mono' => true, 'rp' => true],
  ],
  'baris' => $perSatker['baris'],
  'jumlah' => $perSatker['total'],
])

@include('bagian/tabel-rekap', [
  'judul' => 'Rekap per tahun LHP',
  'ket' => [
    'Satu baris satu tahun surat pemeriksaan. Yang dihitung REKOMENDASI — satu Ref IDT sekali, berapa pun satuan kerjanya.',
    'SS, BS, dan BT milik BPK dan hanya berlaku untuk LHP — itu sebabnya ada kolom "dari LHP" sebagai penyebutnya. Ketiganya berjumlah sama dengan kolom itu, bukan dengan jumlah rekomendasi.',
    'Dari tabel inilah terbaca umur tumpukannya: tahun lama biasanya sudah lunas, sementara yang belum ditindaklanjuti menumpuk di tahun terakhir.',
  ],
  'kepala' => [
    ['k' => 'tahun', 'nama' => 'Tahun LHP', 'mono' => true],
    ['k' => 'jml', 'nama' => 'Rekomendasi', 'num' => true],
    ['k' => 'keSiptl', 'nama' => 'dari LHP', 'num' => true],
    ['k' => 'SS', 'nama' => 'SS', 'num' => true],
    ['k' => 'BS', 'nama' => 'BS', 'num' => true],
    ['k' => 'BT', 'nama' => 'BT', 'num' => true],
    /* TD cuma ditampilkan kalau ada isinya. Tanpa kolom ini SS + BS + BT tidak
       menutup penyebut "dari LHP"; dengan kolom kosong ia cuma jadi lajur nol
       yang harus dilewati mata. */
    ...($perTahun['total']['TD'] ?? 0) > 0
      ? [['k' => 'TD', 'nama' => 'TD', 'num' => true]] : [],
    ['k' => 'memadai', 'nama' => $kataM, 'num' => true],
    ['k' => 'belum', 'nama' => $kataBM, 'num' => true],
    ['k' => 'nilai', 'nama' => 'Nilai rekomendasi', 'num' => true, 'mono' => true, 'rp' => true],
    ['k' => 'sisaBpk', 'nama' => 'Sisa · SIPTL', 'num' => true, 'mono' => true, 'rp' => true],
    ['k' => 'sisaItjen', 'nama' => 'Sisa · Inspektorat', 'num' => true, 'mono' => true, 'rp' => true],
  ],
  'baris' => $perTahun['baris'],
  'jumlah' => $perTahun['total'],
])

<div style="display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin:26px 0 14px">
  <h2 style="margin:0;font-size:16px;font-weight:700">Peta data</h2>
  <span class="lbl" style="flex:1 1 240px;line-height:1.55">
    Tiap panel bisa diganti sendiri: dikelompokkan menurut apa, dihitung apa, digambar bentuk apa.
    Pilihannya ikut di alamat halaman, jadi tampilan yang sedang dilihat bisa disalin dan dikirim.
  </span>
  @if(request()->query())
    <a class="btn btn-s" href="{{ route('ringkasan') }}">Kembalikan ke susunan awal</a>
  @endif
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:14px;margin-bottom:26px">
  @foreach($panel as $p)
    @include('bagian/panel-peta', ['p' => $p, 'semua' => $panel, 'warna' => $warna])
  @endforeach
</div>

@if($menungguDok->isNotEmpty())
<div class="kartu" style="padding:0;margin-bottom:26px">
  <div style="padding:14px 17px 10px;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap">
    <div class="lbl">Dokumen belum lengkap</div>
    <div style="flex:1"></div>
    <div class="lbl">{{ $menungguDok->count() }} rekomendasi</div>
  </div>
  <table>
    <thead><tr><th>Kode</th><th>Satuan kerja</th><th>Kelengkapan</th><th>Diminta sejak</th></tr></thead>
    <tbody>
      @foreach($menungguDok->sortBy(fn ($x) => $x->permintaanDokumen->last()?->tanggal)->take(10) as $x)
        @php $d = $x->progresDokumen(); $pm = $x->permintaanDokumen->last(); @endphp
        <tr>
          <td class="mono" style="font-size:11.5px">
            <a href="{{ route('rekomendasi.show', $x) }}">{{ $x->kode }}</a>
          </td>
          <td style="font-size:12.5px">{{ $x->satker?->namaPendek() }}</td>
          <td class="mono" style="font-size:12px">{{ $d[0] }} dari {{ $d[1] }}</td>
          <td class="mono" style="font-size:12px">
            {{ $pm ? Tampil::tgl($pm->tanggal) : '—' }}
            @if($pm) &middot; {{ (int) $pm->tanggal->startOfDay()->diffInDays(now()->startOfDay()) }} hari @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
  @if($menungguDok->count() > 10)
    <div class="lbl" style="padding:11px 17px;border-top:1px solid var(--rule-2)">
      {{ $menungguDok->count() - 10 }} rekomendasi lain juga belum lengkap, tidak ditampilkan di sini.
    </div>
  @endif
</div>
@endif

{{-- Catatan pembacaan, mengikuti kaki lembar mereka. Bukan hiasan: butir
     pertama menjaga salah baca yang paling gampang terjadi di seluruh halaman
     ini. --}}
<section class="catatanbaca">
  <div class="lbl">Cara membaca angkanya</div>
  <ul>
    <li>
      Jumlah rekomendasi dihitung <b>sekali per Ref IDT</b>. Menjumlahkan baris
      per satuan kerja menghasilkan angka penugasan, yang memang lebih besar —
      di lembar pemantauan, 123 rekomendasi berisi 312 penugasan.
    </li>
    <li>
      <b>Sisa nilai</b> bukan uang yang belum disetor. Ia nilai yang belum
      diakui: rekomendasi yang uangnya sudah lunas tapi buktinya baru diterima
      sebagian tetap menyisakan nilai.
    </li>
    <li>
      Angka BPK dan angka BPSDM memang sering berbeda. Satu rekomendasi BPK bisa
      menyangkut beberapa unit organisasi, jadi bagian BPSDM boleh sudah beres
      sementara SIPTL tetap Belum Sesuai karena unit lain.
    </li>
    <li>
      Untuk memilih apa yang dikerjakan lebih dulu, baca tabel
      <b>Pemantauan per satuan kerja</b> — yang tumpukannya paling tinggi itu
      yang paling menahan angka di atas.
    </li>
  </ul>
</section>
@endsection
