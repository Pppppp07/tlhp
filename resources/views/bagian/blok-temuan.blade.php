@php
  use App\Enums\PosisiBerkas;
  use App\Support\Tampil;

  $lap = $t->laporan;
  $rek = $t->rekomendasi;

  $nilai   = (int) $t->nilai;
  $ditagih = (int) $rek->sum('nilai_pulih');
  $kembali = (int) $rek->sum(fn ($r) => $r->nilaiTerpulihkan());
  $tuntas  = $rek->filter(fn ($r) => $r->posisiTampil() === PosisiBerkas::SELESAI)->count();
  $mendesak = $rek->filter(fn ($r) => $r->perluPerhatian())->count();

  /* Temuan yang tercatat atas nama satuan kerja pembaca, tapi seluruh tindak
     lanjutnya jadi tugas satuan kerja lain. Ditandai Terlihat::pangkas(). */
  $hanyaPantau = $t->hanya_terperiksa ?? false;
@endphp

{{-- Satu temuan satu blok yang bisa dibuka-tutup. Laporan bisa memuat belasan
     temuan; kalau semua terbuka, isinya jadi dinding teks dan tidak ada yang
     membacanya. Yang genting tidak ikut hilang saat tertutup — jumlahnya
     tercetak di kepalanya. --}}
<details class="temblok">
  <summary>
    <span class="panah">&rsaquo;</span>
    <span class="no mono">{{ $nomor }}</span>
    <span class="isi">
      <b>{{ $t->judul }}</b>
      <span class="lbl">
        {{ $t->kode }} &middot; butir {{ $t->nomor_pada_surat }}
        &middot; {{ $rek->count() }} rekomendasi
        @if($tuntas > 0) &middot; {{ $tuntas }} tuntas @endif
        @if($nilai > 0) &middot; {{ Tampil::rupiahSingkat($nilai) }} @endif
      </span>
    </span>
    @if($mendesak > 0)
      <span class="lbl" style="color:var(--bad);font-weight:600;flex:none">
        {{ $mendesak }} perlu perhatian
      </span>
    @endif
    @if($hanyaPantau)
      <span class="pantau"><x-ik nama="mata" ukuran="12" /> untuk diketahui</span>
    @else
      <span class="cap cap-{{ $t->statusSimpulan()->value }}">
        {{ $t->statusSimpulan()->value }} &middot; {{ $t->statusSimpulan()->nama() }}
      </span>
    @endif
  </summary>

  <div class="isitem">
    {{-- Kategori naik ke atas sebagai lencana. Sebagai kalimat abu-abu di dasar
         blok ia terbaca seperti catatan kaki — padahal justru itu yang memberi
         tahu jenis perkaranya sebelum uraiannya dibaca.

         Namanya ikut ditulis di depan tiap lencana: dua lencana berdampingan
         tanpa nama memaksa pembacanya menebak mana yang dari pemeriksa dan
         mana yang penggolongan sendiri. --}}
    <div class="katbaris">
      <span class="lbl" style="margin:0">Kategori temuan</span>
      <span class="tagkat">{{ $t->kategori?->nama ?: 'belum dipilih' }}</span>
      <span class="lbl" style="margin:0 0 0 6px">Kategori internal</span>
      {{-- Warnanya dari data master, bukan satu abu-abu untuk semuanya:
           sekali lihat sudah ketahuan perkaranya jenis apa. --}}
      @if($t->kategoriIntern)
        <span class="tagkat dalam berwarna"
          style="--w:{{ $t->kategoriIntern->warnaLabel()->padat() }}">
          {{ $t->kategoriIntern->nama }}
        </span>
      @else
        <span class="tagkat dalam">belum dipilih</span>
      @endif
      <x-info :teks="[
        $lap->sumber->value === 'LHP'
          ? 'Kategori temuan: penggolongan dari BPK, tertulis apa adanya dari suratnya.'
          : 'Kategori temuan: penggolongan dari Inspektorat, tertulis apa adanya dari suratnya.',
        'Kategori internal: penggolongan BPSDM sendiri, dipakai mengelompokkan temuan sejenis untuk rekap ke dalam.',
        'Keduanya diisi Setba pada saat mencatat Laporan Baru.',
      ]" />
    </div>

    {{-- Sebab dan akibat saja. Kondisi dan kriteria panjang — dua paragraf
         penuh — dan di sini pembacanya sedang menyapu daftar temuan, belum
         mendalami satu perkara. Keduanya tetap lengkap di halaman rincian
         rekomendasi, di ruas "Uraian temuan". --}}
    <div class="unsur">
      <div><div class="lbl">Sebab</div><p>{{ $t->sebab ?: '—' }}</p></div>
      <div><div class="lbl">Akibat</div><p>{{ $t->akibat ?: '—' }}</p></div>
    </div>

    {{-- Angkanya dipisah jadi kolom berlabel, bukan dirangkai jadi kalimat.
         Dirangkai, bunyinya "Nilai temuan Rp 2.000.000 kembali dari
         Rp 4.250.000" — namanya menyebut nilai temuan sementara angka di
         sebelahnya justru yang sudah kembali, dan pembacanya harus membongkar
         sendiri mana yang mana. --}}
    @if($nilai > 0 && ! $hanyaPantau)
      <div class="uang">
        <span class="bar dana">
          <i style="width:{{ $ditagih ? min(100, round($kembali / $ditagih * 100)) : 0 }}%"></i>
        </span>
        <div class="angka">
          <div>
            <b>{{ Tampil::rupiah($nilai) }}</b>
            <span class="lbl">Nilai temuan</span>
          </div>

          @if($ditagih > 0 && $ditagih !== $nilai)
            <div>
              <b>{{ Tampil::rupiah($ditagih) }}</b>
              <span class="lbl">Harus dikembalikan</span>
            </div>
          @endif

          @if($ditagih > 0)
            <div>
              {{-- "Rp 0", bukan em dash. Dash terbaca "tidak ada datanya";
                   nol yang kembali itu keterangan, bukan ketiadaan. --}}
              <b style="color:var(--ok)">{{ $kembali ? Tampil::rupiah($kembali) : 'Rp 0' }}</b>
              <span class="lbl">Sudah kembali</span>
            </div>
            <div>
              <b style="color:{{ $ditagih - $kembali > 0 ? 'var(--bad)' : 'var(--ok)' }}">
                {{ $ditagih - $kembali > 0 ? Tampil::rupiah($ditagih - $kembali) : 'Lunas' }}
              </b>
              <span class="lbl">Belum kembali</span>
            </div>
          @endif

          @if($ditagih < $nilai)
            <div>
              <b style="color:var(--jingga)">{{ Tampil::rupiah($nilai - $ditagih) }}</b>
              <span class="lbl">
                Administratif
                <x-info teks="Bagian nilai temuan yang tidak perlu disetor — cukup dilengkapi dokumennya atau diperbaiki prosedurnya." />
              </span>
            </div>
          @endif
        </div>
      </div>
    @endif

    @if($hanyaPantau)
      <div class="pesan" style="margin:0">
        <x-ik nama="mata" ukuran="16" />
        <span>
          Temuan ini tercatat atas nama satuan kerja Anda, tetapi tindak lanjutnya
          menjadi tugas satuan kerja lain. Tidak ada yang perlu Anda kerjakan di sini.
        </span>
      </div>
    @endif

    {{-- Tabel, bukan tumpukan kartu. Satu temuan bisa memuat empat rekomendasi;
         sebagai kartu, membandingkannya berarti menggulung layar panjang-panjang
         — padahal yang dibandingkan cuma enam keterangan yang sama di tiap
         baris. --}}
    <div class="tw">
      <table>
        <thead>
          <tr>
            <th class="num">No</th>
            <th>Uraian rekomendasi</th>
            <th>Kode</th>
            <th>Satuan kerja</th>
            <th>Tenggat jawab</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rek as $j => $r)
            @php
              $telat = $r->lewatTenggat();
              $satker = $r->daftarSasaran()->map(fn ($x) => $x->satker?->namaPendek())
                ->filter()->unique()->values();
            @endphp
            <tr @class(['bukaan', 'awas' => $r->perluPerhatian()])
                data-buka="tr{{ $r->id }}">
              <td class="num mono" style="font-size:12px">
                <span class="panahbaris"><x-ik nama="panah-kanan" ukuran="13" /></span>
                {{ $nomor }}.{{ $j + 1 }}
              </td>
              <td style="max-width:380px;font-size:13px">{{ $r->uraian }}</td>
              <td class="mono" style="font-size:11.5px">
                {{ $r->refLhp() }}
                <div class="lbl" style="margin-top:3px">{{ $r->kode }}</div>
              </td>
              <td style="font-size:12.5px">
                {{ $satker->count() === 1 ? $satker->first()
                   : ($satker->count() ? $satker->count().' satuan kerja' : '—') }}
              </td>
              <td class="mono" style="font-size:12px;{{ $telat ? 'color:var(--bad)' : '' }}">
                {{ Tampil::tgl($r->renaksiUntuk(auth()->user()->satker_id)) }}
                @if($telat)
                  <div class="lbl" style="color:var(--bad)">lewat {{ $telat }} hari</div>
                @endif
              </td>
              <td>
                <span class="cap cap-{{ $r->status->value }}">
                  {{ $r->status->value }} &middot; {{ $r->status->nama() }}
                </span>
              </td>
            </tr>

            <tr class="lebar" id="tr{{ $r->id }}">
              <td colspan="6">@include('bagian/rinci-rekomendasi', ['r' => $r])</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</details>
