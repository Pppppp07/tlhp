@extends('rangka')
@section('judul', auth()->user()->peran === \App\Enums\PeranPengguna::PIMPINAN ? 'Beranda' : 'Ringkasan')
@section('isi')
@php
  use App\Enums\HasilTelaah;
  use App\Enums\StatusTindakLanjut;
  use App\Enums\SumberLaporan;
  use App\Support\PetaData;
  use App\Support\Tampil;

  $sumber = SumberLaporan::from($jenisUtama);
  $kataM = HasilTelaah::M->nama($sumber);
  $kataBM = HasilTelaah::BM->nama($sumber);
@endphp

<div class="body ringkas">
  {{-- Judulnya jumlah yang BELUM MEMADAI, bukan yang terlambat.

       Mbak Puspi menolak keterlambatan ditonjolkan: "kayaknya enggak perlu
       dimunculin ... di bagian bawah aja enggak apa-apa." Alasannya disebut Mas
       Naufal — "Pusat 4 tuh udah berapa tahun tuh, udah 4 tahun." Temuan 2005
       tidak akan pernah berhenti terlambat, jadi kepalanya akan merah
       selamanya, dan merah yang tidak pernah berubah berhenti dibaca orang.

       Gantinya angka yang memang mereka pakai memutuskan. Angka ini juga turun
       kalau dikerjakan — keterlambatan cuma bisa naik. Keterlambatannya tidak
       hilang; ia turun ke panelnya sendiri. --}}
  <div class="fokus">
    <span class="jml">{{ $unorJml['BM'] }}</span>
    <span class="apa">
      rekomendasi {{ mb_strtolower($kataBM) }}
      <span class="rinci">
        dari {{ $total }} rekomendasi pada {{ $jumlahLaporan }} laporan
        @if($tumpukan) · tumpukan terbanyak di {{ $tumpukan['satker'] }} ({{ $tumpukan['belum'] }} penugasan)@endif
      </span>
    </span>
  </div>

  {{-- Satu formulir untuk seluruh halaman: saklar lingkup dan susunan panel
       terbawa bersama, jadi mengganti lingkup tidak mengembalikan panel ke
       susunan awal. --}}
  <form method="get" action="{{ route('ringkasan') }}" data-peta>
    {{-- Lingkup laporan di paling atas, sebelum apa pun yang lain.

         Mbak Puspi: "di atasnya mungkin, jadi sebelum milih ini dia mau LHP
         laporan pemeriksaan apa." Gabungannya sengaja dipertahankan — "jangan
         dihilangin juga, jadi ini buat kayak monev-nya." --}}
    <div class="pilihgrup" role="group" aria-label="Jenis laporan" style="margin-bottom:14px">
      @foreach(['semua' => 'Semua', 'LHP' => 'LHP', 'LHA' => 'LHA'] as $k => $n)
        <button type="submit" name="lingkup" value="{{ $k }}" aria-pressed="{{ $lingkup === $k ? 'true' : 'false' }}"
          title="{{ $k === 'semua' ? 'Kedua jenis laporan sekaligus — dipakai untuk monev'
            : 'Hanya '.SumberLaporan::from($k)->nama().' dari '.SumberLaporan::from($k)->penerbit() }}">
          {{ $n }}<span class="n">{{ $jumlahJenis[$k] }}</span>
        </button>
      @endforeach
    </div>

    {{-- Satu baris keterangan lingkup, sepadan dengan baris "Sumber data: PTL
         BPSDM · Tanggal pengolahan ..." di kepala lembar mereka. Yang membaca
         angka harus tahu angka apa yang sedang dibacanya. --}}
    <div class="asalangka">
      {{ $jumlahLaporan }} laporan pemeriksaan{{ $jumlahLhp && $jumlahLhp < $jumlahLaporan
        ? ' · '.$jumlahLhp.' LHP dan '.($jumlahLaporan - $jumlahLhp).' LHA'
        : ($jumlahLaporan ? ' · seluruhnya '.($jumlahLhp ? 'LHP' : 'LHA') : '') }}
      · {{ $jumlahTemuan }} temuan bernilai {{ Tampil::rupiahSingkat($nilaiTemuan) }}
      <x-info :teks="[
        'Jumlah rekomendasi dihitung sekali per Ref IDT, sama seperti rekap lembar pemantauan.',
        'Nilai temuan dihitung sekali per temuan — satu temuan bisa melahirkan empat rekomendasi, dan menjumlahkannya empat kali melipatgandakan angka yang dilaporkan ke atas.',
      ]" />
    </div>

    {{-- Dua blok bertumpuk, persis susunan dasbor mereka. Tiap status punya dua
         angka: jumlahnya dan rupiahnya. Mbak Puspi: "Biro pun ada dua dashboard
         kayak gini: versi Unor dan versi SIPTL ... kenapa yang di SIPTL lebih
         kecil? Itu karena ada Unor yang belum beres." --}}
    @if($jumlahLhp)
      @include('ringkasan.blok-status', [
        'judul' => 'Menurut BPK · SIPTL',
        'ket' => 'Putusan resmi di SIPTL, disalin Setba. Menghitung seluruh Unor Kementerian, bukan BPSDM saja. Hanya LHP — LHA berhenti di Inspektorat.',
        'total' => $jumlahBpk,
        'totalRp' => $nilaiBpk,
        'sisa' => $sisaBpk,
        /* Urutannya SS, BS, BT — dari yang paling beres. Itu urutan lembar
           mereka, dan yang dibaca duluan memang kabar baiknya. Berbeda dari
           urutan grafik, yang mengikuti perjalanan berkas. */
        'kolom' => collect(['SS', 'BS', 'BT', 'TD'])
          ->filter(fn ($k) => $statusJml[$k] > 0 || $k !== 'TD')
          ->map(fn ($k) => ['nama' => $k.' · '.StatusTindakLanjut::from($k)->pendek(),
            'n' => $statusJml[$k], 'rp' => $statusRp[$k], 'warna' => PetaData::WARNA_STATUS[$k]])->values(),
        'catatan' => $beda > 0
          ? '<b>'.$beda.'</b> rekomendasi sudah memadai menurut Inspektorat tapi belum diakui selesai oleh BPK. Sebagian besar bukan karena BPSDM — satu rekomendasi BPK bisa menyangkut beberapa Unor, dan yang belum beres di Unor lain menahan statusnya di sini.'
          : null,
        'tugas' => null,
        'kataM' => $kataM,
        'kataBM' => $kataBM,
      ])
    @endif

    @include('ringkasan.blok-status', [
      'judul' => 'Menurut BPSDM · verifikasi Inspektorat',
      'ket' => 'Keadaan bagian BPSDM saja: memadai kalau seluruh satuan kerja pada rekomendasi itu sudah memadai — status terburuk menang, sama seperti kolom Status Rekomendasi Unor di lembar pemantauan.',
      'total' => $total,
      'totalRp' => $nilaiRek,
      'sisa' => $sisaItjen,
      'kolom' => collect([
        ['nama' => $kataM,  'n' => $unorJml['M'],  'rp' => $unorRp['M'],  'warna' => 'var(--ok)'],
        ['nama' => $kataBM, 'n' => $unorJml['BM'], 'rp' => $unorRp['BM'], 'warna' => 'var(--bad)'],
      ]),
      'catatan' => null,
      /* Penyebut kedua. Ditaruh di kaki bloknya, bukan disisipkan ke baris yang
         sama: 123 dan 312 menghitung hal yang berbeda, dan menaruh keduanya
         berdampingan tanpa keterangan persis yang membuat tabel mereka susah
         dibaca. */
      'tugas' => $tugas,
      'kataM' => $kataM,
      'kataBM' => $kataBM,
    ])

    {{-- Dua tabel rekap yang mereka pakai menghadap ke luar. Ditaruh tetap,
         bukan sebagai panel yang bisa diatur: bentuk yang dipakai melapor tidak
         boleh bisa terhapus tidak sengaja.

         PENYEBUTNYA BERBEDA, dan itu yang paling gampang salah baca — per
         satuan kerja menghitung PENUGASAN, per tahun menghitung REKOMENDASI. --}}
    @include('ringkasan.tabel-rekap', [
      'judul' => 'Pemantauan per satuan kerja',
      'ket' => [
        'Satu baris satu satuan kerja. Yang dihitung PENUGASAN — satu satuan kerja pada satu bentuk tindak lanjut — jadi jumlahnya lebih besar daripada jumlah rekomendasi.',
        'Di lembar pemantauan mereka tabel ini berjudul “Reff IDT per Satker” dan totalnya 312, bukan 123.',
        'Diurutkan dari yang tumpukan belum selesainya paling banyak. Itu memang gunanya — catatan mereka sendiri berbunyi “Fokus utama dapat diarahkan pada Satker dengan backlog Belum Selesai tertinggi.”',
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

    @include('ringkasan.tabel-rekap', [
      'judul' => 'Rekap per tahun LHP',
      'ket' => [
        'Satu baris satu tahun surat pemeriksaan. Yang dihitung REKOMENDASI — satu Ref IDT sekali, berapa pun satuan kerjanya.',
        'SS, BS, dan BT milik BPK dan hanya berlaku untuk LHP — itu sebabnya ada kolom “dari LHP” sebagai penyebutnya. Ketiganya berjumlah sama dengan kolom itu, bukan dengan jumlah rekomendasi.',
        'Dari tabel inilah terbaca umur tumpukannya: tahun lama biasanya sudah lunas, sementara yang belum ditindaklanjuti menumpuk di tahun terakhir.',
      ],
      'kepala' => [
        ['k' => 'tahun', 'nama' => 'Tahun LHP', 'mono' => true],
        ['k' => 'jml', 'nama' => 'Rekomendasi', 'num' => true],
        /* Penyebut kolom BPK. Tanpa ini baris yang bercampur LHP dan LHA
           terbaca seperti salah hitung: SS + BS + BT tidak sama dengan jumlah
           rekomendasinya, karena LHA tidak pernah punya status BPK. */
        ['k' => 'keSiptl', 'nama' => 'dari LHP', 'num' => true,
         'ket' => 'Berapa di antaranya LHP — cuma itu yang punya status BPK'],
        ['k' => 'SS', 'nama' => 'SS', 'num' => true, 'ket' => 'Sudah sesuai — BPK'],
        ['k' => 'BS', 'nama' => 'BS', 'num' => true, 'ket' => 'Belum sesuai — BPK'],
        ['k' => 'BT', 'nama' => 'BT', 'num' => true, 'ket' => 'Belum ditindaklanjuti — BPK'],
        /* TD cuma ditampilkan kalau ada isinya. Tanpa kolom ini SS + BS + BT
           tidak menutup penyebut "dari LHP"; dengan kolom kosong ia cuma jadi
           lajur nol yang harus dilewati mata. */
        ...(($perTahun['total']['TD'] ?? 0) > 0
          ? [['k' => 'TD', 'nama' => 'TD', 'num' => true, 'ket' => 'Tidak dapat ditindaklanjuti — BPK']] : []),
        ['k' => 'memadai', 'nama' => $kataM, 'num' => true],
        ['k' => 'belum', 'nama' => $kataBM, 'num' => true],
        ['k' => 'nilai', 'nama' => 'Nilai rekomendasi', 'num' => true, 'mono' => true, 'rp' => true],
        ['k' => 'sisaBpk', 'nama' => 'Sisa · SIPTL', 'num' => true, 'mono' => true, 'rp' => true],
        ['k' => 'sisaItjen', 'nama' => 'Sisa · Inspektorat', 'num' => true, 'mono' => true, 'rp' => true],
      ],
      'baris' => $perTahun['baris'],
      'jumlah' => $perTahun['total'],
    ])

    <div class="judulderet">
      <h2>Peta data</h2>
      <span class="ket">Tiap panel bisa diganti sendiri: dikelompokkan menurut apa, dihitung apa, dan digambar dalam bentuk apa.</span>
      <div class="sela"></div>
      <button class="btn btn-s" type="submit" name="aksi" value="tambah"><x-ikon n="Plus" :s="14" /> Tambah panel</button>
    </div>

    <div class="petak">
      @foreach($panel as $i => $p)
        @include('ringkasan.panel', ['p' => $p, 'i' => $i])
      @endforeach
      @if(count($panel) === 0)
        <div class="kosong" style="grid-column:1 / -1">
          Semua panel dihapus. Tekan “Tambah panel” untuk mulai lagi.
        </div>
      @endif
    </div>

    {{-- Tanpa JavaScript, pilihan panel dikirim dengan tombol ini. --}}
    <div class="tindakan" data-tanpa-js style="margin-bottom:22px">
      <button class="btn btn-p" type="submit">Terapkan pilihan panel</button>
    </div>
  </form>

  {{-- Catatan pembacaan, mengikuti kaki lembar mereka. Bukan hiasan: butir
       terakhir menjaga salah baca yang paling gampang terjadi di seluruh
       halaman ini. --}}
  <section class="catatanbaca">
    <div class="lbl">Cara membaca angkanya</div>
    <ul>
      <li>
        Jumlah rekomendasi dihitung <b>sekali per Ref IDT</b>. Menjumlahkan
        baris per satuan kerja menghasilkan angka penugasan, yang memang lebih
        besar — di lembar pemantauan, 123 rekomendasi berisi 312 penugasan.
      </li>
      <li>
        <b>Sisa nilai</b> bukan uang yang belum disetor. Ia nilai yang belum
        diakui: rekomendasi yang uangnya sudah lunas tapi buktinya baru diterima
        sebagian tetap menyisakan nilai.
      </li>
      <li>
        Angka BPK dan angka BPSDM memang sering berbeda. Satu rekomendasi BPK
        bisa menyangkut beberapa Unor, jadi bagian BPSDM boleh sudah beres
        sementara SIPTL tetap Belum Sesuai karena Unor lain.
      </li>
      <li>
        Untuk memilih apa yang dikerjakan lebih dulu, baca panel
        <b>Belum memadai per satuan kerja</b> — yang tumpukannya paling tinggi
        itu yang paling menahan angka di atas.
      </li>
    </ul>
  </section>

  @if($menungguDok->isNotEmpty())
    <section class="panel lebar">
      <div class="kepala">
        <h3>Dokumen belum lengkap</h3>
        <x-info teks="Rekomendasi yang dokumennya sudah diminta tapi belum terpenuhi semua. Diurutkan dari permintaan yang paling lama menggantung." />
        <div class="sela"></div>
        <span class="n">{{ $menungguDok->count() }} rekomendasi</span>
      </div>
      <div class="isi tw" style="padding:0;border:0">
        <table>
          <thead><tr><th>Kode</th><th>Satuan kerja</th><th>Kelengkapan</th><th>Diminta sejak</th></tr></thead>
          <tbody>
            {{-- Sepuluh yang paling lama menggantung sudah cukup untuk
                 memutuskan apa yang dikejar hari ini; sisanya bukan bacaan
                 ringkasan. --}}
            @foreach($menungguDok->sortBy(fn ($x) => (string) $x->permintaanDokumen->last()?->tanggal?->toDateString())->take(10) as $x)
              @php
                $d = $x->progresDok();
                $pm = $x->permintaanDokumen->last();
              @endphp
              <tr>
                <td class="mono" style="font-size:11.5px">{{ $x->kode }}</td>
                <td style="font-size:12.5px">{{ $x->daftarSasaran()->first()?->satker?->namaPendek() }}</td>
                <td>
                  <div class="prog">
                    <span class="bar"><i style="width:{{ $d['ada'] / $d['dari'] * 100 }}%"></i></span>
                    <b>{{ $d['ada'] }} dari {{ $d['dari'] }}</b>
                  </div>
                </td>
                <td class="mono" style="font-size:12px">
                  {{ Tampil::tgl($pm?->tanggal) }} · {{ \App\Models\Rekomendasi::selisih($pm?->tanggal) }} hari
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if($menungguDok->count() > 10)
        <div class="kaki">
          {{ $menungguDok->count() - 10 }} rekomendasi lain juga belum lengkap, tidak ditampilkan di sini.
        </div>
      @endif
    </section>
  @endif
</div>
@endsection
