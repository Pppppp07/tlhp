@extends('rangka')
@section('judul', 'Rincian rekomendasi')
@section('isi')
@php
  use App\Support\Tampil;
  use App\Enums\PeranPengguna;
  use App\Enums\PosisiBerkas;
  $lap = $r->temuan->laporan;
  $u = auth()->user();
  $telat = $r->lewatTenggat();
  $dana = $r->nilai_pulih ? [$r->nilaiTerpulihkan(), $r->nilai_pulih] : null;
  $dok = $r->progresDokumen();
  $chv = $r->keputusan->last();

  use App\Enums\HasilTelaah;
  $sumber = $lap->sumber;
  $sayaSatker = $u->peran === PeranPengguna::SATKER;

  /* Belum memadai adalah keadaan bakunya — termasuk selama suratnya belum
     terbit. Modelnya tetap jujur mengembalikan null (belum ada putusan); yang
     ditampilkan tetap "belum memadai", karena itulah keadaan rekomendasinya di
     mata Itjen sampai ada surat yang menyatakan sebaliknya. */
  $putusan = $r->putusan() ?? HasilTelaah::BM;

  $namaSatker = $baris->map(fn ($x) => $x->satker?->namaPendek())->filter();
  $sebutSatker = $namaSatker->count() > 1
      ? $namaSatker->count() . ' satuan kerja'
      : ($namaSatker->first() ?? '—');

  $terpulih = $r->nilaiTerpulihkan();
  $sisa = $r->sisaPemulihan();
@endphp

<div style="display:flex;gap:9px;margin-bottom:16px;flex-wrap:wrap">
  <a class="btn" href="{{ url()->previous() }}">&larr; Kembali</a>
  <a class="btn" href="{{ route('laporan.show', $lap) }}">Lihat laporan lengkap</a>
</div>

{{-- Dibaca lebih dulu daripada isi rekomendasinya: "sekarang di tahap mana"
     adalah pertanyaan pertama yang muncul saat membuka berkas. --}}
<x-langkah :rekomendasi="$r" :sumber="$lap->sumber" />

<x-babak no="1" judul="Laporan dan temuan"
  :teks="[
    'Surat asalnya dan isi temuan yang melahirkan rekomendasi ini.',
    'Semuanya keterangan — tidak ada yang bisa dikerjakan di sini.',
  ]" />

{{-- Kartu "Laporan asal": keterangan yang berlaku untuk laporannya, bukan
     untuk rekomendasi ini sendiri. Dipisah supaya kepala rekomendasi di
     bawahnya cuma memuat yang memang miliknya. --}}
<div class="kartu lipatsatu" id="r-laporan" style="margin-bottom:20px;scroll-margin-top:80px">
  <div class="judulkartu">
    <span class="ic-kotak"><x-ik nama="tumpuk" ukuran="17" /></span>
    <h3>Laporan asal</h3>
  </div>

  <dl class="kv">
    <dt>Nomor surat</dt>
    <dd class="mono">
      <a href="{{ route('laporan.show', $lap) }}">{{ $lap->nomor }}</a>
    </dd>

    <dt>Diterbitkan</dt><dd>{{ $lap->sumber->penerbit() }}</dd>
    <dt>Diterima</dt><dd class="mono">{{ Tampil::tgl($lap->tgl_terima) }}</dd>

    {{-- Untuk satuan kerja, hanya namanya sendiri. Menyebut seluruh satuan
         kerja terperiksa memberi tahu siapa lagi yang kena perkara yang sama —
         dan itu bukan urusannya, walau berkasnya satu laporan. --}}
    <dt>Satuan kerja terperiksa</dt>
    <dd>
      @if($sayaSatker)
        <span class="kepingsatker">
          <span class="keping">{{ $u->satker?->namaPendek() ?? '—' }}</span>
        </span>
      @else
        <span class="kepingsatker">
          @forelse($lap->satkerDiperiksa() as $sk)
            <span class="keping">{{ $sk->namaPendek() }}</span>
          @empty
            &mdash;
          @endforelse
        </span>
      @endif
    </dd>

    {{-- Surat aslinya tidak diberikan ke satuan kerja: satu surat memuat
         seluruh temuan pada seluruh satuan kerja. --}}
    @if($u->peran !== PeranPengguna::SATKER)
      @php $suratAsli = $lap->lampiran->first(); @endphp
      @if($suratAsli)
        <dt>Berkas surat asli</dt>
        <dd><x-ik nama="klip" ukuran="13" /> {{ $suratAsli->labelTampil() }}</dd>
      @endif
    @endif

  </dl>

  {{-- Bagian kedua: isi temuannya sendiri. Dipisah dari keterangan suratnya
       karena keduanya menjawab pertanyaan yang berbeda — "surat mana ini" dan
       "apa perkaranya". --}}
  <div class="ruas">
    <div class="judulkartu">
      <span class="ic-kotak abu"><x-ik nama="berkas-teks" ukuran="17" /></span>
      <h3>Uraian temuan</h3>
    </div>

    <dl class="kv">
    <dt>Kategori temuan <x-info :teks="[
      'Golongan temuan menurut BPK, dan daftarnya berbeda antara LHP dan LHA.',
      'Dipakai mengelompokkan di layar Ringkasan; tidak memengaruhi alur kerjanya.',
    ]" /></dt>
    <dd>
      <span class="nilaikat">
        <i style="background:var(--aksen)"></i>{{ $r->temuan->kategori?->nama ?: '—' }}
      </span>
    </dd>

    <dt>Kategori internal <x-info :teks="[
      'Golongan menurut pengelompokan Setba sendiri, di luar kategori BPK.',
      'Dipakai melihat perkara mana yang paling sering berulang.',
    ]" /></dt>
    <dd>
      <span class="nilaikat">
        <i style="background:{{ $r->temuan->kategoriIntern?->warnaLabel()->padat() ?? 'var(--rule)' }}"></i>{{ $r->temuan->kategoriIntern?->nama ?: '—' }}
      </span>
    </dd>

    {{-- Kondisi dan kriteria tidak disebut. Kondisi berbeda-beda tiap satuan
         kerja sehingga menumpuknya jadi satu paragraf justru mengaburkan siapa
         kena apa, dan kriteria berisi terlalu banyak peraturan untuk diketik
         ulang. Keduanya tetap ada di dokumen LHP-nya — yang dipantau bukan itu.

         Nilai temuan juga tidak: halaman ini tentang SATU rekomendasi, dan
         nilai yang mengikatnya sudah berdiri di kepala rekomendasi. Menyebut
         nilai temuan seluruhnya membuat pembacanya mengira semuanya
         tanggungannya. --}}
    <dt>Sebab</dt><dd>{{ $r->temuan->sebab ?: '—' }}</dd>
    <dt>Akibat</dt><dd>{{ $r->temuan->akibat ?: '—' }}</dd>

    <dt>Rekomendasi lain <x-info :teks="[
      'Rekomendasi lain yang lahir dari temuan yang sama.',
      'Status temuan disimpulkan dari seluruhnya — tidak disimpan di basis data, supaya tidak bisa berselisih dengan isinya.',
    ]" /></dt>
    <dd>
      {{-- Disaring lewat penjaga yang sama dengan halaman lain: rekomendasi
           saudara yang tidak menyangkut satuan kerja ini tidak disebut sama
           sekali. Menyebutnya berarti membocorkan siapa lagi yang kena temuan
           yang sama lewat pintu belakang — sesudah susah payah disaring di
           seluruh layar lain. --}}
      @php
        $penjaga = \App\Support\Terlihat::untuk();
        $saudara = $r->temuan->rekomendasi
          ->where('id', '!=', $r->id)
          ->filter(fn ($x) => $penjaga->bolehLihatRekomendasi($x));
      @endphp
      @forelse($saudara as $lain)
        @php $sasaranLain = $penjaga->barisRekomendasi($lain); @endphp
        <div style="display:flex;gap:9px;align-items:flex-start;margin-bottom:7px">
          <span class="cap cap-{{ $lain->status->value }}">{{ $lain->status->value }}</span>
          <div>
            <a href="{{ route('rekomendasi.show', $lain) }}">{{ $lain->uraian }}</a>
            <div class="lbl" style="margin-top:2px">
              {{ $sasaranLain->map(fn ($x) => $x->satker?->namaPendek())->filter()->unique()->join(', ') ?: 'belum ditugaskan' }}
              &middot; {{ $lain->posisiTampil()?->label() ?? 'belum ditugaskan' }}
            </div>
          </div>
        </div>
      @empty
        <span class="lbl">tidak ada</span>
      @endforelse
    </dd>
  </dl>

  </div>
</div>

<x-babak no="2" judul="Tindak lanjut"
  :teks="[
    'Rekomendasinya sendiri, siapa yang dituju, dan sampai mana berkas tiap satuan kerja.',
    'Yang bisa dikerjakan ada di dalam barisnya sendiri — tekan Kerjakan pada baris satuan kerja yang dituju.',
  ]" />

<div class="wadahtl" id="r-kepala" style="scroll-margin-top:80px">
  <div class="kepalarek">
    {{-- Penomoran resmi berdiri paling depan: itulah yang dipakai menyebut
         rekomendasi ini di surat-menyurat dan saat berkoordinasi dengan BPK.
         Kode sistem sendiri (REK-…) tidak pernah dipakai di luar aplikasi. --}}
    <div class="tanda">
      <span class="pil biru">Ref LHP {{ $r->refLhp() }}</span>
      <span class="sumber sumber-{{ $lap->sumber->value }}">{{ $lap->sumber->value }}</span>
      <x-kode ket="Ref IDT" isi="{{ $r->refIdt() }}" />
    </div>

    <h2>{{ $r->uraian }}</h2>

    <div class="tanda kecil">
      <span class="pil">Temuan {{ $r->temuan->nomor_pada_surat ?: $r->temuan->kode }}</span>
      <x-kode ket="Kode" isi="{{ $r->temuan->kode }}" />
      <span class="jdltem">{{ $r->temuan->judul }}</span>
    </div>

    <dl class="kv fakta">
      {{-- Dua sumbu status berdiri berdampingan, dan urutannya bukan
           kebetulan: Itjen menilai lebih dulu, BPK sesudahnya. --}}
      <dt>Status verifikasi <x-info :teks="[
        'Putusan Inspektorat atas rekomendasi ini, dibaca dari surat CHV terakhir.',
        'Sampai Inspektorat hanya ada dua putusan: memadai dan belum memadai. Kode BT, SS, dan BS milik BPK, dan baru muncul sesudah berkasnya dinilai di SIPTL.',
        'Belum memadai adalah keadaan bakunya — termasuk selama suratnya belum terbit. Yang menjawab sudah diperiksa siapa adalah Posisi berkas.',
        'Satu satuan kerja belum beres membuat seluruh rekomendasi belum memadai, walau satuan kerja lain sudah.',
      ]" /></dt>
      <dd style="color:{{ $putusan->memadai() ? 'var(--stamp)' : 'var(--verm)' }};font-weight:600">
        {{ $putusan->nama($sumber) }}
      </dd>

      @if($sumber->melewatiSiptl())
        <dt>Status SIPTL <x-info :teks="[
          'Putusan BPK atas rekomendasi ini — putusan terakhir, sesudah Inspektorat.',
          'Disalin dengan tangan oleh Setba dari SIPTL. BPK tidak mengirim pemberitahuan, jadi status ini baru berubah setelah Setba membukanya.',
          'Bisa berbeda dari Status verifikasi, dan itu wajar: satu rekomendasi BPK bisa menyangkut beberapa unit organisasi.',
        ]" /></dt>
        <dd>
          <span class="cap cap-{{ $r->status->value }}">{{ $r->status->value }}</span>
          {{ $r->status->nama() }}
          @if($r->siptl_catatan)
            <div class="lbl" style="margin-top:3px">{{ $r->siptl_catatan }}</div>
          @endif
        </dd>
      @endif

      <dt>{{ $sayaSatker ? 'Yang harus saya lakukan' : 'Bentuk tindak lanjut' }}</dt>
      <dd>{{ $r->tindakan->map(fn ($t) => $t->namaBentuk())->unique()->join(', ') ?: '—' }}</dd>

      <dt>{{ $sayaSatker ? 'Satuan kerja saya' : 'Satuan kerja dituju' }} <x-info :teks="[
        'Satu rekomendasi bisa ditujukan ke beberapa satuan kerja sekaligus, dan nominalnya dipecah antar mereka.',
        'Berkas tiap satuan kerja berjalan sendiri-sendiri: yang satu bisa sudah di Inspektorat sementara yang lain masih menyusun jawaban.',
        'Rekomendasinya baru bergerak sesudah semuanya tuntas.',
      ]" /></dt>
      <dd>{{ $sebutSatker }}</dd>

      {{-- "Rencana aksi", bukan "tenggat menjawab": itu sebutan yang dipakai
           surat dan lembar pemantauannya sendiri. --}}
      <dt>Rencana aksi <x-info :teks="[
        'Batas waktu satuan kerja menjawab, dihitung sejak laporan pemeriksaannya diterima Setba.',
        'Berbeda dari target penyelesaian di bawahnya: yang ini punya dasar hukum, yang itu perkiraan kapan pekerjaannya benar-benar selesai.',
        'Terlewat bukan berarti berkasnya batal — hanya berarti terlambat, dan keterlambatannya ikut tercatat.',
      ]" /></dt>
      @php
        /* Satuan kerja melihat tanggal yang mengikat dirinya — yang paling awal
           di antara bentuk tindak lanjut yang membebaninya. Peran lain melihat
           tanggal rekomendasinya seutuhnya. */
        $tglRenaksi = $r->renaksiUntuk($sayaSatker ? $u->satker_id : null);
      @endphp
      <dd class="mono" @if($telat) style="color:var(--verm)" @endif>
        {{ Tampil::tgl($tglRenaksi) }}@if($telat) &middot; lewat {{ $telat }} hari @endif
      </dd>

      <dt>Target penyelesaian</dt>
      <dd class="mono">{{ $r->target_selesai ? Tampil::tgl($r->target_selesai) : 'tidak ditetapkan' }}</dd>

      <dt>{{ $sayaSatker ? 'Nilai yang harus saya pulihkan' : 'Nilai yang dipulihkan' }} <x-info :teks="[
        'Bagian nilai temuan yang memang harus disetor ke kas negara menurut bunyi rekomendasinya.',
        'Belum tentu sama dengan nilai temuan: sebagian temuan diselesaikan dengan melengkapi dokumen atau memperbaiki prosedur, bukan dengan menyetor uang.',
      ]" /></dt>
      <dd class="mono" @if($r->nilai_pulih > 0) style="color:var(--verm);font-weight:600" @endif>
        {{ Tampil::rupiah($r->nilai_pulih) }}
      </dd>

      {{-- Dua baris uang ini hanya muncul kalau rekomendasinya memang
           membebani nilai. Yang cuma minta surat teguran tidak punya angka apa
           pun, dan "Rp 0 dari Rp 0" bukan keterangan. --}}
      @if($r->nilai_pulih > 0)
        {{-- Nol ditulis apa adanya. Tampil::rupiah(0) menghasilkan garis, dan
             garis artinya "tidak ada datanya" — padahal datanya ada dan
             nilainya nol. --}}
        <dt>Sudah dipulihkan</dt>
        <dd class="mono" style="color:{{ $terpulih ? 'var(--stamp)' : 'var(--ink-3)' }};font-weight:600">
          {{ $terpulih ? Tampil::rupiah($terpulih) : 'Rp 0' }}
        </dd>

        <dt>Sisa</dt>
        <dd class="mono" style="color:{{ $sisa > 0 ? 'var(--verm)' : 'var(--stamp)' }};font-weight:600">
          {{ Tampil::rupiahSisa($sisa) }}
        </dd>

        @php $angsur = $r->progresAngsuran(); @endphp
        @if($angsur)
          <dt>Rencana angsuran</dt>
          <dd>
            {{ $angsur[0] }} dari {{ $angsur[1] }} kali
            @if($angsur[2])<span class="lbl"> &middot; dikunci</span>@endif
          </dd>
        @endif
      @endif

      @if($r->alasanTd)
        <dt>Alasan tidak dapat ditindaklanjuti</dt>
        <dd>
          {{ $r->alasanTd->nama }}
          @if($r->catatan_td)<div class="lbl" style="margin-top:3px">{{ $r->catatan_td }}</div>@endif
        </dd>
      @endif
    </dl>

    @if($r->catatan)
      <div class="catat">
        <div class="lbl" style="margin-bottom:4px">Catatan Setba untuk satuan kerja</div>
        <div style="font-size:13px">{{ $r->catatan }}</div>
      </div>
    @endif
  </div>

  {{-- Bilah alur REKOMENDASI. Tahap ini milik rekomendasinya, bukan milik
       satu satuan kerja: ia berjalan sesudah seluruh satuan kerja dinyatakan
       memadai, lalu bergerak sekali untuk seluruhnya.

       Berdiri di atas tabel karena menjawab pertanyaan yang selama ini
       menggantung bagi satuan kerja: barisku sudah memadai, lalu kenapa
       perkaranya belum selesai. --}}
  @if($r->posisi)
    <div class="alurrek">
      <span class="lbl" style="margin:0">Tindak lanjut Rekomendasi</span>
      <b>{{ $r->posisi->label() }}</b>
      <x-info :teks="[
        'Tahap ini milik rekomendasinya, bukan milik satu satuan kerja.',
        'Berjalan setelah tindak lanjut seluruh satuan kerja dinyatakan memadai, dan bergerak sekali untuk seluruh rekomendasi.',
        'Ada dua alur yang berjalan sendiri-sendiri: Tindak lanjut Rekomendasi di baris ini, dan tindak lanjut tiap satuan kerja di tabel bawah.',
      ]" />
    </div>
  @endif

  {{-- Satu baris satu satuan kerja: nominalnya, sampai mana berkasnya, dan
       sudah ditandai memadai atau belum. Inilah bentuk yang diminta Mbak
       Puspi — tanda memadai per satuan kerja, bukan satu tanda untuk semua.

       Dikelompokkan menurut bentuk tindak lanjutnya: satu rekomendasi bisa
       menuntut dua bentuk sekaligus, dan tiap bentuk punya daftar dokumen dan
       kirimannya sendiri. Bentuknya disebut sekali sebagai kepala kelompok,
       bukan diulang di tiap baris. --}}
  @php [$memadai, $totalSasaran] = $r->hitungMemadai(); @endphp
  <div class="kepalatl" id="r-satker" style="scroll-margin-top:80px">
    <span class="lbl">{{ $sayaSatker ? 'Yang harus saya kerjakan' : 'Rincian tindak lanjut' }}</span>
    <span class="bar"><i style="width:{{ $totalSasaran ? round($memadai / $totalSasaran * 100) : 0 }}%"></i></span>
    <span>{{ $memadai }} dari {{ $totalSasaran }} {{ strtolower(\App\Enums\HasilTelaah::M->nama($sumber)) }}</span>
    <span class="cap {{ $putusan->cap() }}">{{ $putusan->value }}</span>
    <x-info :teks="$sayaSatker ? [
      'Yang ditampilkan hanya kewajiban satuan kerja ini. Rekomendasi yang sama bisa membebani satuan kerja lain, dan bagian mereka bukan urusan di sini.',
      'Satu satuan kerja yang kena dua bentuk tindak lanjut memikul dua kewajiban, dan keduanya harus tuntas sendiri-sendiri.',
    ] : [
      'Tanda di tiap baris adalah penilaian Inspektorat atas SATU satuan kerja.',
      'Putusan atas seluruh rekomendasi hanya lahir dari surat CHV — dan ia berbunyi memadai cuma kalau semua barisnya sudah memadai.',
      'Satu satuan kerja yang kena dua bentuk tindak lanjut terhitung dua penugasan, karena keduanya memang harus tuntas sendiri-sendiri.',
    ]" />
  </div>

  <div class="tw">
    <table class="tabtl">
      <colgroup>
        <col style="width:64px"><col><col style="width:150px">
        <col><col style="width:164px"><col style="width:110px">
      </colgroup>
      <thead><tr>
        <th class="num kolno">No</th>
        <th>Satuan kerja / tindak lanjut</th>
        <th class="num">Nilai</th>
        <th>Posisi berkas</th>
        <th>Status <x-info :teks="[
          'Hasil telaah atas berkas satuan kerja ini saja.',
          'Kosong berarti belum ditelaah — itu tidak sama dengan belum memadai.',
          'UKI menyaring lebih dulu dan menerbitkan surat validasinya sendiri, tapi tandanya di sini baru berubah setelah Inspektorat memutuskan.',
          'Putusan UKI-nya bisa dibaca di bagian Riwayat validasi.',
        ]" /></th>
        {{-- Kolom terakhir menjawab satu pertanyaan yang selama ini harus
             dicari sendiri ke bawah halaman: di baris ini, saya bisa berbuat
             apa. --}}
        <th class="kolaksi">Aksi</th>
      </tr></thead>
      <tbody>
      @php $adaBaris = false; @endphp
      @foreach($r->tindakan as $k => $tk)
        @php
          $isiTk = $tk->sasaran->filter(fn ($x) => $baris->contains('id', $x->id));
        @endphp
        @continue($isiTk->isEmpty())
        @php $adaBaris = true; @endphp

        <tr class="grupbaris">
          <td class="num mono kolno">{{ $k + 1 }}</td>
          <td colspan="3" style="font-weight:600">{{ $tk->namaBentuk() }}</td>
          {{-- Rata kanan supaya ujungnya segaris dengan tombol di baris-baris
               bawahnya. --}}
          @php
            /* Tanggal bentuk tindak lanjut INI, bukan tanggal rekomendasinya.
               Satuan kerja yang cuma kena satu bentuk tidak perlu tahu tenggat
               bentuk yang lain. */
            $tglTk = $tk->renaksi();
            $telatTk = $tglTk && $tglTk->startOfDay()->isPast()
              && ! $r->semuaTuntas();
          @endphp
          <td colspan="2" class="lbl"
            style="white-space:nowrap;text-align:right;{{ $telatTk ? 'color:var(--bad)' : '' }}">
            @if($tglTk)rencana aksi {{ Tampil::tgl($tglTk) }}@endif
          </td>
        </tr>

        @foreach($isiTk->values() as $i => $x)
          <tr class="bukaan" data-buka="s{{ $x->id }}">
            <td class="num mono kolno">{{ $k + 1 }}.{{ $i + 1 }}</td>
            {{-- Panahnya berdiri di depan nama, bukan di kolom nomor: yang
                 dibuka barisnya, dan di kolom nomor yang rata kanan ia
                 terselip di antara angka dan tepi kolom. --}}
            <td style="font-weight:600">
              <span class="panahbaris"><x-ik nama="panah-kanan" ukuran="13" /></span>
              {{ $x->satker?->namaPendek() ?? '—' }}
            </td>
            <td class="num mono" style="font-weight:700">
              {{ $x->nilai > 0 ? Tampil::rupiah($x->nilai) : '—' }}
            </td>
            {{-- Keadaannya, bukan nama unitnya. "Setba" tidak menjawab apa yang
                 sedang terjadi — berkas di mejanya bisa berarti tanggapan perlu
                 ditinjau atau hasil telaah perlu diteruskan, dan keduanya
                 pekerjaan yang berbeda. --}}
            <td style="font-size:12.5px">{{ $x->keadaanPosisi() }}</td>
            {{-- Kodenya saja; nama panjangnya di gelembung judul. Kolomnya
                 sempit, dan "Belum memadai" memaksa tabelnya melar.

                 Kosong tetap dibedakan dari belum memadai — prototipe
                 menyamakan keduanya, tapi menuliskan "BM" pada baris yang belum
                 pernah dilihat siapa pun berarti mengaku Inspektorat sudah
                 menilainya kurang. --}}
            <td>
              @if($x->hasil)
                <span class="cap {{ $x->hasil->cap() }}"
                  title="{{ $x->hasil->nama($sumber) }}">{{ $x->hasil->value }}</span>
              @else
                <span class="lbl" title="Belum ditelaah — belum ada yang menilainya">&mdash;</span>
              @endif
            </td>
            {{-- Dua saklar terpisah. Menekan barisnya membuka keterangannya
                 saja; menekan tombol ini membuka keterangan BESERTA
                 formulirnya. Yang cuma ingin melihat tidak perlu menurunkan
                 formulir sepanjang layar.

                 Tombolnya menyala hanya kalau memang ada yang bisa dikerjakan
                 peran ini di baris ini. Yang padam tetap membuka rinciannya —
                 keterangan siapa yang sedang memegangnya sama pentingnya. --}}
            @php $bisaKerja = $x->diMeja($u->peran) && ! $r->terkunciOlehSurat(); @endphp
            <td class="kolaksi">
              <button type="button" class="btn btn-s {{ $bisaKerja ? 'btn-p' : '' }}"
                data-aksi="s{{ $x->id }}"
                aria-expanded="false" aria-controls="s{{ $x->id }}">
                {{ $bisaKerja ? 'Kerjakan' : 'Lihat' }}
              </button>
            </td>
          </tr>

          {{-- Tanpa `hidden`: yang menyembunyikannya skrip, sesudah halaman
               siap. Kalau skripnya gagal dimuat, seluruh rinciannya tetap
               terbaca — cuma tergelar semua. --}}
          <tr class="lebar" id="s{{ $x->id }}">
            <td colspan="6">
              <div class="isilebar">
                @include('bagian/rinci-sasaran', ['s' => $x, 'sumber' => $sumber])

                {{-- Formulirnya turun di dalam barisnya sendiri, bukan di satu
                     panel di bawah tabel. Satu rekomendasi bisa dipikul tiga
                     satuan kerja; tiga panel berturut-turut di bawah tabel
                     memaksa pengisinya mencocokkan sendiri panel mana milik
                     baris mana — padahal barisnya sudah menyebutkan namanya
                     persis di atas. --}}
                @if($x->diMeja($u->peran))
                  {{-- Turun hanya kalau tombol Aksi yang menekannya. Tanpa
                       skrip, `hidden` tidak pernah dipasang dan formulirnya
                       ikut terlihat begitu barisnya terbuka — penambah, bukan
                       penopang. --}}
                  <div class="panelbaris" data-panel="s{{ $x->id }}">
                    @include('bagian/panel-baris', ['s' => $x, 'r' => $r])
                  </div>
                @endif
              </div>
            </td>
          </tr>
        @endforeach
      @endforeach

      @unless($adaBaris)
        <tr><td colspan="6" style="color:var(--ink-3);padding:18px">
          Rekomendasi ini belum ditujukan ke satuan kerja mana pun.
        </td></tr>
      @endunless
      </tbody>
    </table>
  </div>

  {{-- Catatan yang tidak bisa dipastikan barisnya tetap ditampilkan, tidak
       dihilangkan. Ini catatan lama dari zaman satu rekomendasi satu satuan
       kerja: penugasannya sudah dipecah, catatannya belum ikut bertanda.
       Menyembunyikannya berarti dokumen yang pernah diminta hilang dari
       halaman tanpa ada yang tahu. --}}
  @php
    $dokLepas = $r->permintaanDokumen->whereNull('sasaran_id');
    $uangLepas = $r->pemulihan->whereNull('sasaran_id');
    $suratLepas = $r->surat->whereNull('sasaran_id');
  @endphp
  @if($dokLepas->isNotEmpty() || $uangLepas->isNotEmpty() || $suratLepas->isNotEmpty())
    <div class="lepasan">
      <div class="lbl" style="margin-bottom:8px">
        Belum bertanda satuan kerja
        <x-info :teks="[
          'Catatan ini menggantung pada rekomendasinya, belum pada baris satuan kerja tertentu.',
          'Berasal dari zaman satu rekomendasi hanya punya satu satuan kerja. Sesudah penugasannya dipecah, catatan lama tidak bisa dipastikan milik baris yang mana.',
          'Ditampilkan di sini supaya tidak hilang — bukan berarti tidak berlaku.',
        ]" />
      </div>

      @foreach($dokLepas as $pm)
        <div class="lbl" style="margin-bottom:4px">
          dokumen diminta {{ $pm->peran_peminta->nama() }} &middot; {{ Tampil::tgl($pm->tanggal) }}
        </div>
        <ul class="ceklisrapat" style="margin-bottom:10px">
          @foreach($pm->item as $it)
            <li @class(['ada' => $it->terpenuhi])>
              <span class="tik">{{ $it->terpenuhi ? '✓' : '○' }}</span>
              <span>{{ $it->nama }}</span>
            </li>
          @endforeach
        </ul>
      @endforeach

      @if($suratLepas->isNotEmpty())
        <ul class="barisrinci" style="margin-bottom:10px">
          @foreach($suratLepas as $x)
            <li>
              <span class="tgl mono">{{ Tampil::tgl($x->tanggal) }}</span>
              <span>
                <b class="mono">{{ $x->nomor }}</b>
                <span class="lbl">&middot; {{ $x->ringkas() }}</span>
              </span>
            </li>
          @endforeach
        </ul>
      @endif

      @if($uangLepas->isNotEmpty())
        <ul class="barisrinci">
          @foreach($uangLepas as $x)
            <li>
              <span class="tgl mono">{{ Tampil::tgl($x->tanggal) }}</span>
              <span class="nilai">
                <b class="mono">{{ Tampil::rupiah($x->nilai) }}</b>
                <span class="lbl">
                  @if($x->ntpn) NTPN {{ $x->ntpn }}
                  @elseif($x->no_berita_acara) BA {{ $x->no_berita_acara }}
                  @endif
                </span>
              </span>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  @endif

  @if($r->nilai_pulih > 0)
    <div class="kakitl">
      <span class="lbl">Jumlah</span>
      <span class="mono">{{ Tampil::rupiah($r->nilai_pulih) }}</span>
    </div>
  @endif
</div>

<div id="r-perkembangan" style="scroll-margin-top:80px">

@if($r->telaah->isNotEmpty())
<div class="kartu" id="r-telaah" style="margin-bottom:14px;scroll-margin-top:80px">
  <div class="judulkartu">
    <span class="ic-kotak kuning"><x-ik nama="papan-cek" ukuran="17" /></span>
    <h3>Hasil telaah</h3>
    <span class="n">{{ $r->telaah->count() }} catatan</span>
    <x-info :teks="[
      'Telaah menandai berkas SATU satuan kerja memadai atau belum — bukan seluruh rekomendasinya.',
      'Ia tidak mengubah status BPK; status itu hanya berubah lewat SIPTL.',
    ]" />
  </div>
  @foreach($r->telaah as $v)
    <div style="display:flex;gap:12px;padding-bottom:12px;margin-bottom:12px;@if(!$loop->last)border-bottom:1px solid var(--rule-2)@endif">
      <div class="mono" style="font-size:11px;color:var(--ink-3);flex:none;width:74px;padding-top:1px">{{ Tampil::tgl($v->tanggal) }}</div>
      <div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:4px">
          @if($v->hasil)
            <span class="cap {{ $v->hasil->cap() }}">{{ $v->hasil->nama($r->temuan->laporan->sumber) }}</span>
          @endif
          <b style="font-size:12.5px">{{ $v->sasaran?->satker?->namaPendek() ?? 'seluruh rekomendasi' }}</b>
        </div>
        <div style="font-size:13px">{{ $v->catatan }}</div>
        <div class="lbl" style="margin-top:3px">
          {{ $v->label_oleh }}@if($v->bersurat()) &middot; surat {{ $v->nomor_surat }}
            @if($v->tgl_surat) &middot; {{ Tampil::tgl($v->tgl_surat) }}@endif
          @endif
        </div>
        @if($v->lampiran)
          <div style="margin-top:7px">@include('bagian/berkas', ['b' => $v->lampiran])</div>
        @endif
      </div>
    </div>
  @endforeach
</div>
@endif

@if($r->pengembalian->isNotEmpty())
{{-- Kenapa berkas ini pernah balik lagi. Ditaruh sesudah hasil telaah karena
     memang akibatnya: berkas kembali justru karena telaahnya berbunyi belum
     memadai. --}}
<div class="kartu" id="r-kembali" style="margin-bottom:14px;scroll-margin-top:80px">
  <div class="judulkartu">
    <span class="ic-kotak jingga"><x-ik nama="putar-balik" ukuran="17" /></span>
    <h3>Pernah dikembalikan</h3>
    <span class="n">{{ $r->pengembalian->count() }} kali</span>
    <x-info :teks="[
      'Berkas ini pernah balik lagi ke satuan kerja, dan inilah alasannya.',
      'Kembali bukan berarti satuan kerja belum menindaklanjuti — tindak lanjutnya sudah ada, tapi dinilai masih kurang.',
    ]" />
  </div>
  @foreach($r->pengembalian as $k)
    <div style="display:flex;gap:12px;padding-bottom:12px;margin-bottom:12px;@if(!$loop->last)border-bottom:1px solid var(--rule-2)@endif">
      <div class="mono" style="font-size:11px;color:var(--ink-3);flex:none;width:74px;padding-top:1px">{{ Tampil::tgl($k->tanggal) }}</div>
      <div>
        <div style="font-size:13px">{{ $k->alasan }}</div>
        <div class="lbl" style="margin-top:3px">dikembalikan {{ $k->label_oleh }}</div>
      </div>
    </div>
  @endforeach
</div>
@endif

@include('bagian/permintaan-ubah', ['r' => $r])

{{-- Dua otoritas, dua tabel. Prototipe memisahkannya, dan alasannya bukan
     tata letak: LHV dari UKI menilai berkas SATU satuan kerja, sedangkan CHV
     dari Inspektorat memutus SELURUH rekomendasi. Ditumpuk dalam satu tabel,
     pembacanya tidak bisa tahu keputusan mana yang berlaku untuk apa.

     Validasi UKI berdiri lebih dulu karena memang lebih dulu dalam alurnya —
     berkas tidak naik ke Inspektorat sebelum UKI memvalidasi. --}}
@php
  $lhv = $r->keputusan->filter(fn ($k) => $k->verifikasi->jenis === \App\Models\Verifikasi::LHV);
  $chv = $r->keputusan->filter(fn ($k) => $k->verifikasi->jenis === \App\Models\Verifikasi::CHV);
@endphp

@if($lhv->isNotEmpty())
<div class="kartu" id="r-validasi" style="margin-bottom:14px;padding:0;scroll-margin-top:80px">
  <div style="padding:var(--s5) var(--s5) 4px">
    <div class="judulkartu" style="margin-bottom:0">
      <span class="ic-kotak"><x-ik nama="papan-cek" ukuran="17" /></span>
      <h3>Riwayat validasi</h3>
      <span class="n">UKI &middot; {{ $lhv->count() }} surat</span>
    </div>
  </div>
  <div class="tw" style="border:0;border-radius:0">
    <table>
      <thead><tr>
        <th>Nomor validasi</th><th>Tanggal surat</th>
        <th>Keputusan</th><th>Catatan</th><th>Surat</th>
      </tr></thead>
      <tbody>
      @foreach($lhv as $k)
        <tr>
          <td class="mono" style="font-size:11.5px">
            {{ $k->verifikasi->nomor_surat }}
            @if($k->verifikasi->periode)
              <div class="lbl" style="margin-top:3px">{{ $k->verifikasi->periode }}</div>
            @endif
          </td>
          <td class="mono" style="font-size:12px">{{ Tampil::tgl($k->verifikasi->tgl_surat) }}</td>
          <td><span class="cap {{ $k->hasil->cap() }}">{{ $k->hasil->nama($sumber) }}</span></td>
          <td style="font-size:12.5px">{{ $k->catatan }}</td>
          <td>
            @if($k->verifikasi->lampiran)
              @include('bagian/berkas', ['b' => $k->verifikasi->lampiran])
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

@if($chv->isNotEmpty())
<div class="kartu" id="r-verifikasi" style="margin-bottom:14px;padding:0;scroll-margin-top:80px">
  <div style="padding:var(--s5) var(--s5) 4px">
    <div class="judulkartu" style="margin-bottom:0">
      <span class="ic-kotak kuning"><x-ik nama="stempel" ukuran="17" /></span>
      <h3>Riwayat verifikasi</h3>
      <span class="n">Inspektorat &middot; {{ $chv->count() }} surat</span>
    </div>
  </div>
  <div class="tw" style="border:0;border-radius:0">
    <table>
      <thead><tr>
        <th>Nomor CHV</th><th>Tanggal LHV</th>
        <th>Keputusan <x-info :teks="[
          'Putusan Itjen atas SELURUH rekomendasi, bukan atas satu satuan kerja.',
          'Memadai hanya kalau seluruh satuan kerjanya sudah memadai; satu belum, seluruh rekomendasinya belum.',
          'Karena itu satu rekomendasi bisa punya beberapa CHV. Yang berlaku selalu yang terakhir terbit.',
        ]" /></th>
        <th>Catatan</th><th>Surat</th>
      </tr></thead>
      <tbody>
      @foreach($chv as $k)
        <tr>
          <td class="mono" style="font-size:11.5px">
            {{ $k->verifikasi->nomor_surat }}
            @if($k->verifikasi->periode)
              <div class="lbl" style="margin-top:3px">{{ $k->verifikasi->periode }}</div>
            @endif
          </td>
          <td class="mono" style="font-size:12px">{{ Tampil::tgl($k->verifikasi->tgl_surat) }}</td>
          <td>
            <span class="cap {{ $k->hasil->cap() }}">{{ $k->hasil->nama($sumber) }}</span>
            @if($k->diakui_masih_terbuka)
              <div class="lbl" style="color:var(--brass);margin-top:3px">diakui masih terbuka</div>
            @endif
          </td>
          <td style="font-size:12.5px">
            {{ $k->catatan }}@if($k->tenggat_baru) &middot; tenggat baru {{ Tampil::tgl($k->tenggat_baru) }}@endif
          </td>
          <td>
            @if($k->verifikasi->lampiran)
              @include('bagian/berkas', ['b' => $k->verifikasi->lampiran])
            @endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

</div>

<div id="r-tindakan" style="scroll-margin-top:80px">
@include('rekomendasi/tindakan', ['r' => $r])
</div>

{{-- Bahan rujukan: tidak dibaca tiap kali halaman dibuka, jadi tertutup
     bawaannya. Yang menjelaskan dan yang perlu dikerjakan sudah berdiri
     sendiri di atas. --}}
<details class="rujukan" id="r-arsip" style="margin-top:22px;scroll-margin-top:80px">
  <summary>
    <x-ik nama="klip" ukuran="16" /> Arsip rekomendasi
    <span class="n">{{ $r->lampiran->count() }} berkas</span>
  </summary>
  <div class="kartu">
    <div class="judulkartu">
      <span class="ic-kotak"><x-ik nama="klip" ukuran="17" /></span>
      <h3>Arsip rekomendasi</h3>
      <x-info :teks="[
        'Tempat mengumpulkan seluruh berkas rekomendasi ini, baik yang diunggah satuan kerja maupun unit lain.',
        'Bukti setoran ikut tersimpan di sini, dan tetap tampil juga di tabel pemulihan nilai.',
        'Berkas hanya bisa ditarik oleh yang mengunggahnya, dan hanya selama berkasnya masih di mejanya sendiri.',
        'Begitu surat verifikasi terbit, seluruh berkas terkunci dan tidak bisa ditarik siapa pun.',
        'Berkas yang ditarik isinya hilang permanen, tapi catatan siapa yang menarik, kapan, dan alasannya tetap tersimpan.',
      ]" />
    </div>
    @forelse($r->lampiran as $b)
      <div style="margin-bottom:12px">
        @include('bagian/berkas', ['b' => $b])
        <div class="lbl" style="margin-top:4px">
          @if($b->ditarik())
            ditarik {{ Tampil::tgl($b->ditarik_pada) }} &middot; {{ $b->sebab_tarik->nama() }}
            @if($b->catatan_tarik) &middot; {{ $b->catatan_tarik }} @endif
          @else
            {{ $b->jenisDokumen?->nama }} &middot; {{ Tampil::tgl($b->diunggah_pada) }}
          @endif
        </div>
      </div>
    @empty
      <div style="font-size:13px;color:var(--ink-3)">Belum ada berkas yang diunggah.</div>
    @endforelse
    <div class="lbl" style="line-height:1.5;margin-top:4px">
      Berkas disajikan lewat rute terotorisasi, bukan tautan langsung ke folder penyimpanan.
    </div>
  </div>
</details>

<details class="rujukan" style="margin-top:12px">
  <summary>
    <x-ik nama="jam" ukuran="16" /> Riwayat aktivitas
    <span class="n">{{ $r->riwayat->count() }} kejadian</span>
  </summary>
  <div class="kartu" id="r-jejak" style="scroll-margin-top:80px">
    <div class="judulkartu">
      <span class="ic-kotak abu"><x-ik nama="jam" ukuran="17" /></span>
      <h3>Riwayat aktivitas</h3>
      <span class="n">{{ $r->riwayat->count() }} kejadian</span>
      <x-info teks="Jejak perpindahan berkas: siapa memindahkannya ke mana, dan kapan." />
    </div>
    @foreach($r->riwayat as $j)
      <div style="display:flex;gap:11px;padding-bottom:12px;margin-bottom:12px;@if(!$loop->last)border-bottom:1px solid var(--rule-2)@endif">
        <div class="mono" style="font-size:11px;color:var(--ink-3);flex:none;width:74px;padding-top:1px">{{ Tampil::tgl($j->waktu) }}</div>
        <div>
          <div style="font-size:13px">{{ $j->aksi }}</div>
          <div class="lbl" style="margin-top:2px">{{ $j->label_aktor }}</div>
        </div>
      </div>
    @endforeach
  </div>

</details>
@endsection
