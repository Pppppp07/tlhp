@php
  use App\Enums\HasilTelaah;
  use App\Support\Tampil;

  /* Satu temuan beserta rekomendasinya — padanan BlokTemuan. Selalu tertutup
     saat pertama tampil; yang genting tetap tercetak di kepalanya. */
  $mendesak = $tem->rekomendasi->filter(fn ($r) => $r->perluPerhatian())->count();
  $nilai = $tem->nilaiTemuan();
  $ditagih = (int) $tem->rekomendasi->sum('nilai_pulih');
  $kembali = (int) $tem->rekomendasi->sum(fn ($r) => $r->totalSetor());
  $memadai = $tem->rekomendasi->filter(fn ($r) => $r->keadaanUnor() === HasilTelaah::M)->count();
  $idIsi = 'temuan-'.$tem->id;
@endphp

<div class="temblok" data-temblok>
  <button type="button" class="kep" aria-expanded="false" aria-controls="{{ $idIsi }}" data-buka-temuan>
    <span class="panah" style="color:var(--ink-3);flex:none">›</span>
    <span class="mono" style="font-size:15px;font-weight:700;color:var(--ink-3);flex:none">{{ $i + 1 }}</span>
    <span style="flex:1;min-width:200px">
      <span style="display:block;font-size:14px;font-weight:600;letter-spacing:-.01em">{{ $tem->judul }}</span>
      <span class="lbl" style="display:block;margin-top:3px">
        {{ $tem->kode }} · butir {{ $tem->nomor_pada_surat }} · {{ $tem->rekomendasi->count() }} rekomendasi{{ $memadai > 0 ? ' · '.$memadai.' '.mb_strtolower(HasilTelaah::M->nama($jenis)) : '' }}{{ $nilai > 0 ? ' · '.Tampil::rupiahSingkat($nilai) : '' }}
      </span>
    </span>
    @if($mendesak > 0)
      <span class="lbl" style="color:var(--bad);font-weight:600;flex:none">{{ $mendesak }} perlu perhatian</span>
    @endif
    @if($tem->hanya_terperiksa)
      <span class="pantau" style="flex:none"><x-ikon n="Eye" :s="12" /> untuk diketahui</span>
    @else
      <x-cap :s="\App\Models\Rekomendasi::simpulkan($tem->rekomendasi)" :jenis="$jenis" />
    @endif
  </button>

  <div class="isi isitem" id="{{ $idIsi }}" data-isi-temuan>
    <div class="katbaris">
      <span class="lbl" style="margin:0">Kategori temuan</span>
      <span class="tagkat">{{ $tem->kategori?->nama ?? 'belum dipilih' }}</span>
      <span class="lbl" style="margin:0 0 0 6px">Kategori internal</span>
      <x-tag-kategori :kat="$tem->kategoriIntern" />
      <x-info :teks="[
        $jenis->melewatiSiptl()
          ? 'Kategori temuan: penggolongan dari BPK, tertulis apa adanya dari suratnya.'
          : 'Kategori temuan: penggolongan dari Inspektorat, tertulis apa adanya dari suratnya.',
        'Kategori internal: penggolongan BPSDM sendiri, dipakai mengelompokkan temuan sejenis untuk rekap ke dalam.',
        'Keduanya diisi Setba pada saat mencatat Laporan Baru.',
      ]" />
    </div>

    <div class="unsur">
      <div class="unsur"><div class="lbl" style="margin-bottom:4px">Sebab</div><div style="font-size:13px">{{ $tem->sebab ?: '—' }}</div></div>
      <div class="unsur"><div class="lbl" style="margin-bottom:4px">Akibat</div><div style="font-size:13px">{{ $tem->akibat ?: '—' }}</div></div>
    </div>

    @if($nilai > 0 && ! $tem->hanya_terperiksa)
      <div class="uang">
        <span class="bar dana"><i style="width:{{ min(100, $ditagih ? $kembali / $ditagih * 100 : 0) }}%"></i></span>
        <div class="angka">
          <div><b>{{ Tampil::rupiah($nilai) }}</b><span class="lbl">Nilai temuan</span></div>
          @if($ditagih > 0 && $ditagih !== $nilai)
            <div><b>{{ Tampil::rupiah($ditagih) }}</b><span class="lbl">Harus dikembalikan</span></div>
          @endif
          @if($ditagih > 0)
            <div><b style="color:var(--ok)">{{ $kembali ? Tampil::rupiah($kembali) : 'Rp 0' }}</b><span class="lbl">Sudah kembali</span></div>
            <div><b style="color:{{ $ditagih - $kembali > 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $ditagih - $kembali > 0 ? Tampil::rupiah($ditagih - $kembali) : 'Lunas' }}</b><span class="lbl">Belum kembali</span></div>
          @endif
          @if($ditagih < $nilai)
            <div>
              <b style="color:var(--jingga)">{{ Tampil::rupiah($nilai - $ditagih) }}</b>
              <span class="lbl">Administratif <x-info teks="Bagian nilai temuan yang tidak perlu disetor — cukup dilengkapi dokumennya atau diperbaiki prosedurnya." /></span>
            </div>
          @endif
        </div>
      </div>
    @endif

    @if($tem->hanya_terperiksa)
      <div class="pesan" style="margin:0">
        <x-ikon n="Eye" :s="16" />
        <span>Temuan ini tercatat atas nama satuan kerja Anda, tetapi tindak lanjutnya menjadi tugas satuan kerja lain. Tidak ada yang perlu Anda kerjakan di sini.</span>
      </div>
    @endif

    <div class="tw">
      <table data-tabel-temuan>
        <thead>
          <tr><th class="num">No</th><th>Uraian rekomendasi</th><th>Kode</th><th>Satuan kerja</th><th>Tenggat jawab</th>
            <th>Kemajuan <x-info :teks="\App\Support\TindakLanjutRingkas::KETERANGAN" /></th></tr>
        </thead>
        <tbody>
          @foreach($tem->rekomendasi as $j => $rek)
            @php
              $lewat = $rek->telatTenggat();
              $lewatBatas = $rek->hariLewatPerbaikan();
              $satker = $rek->satkerTampil($peran, $u->satker_id);
            @endphp
            {{-- Menekan barisnya langsung membuka halaman rincian
                 rekomendasinya — sama seperti tabel Rekomendasi. Dulu barisnya
                 membuka rincian kecil di bawahnya; isinya mengulang sebagian
                 halaman rincian dengan lebih sedikit keterangan, dan yang
                 membacanya tetap harus membuka halamannya juga. --}}
            <tr class="bukaan{{ $rek->perluPerhatian() ? ' awas' : '' }}"
              data-href="{{ route('rekomendasi.show', ['rekomendasi' => $rek, 'dari' => 'laporan']) }}">
              <td class="num mono" style="font-size:12px">
                <span class="panahbaris"><x-ikon n="ChevronRight" :s="13" /></span>
                {{ $i + 1 }}.{{ $j + 1 }}
              </td>
              <td style="max-width:380px;font-size:13px">{{ $rek->uraian }}</td>
              <td class="mono" style="font-size:11.5px">
                {{ $rek->refLhp() }}
                <div class="lbl" style="margin-top:3px">{{ $rek->kode }}</div>
              </td>
              <td style="font-size:12.5px">{{ $satker->count() === 1 ? $satker->first()->namaPendek() : ($satker->count() ? $satker->count().' satuan kerja' : '—') }}</td>
              <td class="mono" style="font-size:12px;color:{{ $lewat ? 'var(--bad)' : 'inherit' }}">
                {{ Tampil::tgl($rek->tenggat_jawab) }}
                @if($lewat)<div class="lbl" style="color:var(--bad)">{{ Tampil::lamaTelat($rek->lewatTenggat()) }}</div>@endif
                @if($lewatBatas > 0)<div class="lbl" style="color:var(--bad)">lewat batas perbaikan {{ $lewatBatas }} hari</div>@endif
              </td>
              <td><x-kemajuan :rek="$rek" /></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
