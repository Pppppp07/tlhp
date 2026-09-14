@php
  use App\Support\Tampil;
  use App\Enums\SumberLaporan;

  $sumber = SumberLaporan::from($s['sumber']);
  $tenggat = $s['tgl_terima']
    ? \App\Models\Rekomendasi::hitungTenggat(\Carbon\Carbon::parse($s['tgl_terima']), $sumber)
    : null;

  $namaSatker = $satker->keyBy('id');
  $namaKategori = $kategori->keyBy('id');
  $namaIntern = $intern->keyBy('id');
  $namaBentuk = $bentuk->keyBy('id');
  $namaSifat = $sifat->keyBy('id');
@endphp

{{-- Langkah terakhir: yang dibaca ulang sebelum dikirim. Tidak ada isian di
     sini — semuanya sudah terisi, tinggal dipastikan. --}}
<div class="kartu" style="margin-bottom:16px">
  <div class="judulkartu">
    <span class="ic-kotak"><x-ik nama="berkas-teks" ukuran="17" /></span>
    <h3>Surat laporan</h3>
    <div style="flex:1"></div>
    <form method="post" action="{{ route('laporan.baru.langkah', 1) }}">@csrf
      <button class="btn btn-s" type="submit">Ubah</button>
    </form>
  </div>
  <dl class="kv">
    <dt>Sumber</dt><dd>{{ $sumber->nama() }}</dd>
    <dt>Nomor surat</dt><dd class="mono">{{ $s['nomor'] }}</dd>
    <dt>Tanggal surat</dt><dd class="mono">{{ Tampil::tgl($s['tgl_surat']) }}</dd>
    <dt>Diterima Setba</dt><dd class="mono">{{ Tampil::tgl($s['tgl_terima']) }}</dd>
    <dt>Tenggat jawaban</dt>
    <dd class="mono">{{ $tenggat ? Tampil::tgl($tenggat) : '—' }}
      <div class="lbl" style="margin-top:2px">
        {{ $sumber->hariTenggat() }} {{ $sumber->pakaiHariKerja() ? 'hari kerja' : 'hari kalender' }}
        &middot; dasar {{ $sumber->dasarHukum() }}
      </div>
    </dd>
  </dl>
</div>

<div class="judulkartu" style="margin-bottom:12px">
  <span class="ic-kotak hijau"><x-ik nama="papan-cek" ukuran="17" /></span>
  <h3>Isi laporan</h3>
  <div style="flex:1"></div>
  <form method="post" action="{{ route('laporan.baru.langkah', 2) }}">@csrf
    <button class="btn btn-s" type="submit">Ubah</button>
  </form>
</div>

@foreach($d['temuan'] as $i => $t)
  <div class="kartu" style="margin-bottom:12px">
    <div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap;margin-bottom:6px">
      <span class="mono" style="font-weight:700">{{ $i + 1 }}</span>
      <b style="font-size:14px;font-weight:600">{{ $t['judul'] }}</b>
    </div>
    <div class="lbl" style="margin-bottom:12px">
      @if($t['nomor_pada_surat']) butir {{ $t['nomor_pada_surat'] }} &middot; @endif
      {{ collect((array) $t['satker'])->map(fn ($id) => $namaSatker[$id]->namaPendek() ?? null)->filter()->join(', ') ?: '—' }}
      &middot; {{ $namaKategori[$t['kategori']]->nama ?? '—' }}
      @if($t['kategori_intern']) &middot; {{ $namaIntern[$t['kategori_intern']]->nama ?? '' }} @endif
      &middot; nilai {{ Tampil::rupiah((int) preg_replace('/\D/', '', (string) $t['nilai'])) }}
    </div>

    <div class="unsur" style="margin-bottom:12px">
      @if($t['sebab'])<div><div class="lbl">Sebab</div><p>{{ $t['sebab'] }}</p></div>@endif
      @if($t['akibat'])<div><div class="lbl">Akibat</div><p>{{ $t['akibat'] }}</p></div>@endif
    </div>

    @foreach($t['rekom'] as $j => $r)
      @php
        $tindakan = collect($r['tindakan'] ?? [])
          ->map(fn ($tk) => [
            'bentuk' => $tk['bentuk'] ?? '',
            'tgl' => $tk['tgl_renaksi'] ?? '',
            'baris' => collect($tk['sasaran'] ?? [])->filter(fn ($x) => filled($x['satker'] ?? null)),
          ]);
        $pulih = $tindakan->sum(fn ($tk) => $tk['baris']
          ->sum(fn ($x) => (int) preg_replace('/\D/', '', (string) ($x['nilai'] ?? ''))));
      @endphp
      <div style="background:var(--surface-2);border:1px solid var(--line);border-radius:var(--r1);
        padding:11px 13px;margin-bottom:8px">
        <div style="display:flex;gap:9px;align-items:baseline;flex-wrap:wrap;margin-bottom:5px">
          <span class="mono" style="font-weight:700">{{ $i + 1 }}.{{ $j + 1 }}</span>
          @if($r['sifat'] ?? null)
            <span class="lbl">{{ $namaSifat[$r['sifat']]->nama ?? '' }}</span>
          @endif
        </div>
        <div style="font-size:13px;line-height:1.5">{{ $r['uraian'] }}</div>

        {{-- Satu blok satu bentuk tindak lanjut, berikut satuan kerjanya:
             begitulah ia akan berdiri di sistem, dan begitu pula yang dilihat
             masing-masing satuan kerja di layarnya sendiri. --}}
        @foreach($tindakan as $tk)
          <div style="margin-top:8px">
            <div class="lbl" style="margin-bottom:4px">
              {{ $namaBentuk[$tk['bentuk']]->nama ?? 'bentuk belum dipilih' }}
              @if($tk['tgl'])
                &middot; rencana aksi {{ Tampil::tgl($tk['tgl']) }}
              @endif
            </div>
            @forelse($tk['baris'] as $x)
              @php $n = (int) preg_replace('/\D/', '', (string) ($x['nilai'] ?? '')); @endphp
              <span class="keping">
                {{ $namaSatker[$x['satker']]->namaPendek() ?? '—' }}
                @if($n)<b>{{ Tampil::rupiahSingkat($n) }}</b>@endif
              </span>
            @empty
              <span class="lbl" style="color:var(--verm)">belum ditujukan ke satuan kerja mana pun</span>
            @endforelse
          </div>
        @endforeach

        @if($pulih)
          <div class="lbl" style="margin-top:6px">total {{ Tampil::rupiah($pulih) }}</div>
        @endif
      </div>
    @endforeach
  </div>
@endforeach

<div class="pesan info">
  Begitu diajukan, seluruh rekomendasi langsung berpindah ke satuan kerjanya masing-masing
  dengan status <b>BT &middot; belum ditindaklanjuti</b>, dan tenggat jawabannya mulai berjalan.
  Nomor surat tidak bisa diubah lagi sesudah ini.
</div>

<div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
  <form method="post" action="{{ route('laporan.baru.langkah', 2) }}">@csrf
    <button class="btn" type="submit"><x-ik nama="panah-kiri" ukuran="15" /> Sebelumnya</button>
  </form>
  <form method="post" action="{{ route('laporan.baru.ajukan') }}">
    @csrf
    <button class="btn btn-p" type="submit">
      Ajukan laporan
    </button>
  </form>
  <span class="hint">
    {{ $kurang2 ?: count($d['temuan']) . ' temuan, ' .
       collect($d['temuan'])->sum(fn ($t) => count($t['rekom'])) . ' rekomendasi' }}
  </span>
</div>
