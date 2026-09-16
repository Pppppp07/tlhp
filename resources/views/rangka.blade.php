<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('judul', 'Monitoring TLHP') · SETBA</title>
  {{-- Aset statis biasa, tanpa langkah build. simtlhp.css disalin dari
       prototipe; yang khusus Laravel ada di simtlhp-tambahan.css. --}}
  <link rel="stylesheet" href="{{ asset('css/simtlhp.css') }}?v={{ filemtime(public_path('css/simtlhp.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/simtlhp-tambahan.css') }}?v={{ filemtime(public_path('css/simtlhp-tambahan.css')) }}">
  <script src="{{ asset('js/simtlhp.js') }}?v={{ filemtime(public_path('js/simtlhp.js')) }}" defer></script>
</head>
<body>
@use('App\Enums\PeranPengguna', 'P')
@auth
  @php
    $u = auth()->user();
    $peran = $u->peran;
    $bingkai = \App\Support\Rangka::untuk($u);
    $akun = \App\Support\Rangka::pengguna($u);

    /* Menu datar tanpa kelompok, sama dengan prototipe. Butir yang tidak
       berlaku bagi sebuah peran dihilangkan, bukan diganti nama lain. */
    $menu = [
      /* Halaman rincian tidak menyalakan menu mana pun, sama seperti
         prototipe: yang menyala hanya layar yang memang butir menu. */
      ['rute' => 'rekomendasi.index', 'aktif' => ['rekomendasi.index'], 'ikon' => 'FileText', 'nama' => 'Rekomendasi', 'tanda' => $bingkai['perluKerja']],
      ['rute' => 'laporan.index', 'aktif' => ['laporan.index'], 'ikon' => 'Files', 'nama' => 'Daftar laporan'],
    ];
    if (in_array($peran, [P::SETBA, P::PIMPINAN, P::ADMIN], true)) {
      $menu[] = ['rute' => 'ringkasan', 'aktif' => ['ringkasan'], 'ikon' => 'LayoutGrid', 'nama' => 'Ringkasan'];
    }
    if (in_array($peran, [P::SETBA, P::ADMIN], true)) {
      $menu[] = ['rute' => 'master', 'aktif' => ['master*'], 'ikon' => 'ListChecks', 'nama' => 'Data master'];
    }
    $menu[] = ['rute' => 'kabar', 'aktif' => ['kabar'], 'ikon' => 'Bell', 'nama' => 'Pemberitahuan', 'tanda' => $bingkai['belumDibaca']];
  @endphp

  {{-- Batang atas hanya muncul di layar sempit — pembuka laci navigasi. --}}
  <div class="nav-atas">
    <button class="buka-nav" type="button" data-buka-nav aria-label="Buka menu"><x-ikon n="Menu" :s="18" /></button>
    <b>@yield('judul')</b>
  </div>
  <div class="tirai-nav" data-tutup-nav></div>

  <nav class="nav">
    <div class="brand">
      <span class="lambang">SB</span>
      <span class="teks">
        <b>SETBA</b>
        <span>Sistem Tindak Lanjut<br>Hasil Pemeriksaan</span>
      </span>
    </div>

    @foreach($menu as $m)
      <a class="menu" href="{{ route($m['rute']) }}" title="{{ $m['nama'] }}"
        @if(request()->routeIs(...$m['aktif'])) aria-current="page" @endif>
        <x-ikon :n="$m['ikon']" :s="17" />
        <span class="tulisan">{{ $m['nama'] }}</span>
        @if(($m['tanda'] ?? 0) > 0)<span class="n">{{ $m['tanda'] }}</span>@endif
      </a>
    @endforeach

    {{-- Di prototipe ini pemilih peran. Di sini akunnya sudah menentukan
         peran dan satuan kerjanya; yang tersisa menyebutnya dan keluar. --}}
    <div class="peran">
      <div class="lbl"><x-ikon n="Users" :s="12" /> Masuk sebagai</div>
      <div class="akunmasuk">
        <b>{{ $akun['peran'] }}</b>
        @if($peran === P::SATKER)
          <span><x-ikon n="Building2" :s="11" /> {{ $u->satker?->namaPendek() }}</span>
        @endif
        <form method="post" action="{{ route('keluar') }}">@csrf<button type="submit" class="btn btn-s">Keluar</button></form>
      </div>
    </div>

    <div class="bantuan">
      <div class="isi">
        <span class="ic-kotak" title="Panduan"><x-ikon n="HelpCircle" :s="17" /></span>
        <span>
          <b>Butuh bantuan?</b>
          <span>Panduan penggunaan sistem</span>
          <a href="#panduan">Buka panduan <x-ikon n="ExternalLink" :s="11" /></a>
        </span>
      </div>
    </div>
  </nav>

  <div class="main">
    <header class="top">
      <h1>@yield('judul')</h1>

      {{-- Hasilnya diambil dari rute yang sudah disaring hak aksesnya. --}}
      <div class="cari-glob" data-cari-glob data-sumber="{{ route('cari') }}">
        <span class="kotak">
          <x-ikon n="Search" :s="15" />
          <input type="text" placeholder="Cari kode, temuan, atau satuan kerja" aria-label="Cari" autocomplete="off">
          <kbd>/</kbd>
          <button class="bersih" type="button" aria-label="Kosongkan pencarian" hidden><x-ikon n="X" :s="13" /></button>
        </span>
        <div class="panel" hidden></div>
      </div>

      <div class="alat">
        @if($peran === P::SETBA && ! request()->routeIs('laporan.baru'))
          <a class="btn btn-p" href="{{ route('laporan.baru') }}">
            @if($bingkai['drafLaporan'])
              <x-ikon n="Save" :s="15" /> Lanjutkan draf laporan
            @else
              <x-ikon n="Plus" :s="15" /> Catat laporan baru
            @endif
          </a>
        @endif
        <span class="lbl" style="display:flex;align-items:center;gap:6px">
          <x-ikon n="Clock" :s="13" /> {{ \App\Support\Tampil::tgl(now()) }}
        </span>
        <a class="lonceng" href="{{ route('kabar') }}" aria-label="Pemberitahuan, {{ $bingkai['belumDibaca'] }} belum dibaca">
          <x-ikon n="Bell" :s="19" />
          @if($bingkai['belumDibaca'] > 0)<span class="n">{{ $bingkai['belumDibaca'] }}</span>@endif
        </a>
        <span class="pengguna">
          <span class="rupa">{{ $akun['rupa'] }}</span>
          <span class="teks">
            <b>{{ $akun['nama'] }}</b>
            <span>{{ $akun['ket'] }}</span>
          </span>
          <x-ikon n="ChevronDown" :s="15" style="color:var(--ink-3)" />
        </span>
      </div>
    </header>

    @if(session('pesan') || session('gagal') || $errors->any())
      <div class="body pesanbingkai">
        @if(session('pesan'))
          <div class="pesan ok"><x-ikon n="Check" :s="16" /><span>{{ session('pesan') }}</span></div>
        @endif
        @if(session('gagal') || $errors->any())
          <div class="pesan bad"><x-ikon n="AlertTriangle" :s="16" /><span>{{ session('gagal') ?? $errors->first() }}</span></div>
        @endif
      </div>
    @endif

    @yield('isi')
  </div>

  @if($s = $bingkai['sembul'])
    @php $k = $s['kabar']; @endphp
    <div class="sembul" role="status" aria-live="polite" data-sembul>
      <span class="lonceng-ikon"><x-ikon n="Bell" :s="15" /></span>
      <div class="teks">
        @if($s['jumlah'] === 1)
          <div class="atas">
            <x-sumber :j="$k->rekomendasi->jenis()" />
            <span class="mono kd">{{ $k->rekomendasi->kode }}</span>
          </div>
          <div class="apa"><b>{{ $k->label_pelaku }}</b> &mdash; {{ $k->aksi }}</div>
          <div class="lbl">{{ $k->rekomendasi->temuan->judul }} &middot; {{ \App\Support\Tampil::daftarPendek($k->satker) }}@if($k->tindakan) &middot; {{ $k->tindakan->namaBentuk() }}@endif</div>
          <a class="taut" href="{{ route('kabar.buka', $k) }}">Buka <x-ikon n="ChevronRight" :s="13" /></a>
        @else
          <div class="apa"><b>{{ $s['jumlah'] }} kabar baru</b> menunggu dibaca</div>
          <div class="lbl">Terbaru: {{ $k->label_pelaku }} &mdash; {{ $k->aksi }}</div>
          <a class="taut" href="{{ route('kabar') }}">Lihat semua <x-ikon n="ChevronRight" :s="13" /></a>
        @endif
      </div>
      <button class="tutup" type="button" aria-label="Tutup kabar" data-tutup-sembul><x-ikon n="X" :s="14" /></button>
    </div>
  @endif
@else
  @yield('isi')
@endauth
</body>
</html>
