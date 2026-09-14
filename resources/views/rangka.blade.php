<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('judul', 'Monitoring TLHP')</title>
  <link rel="stylesheet" href="{{ asset('css/simtlhp.css') }}">
  {{-- Aset statis biasa, tanpa langkah build. Aplikasi yang butuh build akan
       tampil tanpa gaya sama sekali kalau ada yang memasangnya tanpa
       menjalankan build lebih dulu — dan gagalnya diam-diam. --}}
  <script src="{{ asset('js/simtlhp.js') }}" defer></script>
</head>
<body>
@auth
  @php
    $u = auth()->user();
    $peran = $u->peran;
    /* Antrean dibaca lewat sasaran: penugasan tidak lagi tersimpan di
       rekomendasi, dan satuan kerja hanya menghitung barisnya sendiri. */
    $antre = \App\Models\Rekomendasi::diMeja($peran)
        ->when($peran === \App\Enums\PeranPengguna::SATKER,
               fn ($q) => $q->whereHas('sasaran',
                   fn ($s) => $s->where('sasarans.satker_id', $u->satker_id)))
        ->count();
  @endphp
  <nav class="nav">
    <div class="brand">
      <span class="lambang">SB</span>
      <span>
        <b>SETBA</b>
        <span>Sistem Tindak Lanjut<br>Hasil Pemeriksaan</span>
      </span>
    </div>

    {{-- Menu datar tanpa kelompok, seperti prototipe. Sesudah layar
         Rekomendasi punya keranjangnya sendiri, menunya jadi cukup pendek
         untuk dibaca sekali lihat - dan daftar sependek ini tidak perlu
         dikelompokkan; judul kelompok justru memanjangkannya. --}}

    {{-- Slot yang sama untuk semua peran, cakupan yang berbeda. Penyaringnya
         sudah dikerjakan Terlihat, jadi satuan kerja membuka layar yang sama
         dan hanya melihat berkasnya sendiri. --}}
    <a class="item" href="{{ route('rekomendasi.index') }}"
       @if(request()->routeIs('rekomendasi.index')) aria-current="page" @endif>
      <x-ik nama="berkas-teks" ukuran="17" />
      <span class="tulisan">Rekomendasi</span>
      @if($antre)<span class="tanda">{{ $antre }}</span>@endif
    </a>

    <a class="item" href="{{ route('laporan.index') }}"
       @if(request()->routeIs('laporan.index') || request()->routeIs('laporan.show')) aria-current="page" @endif>
      <x-ik nama="tumpuk" ukuran="17" />
      <span class="tulisan">Daftar laporan</span>
    </a>

    @if($peran === \App\Enums\PeranPengguna::SETBA)
      @php
        $perluSiptl = \App\Models\Rekomendasi::whereIn('posisi', [
            \App\Enums\PosisiBerkas::SIPTL->value,
            \App\Enums\PosisiBerkas::BPK->value,
        ])->count();
      @endphp

      {{-- Urusan SIPTL memang milik Setba: mengunggah lalu menyalin status
           BPK. CHV tidak — itu urusan Inspektorat, dan terbit dari panel
           verifikasinya sendiri. --}}
      <a class="item" href="{{ route('siptl') }}"
         @if(request()->routeIs('siptl')) aria-current="page" @endif>
        <x-ik nama="dompet" ukuran="17" />
        <span class="tulisan">Urusan SIPTL</span>
        @if($perluSiptl)<span class="tanda">{{ $perluSiptl }}</span>@endif
      </a>
    @endif

    @if($peran === \App\Enums\PeranPengguna::UKI)
      @php $antreLhv = \App\Models\Sasaran::where('posisi',
          \App\Enums\PosisiBerkas::UKI->value)->count(); @endphp
      <a class="item" href="{{ route('validasi.form') }}"
         @if(request()->routeIs('validasi.*')) aria-current="page" @endif>
        <x-ik nama="papan-cek" ukuran="17" />
        <span class="tulisan">Terbitkan LHV</span>
        @if($antreLhv)<span class="tanda">{{ $antreLhv }}</span>@endif
      </a>
    @endif

    @if(in_array($peran, [\App\Enums\PeranPengguna::SETBA, \App\Enums\PeranPengguna::PIMPINAN,
                          \App\Enums\PeranPengguna::ADMIN], true))
      <a class="item" href="{{ route('ringkasan') }}"
         @if(request()->routeIs('ringkasan')) aria-current="page" @endif>
        <x-ik nama="kotak-empat" ukuran="17" />
        <span class="tulisan">Ringkasan</span>
      </a>
    @endif

    {{-- Data master berdiri paling bawah di antara menu tempat: ia jarang
         dibuka, dan yang membukanya sedang menyetel sistemnya, bukan
         mengerjakan berkas. --}}
    @if(in_array($peran, [\App\Enums\PeranPengguna::SETBA,
                          \App\Enums\PeranPengguna::ADMIN], true))
      <a class="item" href="{{ route('master') }}"
         @if(request()->routeIs('master')) aria-current="page" @endif>
        <x-ik nama="petak" ukuran="17" />
        <span class="tulisan">Data master</span>
      </a>
    @endif

    @php
      $kabarBaru = \App\Support\Kabar::belumDibaca($u);
      /* Kabar terbaru yang belum dibaca, dititipkan ke skrip lewat atribut
         data. Tidak ada permintaan tambahan ke peladen: datanya sudah ada di
         tangan saat halaman ini disusun. */
      $kabarPuncak = $kabarBaru
        ? \App\Support\Kabar::untuk($u)->first(fn ($k) => ! $k->dibaca->contains('id', $u->id))
        : null;
    @endphp
    <a class="item" href="{{ route('kabar') }}"
       @if(request()->routeIs('kabar')) aria-current="page" @endif>
      <x-ik nama="lonceng" ukuran="17" />
      <span class="tulisan">Pemberitahuan</span>
      @if($kabarBaru)<span class="tanda genting">{{ $kabarBaru }}</span>@endif
    </a>

    <div class="kaki">
      <div class="nm">{{ $u->name }}</div>
      <div class="pr">{{ $peran->nama() }}@if($u->satker && $u->satker->namaPendek() !== $peran->nama()) &middot; {{ $u->satker->namaPendek() }}@endif</div>
      <form method="post" action="{{ route('keluar') }}">@csrf<button type="submit">Keluar</button></form>
    </div>
  </nav>

  @if($kabarPuncak)
    <span id="kabar-baru" hidden
      data-jumlah="{{ $kabarBaru }}"
      data-tanda="{{ $kabarPuncak->id }}"
      data-pelaku="{{ $kabarPuncak->label_pelaku }}"
      data-aksi="{{ \Illuminate\Support\Str::limit($kabarPuncak->aksi, 110) }}"
      data-judul="{{ $kabarPuncak->rekomendasi?->kode }} &middot; {{ \Illuminate\Support\Str::limit($kabarPuncak->rekomendasi?->temuan->judul ?? '', 60) }}"
      data-tautan="{{ route('kabar') }}"></span>
  @endif

  <div class="utama">
    <header class="atas">
      <h1>@yield('judul', 'Beranda')</h1>
      {{-- Di batang atas, bukan di satu halaman saja: berkas dicari dari mana
           pun orang sedang berada, bukan hanya saat kebetulan sudah membuka
           daftar rekomendasi. --}}
      <form method="get" action="{{ route('rekomendasi.index') }}" class="cari-atas">
        <input type="search" name="cari" value="{{ request('cari') }}"
          placeholder="Cari kode, nomor surat, temuan, atau satuan kerja"
          aria-label="Cari berkas">
        <button class="btn btn-s" type="submit">Cari</button>
      </form>

      <div class="alat">
        {{-- Tersedia di halaman mana pun Setba berada — surat bisa datang kapan
             saja, dan memaksa kembali ke satu halaman dulu hanya menunda. --}}
        @if($peran === \App\Enums\PeranPengguna::SETBA)
          {{-- Disembunyikan saat formnya sendiri sedang dibuka: tombol yang
               menuju halaman yang sedang dilihat cuma menambah keraguan. --}}
          @unless(request()->routeIs('laporan.baru*'))
            <a class="btn btn-p" href="{{ route('laporan.baru') }}">+ Catat laporan baru</a>
          @endunless
        @endif
        <span class="lbl">{{ now()->translatedFormat('d M Y') }}</span>
        <a class="lonceng" href="{{ route('kabar') }}" aria-label="Pemberitahuan, {{ $kabarBaru }} belum dibaca">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
          </svg>
          @if($kabarBaru)<span class="n">{{ $kabarBaru }}</span>@endif
        </a>
      </div>
    </header>
    <div class="badan">
      @if(session('pesan'))<div class="pesan">{{ session('pesan') }}</div>@endif
      @if(session('gagal'))<div class="pesan bad">{{ session('gagal') }}</div>@endif
      @yield('isi')
    </div>
  </div>
@else
  @yield('isi')
@endauth
</body>
</html>
