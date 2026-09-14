@extends('rangka')
@section('judul', 'Rincian laporan pemeriksaan')
@section('isi')
@php
  use App\Support\Tampil;

  $a = $l->angka();
@endphp

<a class="taut" href="{{ route('laporan.index') }}" style="margin-bottom:14px;display:inline-flex">
  <x-ik nama="panah-kiri" ukuran="16" /> Kembali ke daftar laporan
</a>

<div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;margin-bottom:18px">
  <div style="flex:1;min-width:0">
    <h2 style="margin:0;font-size:24px;font-weight:700;letter-spacing:-.03em;
      display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      {{ $l->sumber->value }} {{ $l->nomor }}
      <span class="sumber sumber-{{ $l->sumber->value }}">{{ $l->sumber->value }}</span>
    </h2>
    <div style="font-size:13.5px;color:var(--ink-3);margin-top:5px">
      Satuan kerja: {{ $l->ringkasSatkerDiperiksa() }}
    </div>
  </div>
  @if($l->tuntas())<span class="cap cap-SS">Sudah selesai</span>@endif
</div>

@if($dipangkas)
  <div class="pesan" style="margin-bottom:16px">
    Halaman ini menampilkan bagian yang menyangkut satuan kerja Anda saja.
    Angka di bawah dihitung dari bagian itu, bukan dari seluruh isi laporan.
  </div>
@endif

<div class="dua">
  <div>
    {{-- Satu kartu, satu daftar ke bawah. Dulu dua kartu: kotak angka di
         atas, daftar keterangan di bawah — padahal keduanya menerangkan hal
         yang sama dan tak satu pun bisa dikerjakan. "2 temuan" dan "nomor
         surat" sama-sama satu nama dengan satu isi; yang memecahnya cuma
         bentuknya.

         Dibagi tiga kelompok supaya daftar sepanjang ini tetap terbaca:
         surat itu sendiri, isi laporannya, lalu uangnya. --}}
    @php
      $jeda = $l->jedaPencatatan();
      $sisaTenggat = (int) now()->startOfDay()->diffInDays($l->tenggatJawab()->startOfDay(), false);
      $sisaDana = max(0, $a['tagihan'] - $a['masuk']);
    @endphp

    <div class="kartu" style="margin-bottom:18px">
      <div class="judulkartu">
        <span class="ic-kotak"><x-ik nama="kotak-empat" ukuran="17" /></span>
        <h3>Ringkasan kondisi laporan</h3>
      </div>

      <div class="subjudul"><b>Surat</b><span class="garis"></span></div>
      <dl class="kv">
        <dt>Sumber</dt>
        <dd>{{ $l->sumber->value }} <span class="lbl">&middot; {{ $l->sumber->nama() }}</span></dd>

        {{-- Nomor surat berdampingan dengan sumbernya — dua keterangan yang
             menjawab "surat yang mana", dan dulu terpisah oleh dua baris
             tanggal. --}}
        <dt>Nomor surat</dt><dd class="mono">{{ $l->nomor }}</dd>

        <dt>Surat diterima Setba <x-info :teks="[
          'Tanggal surat pemeriksaan sampai di Setba, diambil dari cap terima suratnya.',
          'Dari tanggal inilah tenggat jawaban dihitung — bukan dari tanggal pencatatannya di sistem.',
        ]" /></dt>
        <dd class="mono">{{ Tampil::tgl($l->tgl_terima) }}</dd>

        <dt>Dicatat di sistem <x-info :teks="[
          'Tanggal Setba memasukkan laporan ini ke sistem. Diisi sendiri oleh sistem saat disimpan, tidak diketik.',
          'Jaraknya dari tanggal surat diterima penting: selama surat belum dicatat, tenggat jawabannya sudah berjalan tapi belum ada yang bisa mengerjakannya.',
        ]" /></dt>
        <dd class="mono">
          {{ Tampil::tgl($l->dicatat_pada) }}
          <span class="lbl">&middot;
            {{ $jeda === null ? 'belum tercatat'
               : ($jeda > 0 ? $jeda.' hari setelah surat diterima' : 'hari yang sama') }}</span>
        </dd>

        <dt>Tanggal rencana aksi <x-info :teks="[
          $l->sumber->hariTenggat().' '.($l->sumber->pakaiHariKerja() ? 'hari kerja' : 'hari kalender').' sejak laporan diterima.',
          'Dasarnya '.$l->sumber->dasarHukum().'.',
          $l->sumber->pakaiHariKerja()
            ? 'Dihitung hari kerja, jadi Sabtu, Minggu, dan hari libur tidak ikut dihitung.'
            : 'Dihitung hari kalender, jadi Sabtu dan Minggu ikut dihitung.',
        ]" /></dt>
        <dd class="mono" @if($sisaTenggat < 0) style="color:var(--verm)" @endif>
          {{ Tampil::tgl($l->tenggatJawab()) }}
          <span class="lbl">&middot;
            {{ $sisaTenggat < 0 ? 'lewat '.abs($sisaTenggat).' hari'
               : ($sisaTenggat === 0 ? 'jatuh tempo hari ini' : $sisaTenggat.' hari lagi') }}</span>
        </dd>

        <dt>Satuan kerja diperiksa <x-info :teks="[
          'Satuan kerja tempat temuannya terjadi, diturunkan dari temuan-temuannya — bukan diisi terpisah di kepala laporan.',
          'Berbeda dari satuan kerja penanggung jawab di tiap rekomendasi: yang menanggung perbaikan belum tentu yang diperiksa.',
        ]" /></dt>
        <dd>{{ $l->daftarSatkerDiperiksa() }}</dd>
      </dl>

      <div class="subjudul" style="margin-top:18px"><b>Isi laporan</b><span class="garis"></span></div>
      <dl class="kv">
        <dt>Temuan</dt><dd><b class="angkafakta">{{ $a['temuan'] }}</b> temuan</dd>
        <dt>Rekomendasi</dt><dd><b class="angkafakta">{{ $a['jml'] }}</b> rekomendasi</dd>
        <dt>Rekomendasi selesai</dt>
        <dd style="color:var(--stamp)"><b class="angkafakta">{{ $a['selesai'] }}</b> dari {{ $a['jml'] }}</dd>

        @php
          /* Dihitung per BARIS satuan kerja, bukan per rekomendasi: satu
             rekomendasi yang dipikul tiga satker memang berada di tiga meja
             sekaligus, dan menyebutnya satu tempat menyembunyikan dua. */
          $sebaran = collect($a['pos'])->reject(fn ($n, $k) => $k === 'Selesai');
        @endphp
        @if($sebaran->isNotEmpty())
          <dt>Posisi berkas</dt>
          <dd>
            <span class="kepingsatker">
              @foreach($sebaran as $nama => $n)
                <span class="keping">{{ $nama }} <i>{{ $n }}</i></span>
              @endforeach
            </span>
          </dd>
        @endif
      </dl>

      @if($a['nilaiTemuan'] || $a['tagihan'])
        <div class="subjudul" style="margin-top:18px"><b>Pemulihan dana</b><span class="garis"></span></div>
        {{-- Berbaris ke bawah, rantainya justru lebih terbaca daripada saat
             berjajar: tiap angka punya barisnya sendiri dan namanya berdiri di
             kolom yang sama. --}}
        <dl class="kv">
          <dt>Total nilai temuan <x-info :teks="[
            'Jumlah semua temuan di laporan ini. Tidak semuanya harus dikembalikan berupa uang.',
          ]" /></dt>
          <dd class="mono"><b class="angkafakta">{{ Tampil::rupiah($a['nilaiTemuan']) }}</b></dd>

          @if($a['tagihan'] !== $a['nilaiTemuan'])
            <dt>Tagihan rekomendasi <x-info :teks="[
              'Bagian yang memang harus disetor ke kas negara sesuai bunyi rekomendasinya.',
            ]" /></dt>
            <dd class="mono"><b class="angkafakta">{{ Tampil::rupiah($a['tagihan']) }}</b></dd>

            {{-- Selisih antara nilai temuan dan yang ditagih bukan uang yang
                 hilang: sebagian temuan diselesaikan dengan melengkapi dokumen
                 atau memperbaiki prosedur, bukan dengan menyetor. Tetap perlu
                 disebut — nilai yang menggantung tanpa nama tidak bisa
                 diawasi. --}}
            <dt>Administratif <x-info :teks="[
              'Bagian yang tidak perlu disetor — cukup dilengkapi dokumennya atau diperbaiki prosedurnya.',
              'Bisa berubah jadi tagihan kalau buktinya tidak pernah ada.',
            ]" /></dt>
            <dd class="mono" style="color:var(--jingga)">
              <b class="angkafakta">{{ Tampil::rupiah(max(0, $a['nilaiTemuan'] - $a['tagihan'])) }}</b>
            </dd>
          @endif

          <dt>Sudah dipulihkan</dt>
          <dd class="mono" style="color:var(--stamp)"><b class="angkafakta">{{ Tampil::rupiah($a['masuk']) }}</b></dd>

          <dt>{{ $sisaDana > 0 ? 'Sisa yang harus dipulihkan' : 'Sisa tagihan' }}</dt>
          <dd class="mono" style="color:{{ $sisaDana > 0 ? 'var(--verm)' : 'var(--stamp)' }}">
            <b class="angkafakta">{{ $sisaDana > 0 ? Tampil::rupiah($sisaDana) : 'Sudah lunas' }}</b>
          </dd>
        </dl>
      @endif

      {{-- Surat aslinya tidak diberikan ke satuan kerja. Satu surat memuat
           seluruh temuan pada seluruh satuan kerja — memberi tautannya sama
           saja membuka temuan satuan kerja lain lewat pintu yang paling lebar,
           sesudah susah payah menyaringnya di seluruh layar lain. --}}
      {{-- Dijaga perannya saja, bukan ada-tidaknya lampiran. Kalau
           `isNotEmpty()` ikut menjaga, seluruh bloknya lenyap justru waktu
           belum ada tautannya — dan yang membaca tidak bisa membedakan
           "belum ditautkan" dari "tidak ditampilkan untuk Anda". --}}
      @if(auth()->user()->peran !== \App\Enums\PeranPengguna::SATKER)
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line-2);
          display:flex;gap:14px;align-items:center;flex-wrap:wrap">
          <div class="lbl">Dokumen asli</div>
          {{-- Yang belum ada disebut begitu. Label yang berdiri sendiri tanpa
               apa pun di sebelahnya terbaca sebagai layar rusak, bukan sebagai
               "belum ada". --}}
          @forelse($l->lampiran as $b)
            @include('bagian/berkas', ['b' => $b])
          @empty
            <span class="belumada">belum ditautkan &mdash; bisa dilengkapi belakangan</span>
          @endforelse
        </div>
      @endif
    </div>

    <div class="judulkartu" style="margin-bottom:14px">
      <span class="ic-kotak hijau"><x-ik nama="papan-cek" ukuran="17" /></span>
      <h3>Daftar temuan</h3>
      <x-info :teks="[
        'Satu temuan bisa melahirkan beberapa rekomendasi, jadi angka rekomendasi selalu lebih besar atau sama dengan angka temuan.',
        'Satuan kerja yang mengerjakan tertulis di tiap baris rekomendasi, bukan di temuannya — rekomendasi yang lahir dari satu temuan bisa ditujukan ke satuan kerja yang berbeda.',
      ]" />
      <span class="hitungtr">
        <b>{{ $a['temuan'] }}</b> temuan
        <x-ik nama="panah-kanan" ukuran="12" />
        <b>{{ $a['jml'] }}</b> rekomendasi
      </span>
    </div>

    {{-- Daftar lurus, tiap temuan berdiri sendiri dengan judulnya.

         Dulu dikelompokkan per satuan kerja. Dua sebab dibuang. Yang berdiri
         paling menonjol jadi nama satuan kerja, padahal yang dicari pembaca
         judul temuannya. Dan pengelompokannya menggandakan barisnya: satu
         temuan yang mengenai tiga satuan kerja akan muncul tiga kali, sekali
         di tiap kelompok — belum pernah kejadian karena data sekarang belum
         punya temuan seperti itu, tapi datanya memang mengizinkannya.

         Satuan kerjanya tetap tersebut, di dalam tiap baris rekomendasi,
         tempat ia memang menentukan siapa mengerjakan apa. --}}
    @foreach($l->temuan as $t)
      @include('bagian/blok-temuan', ['t' => $t, 'nomor' => $loop->iteration])
    @endforeach
  </div>

  <aside class="relkanan">
    <div class="kartu rapat">
      <div class="judulkartu">
        <span class="ic-kotak kuning"><x-ik nama="kalender" ukuran="17" /></span>
        <h3>Jadwal penting</h3>
      </div>
      <div class="tenggat">
        <div><b>Tenggat jawaban</b></div>
        <span class="mono" style="font-size:12.5px;font-weight:600">{{ Tampil::tgl($l->tenggatJawab()) }}</span>
      </div>
      <div class="tenggat">
        <div><b>Rekomendasi terlambat</b></div>
        <span class="pil {{ $a['telat'] > 0 ? 'merah' : 'hijau' }}">{{ $a['telat'] }}</span>
      </div>
      <div class="tenggat">
        <div><b>Belum ditindaklanjuti</b></div>
        <span class="pil {{ $a['belumTL'] > 0 ? 'kuning' : 'hijau' }}">{{ $a['belumTL'] }}</span>
      </div>
    </div>
  </aside>
</div>
@endsection
