@php
  use App\Enums\StatusTindakLanjut;
  use App\Support\Tampil;

  /* Bahan rujukan yang tidak dibaca tiap kali dibuka: arsip seluruh berkas
     rekomendasi ini, dan riwayat aktivitasnya. */
  $arsip = $r->lampiran;
  $jejak = $r->riwayat;
  $ikonPelaku = function (?string $a) {
      return match (true) {
          (bool) preg_match('/^balai/i', (string) $a) => 'Building2',
          (bool) preg_match('/^setba/i', (string) $a) => 'Users',
          (bool) preg_match('/^uki/i', (string) $a) => 'ClipboardCheck',
          (bool) preg_match('/inspektorat/i', (string) $a) => 'Stamp',
          (bool) preg_match('/bpk/i', (string) $a) => 'Landmark',
          default => 'Building2',
      };
  };
  $nadaAkhir = match ($r->status) {
      StatusTindakLanjut::SS => 'ok',
      StatusTindakLanjut::BS => 'bad',
      StatusTindakLanjut::TD => 'diam',
      default => 'jalan',
  };
@endphp

<div class="tab" role="tablist" data-tab-rincian>
  <button type="button" role="tab" aria-selected="true" data-tab="berkas">{{ 'Arsip rekomendasi' }}@if($arsip->count())<span class="n">{{ $arsip->count() }}</span>@endif</button>
  <button type="button" role="tab" aria-selected="false" data-tab="riwayat">{{ 'Riwayat aktivitas' }}@if($jejak->count())<span class="n">{{ $jejak->count() }}</span>@endif</button>
</div>

<div id="r-arsip" class="card" data-isi-tab="berkas">
  <div class="judulkartu">
    <span class="ic-kotak"><x-ikon n="Paperclip" :s="17" /></span>
    <h3>Arsip rekomendasi</h3>
    <x-info :teks="[
      'Tempat mengumpulkan seluruh berkas rekomendasi ini, baik yang diunggah satuan kerja maupun unit lain.',
      'Bukti setoran ikut tersimpan di sini, dan tetap tampil juga di tabel pemulihan nilai.',
      'Berkas yang sudah dikirim tidak bisa dihapus atau ditarik siapa pun, termasuk pengunggahnya.',
      'Berkas yang keliru diganti saat berkasnya dikirim ulang untuk pemberkasan ulang. Berkas lama tetap tersimpan sebagai jejak.',
      'Berkas di sini berupa tautan. Kalau tautannya ternyata memuat data pribadi, tutup aksesnya di tempat penyimpanannya.',
    ]" />
    <span class="n">{{ $arsip->count() }} berkas</span>
  </div>
  @forelse($arsip as $d)
    <div style="margin-bottom:12px">
      <x-berkas :b="$d" :penuh="true" />
      <div class="lbl" style="margin-top:4px;padding-left:2px">{{ $d->jenis() }} · {{ $d->label_oleh }} · {{ Tampil::tgl($d->diunggah_pada) }}</div>
    </div>
  @empty
    <div style="font-size:13px;color:var(--ink-3)">Belum ada berkas yang diunggah.</div>
  @endforelse
</div>

<div id="r-jejak" class="card" data-isi-tab="riwayat" hidden>
  <div class="judulkartu">
    <span class="ic-kotak abu"><x-ikon n="Clock" :s="17" /></span>
    <h3>Riwayat aktivitas</h3>
    <span class="n">dicatat sistem · {{ $jejak->count() }} kejadian</span>
  </div>
  <div class="arah">
    <span class="wkt"></span>
    <span class="tanda"><x-ikon n="ChevronDown" :s="14" /></span>
    <span class="teks">Awal</span>
  </div>
  <ol class="jejak">
    @foreach($jejak as $i => $rw)
      @php $akhir = $i === $jejak->count() - 1; @endphp
      <li class="{{ $akhir ? 'akhir '.$nadaAkhir : 'lalu' }}">
        <span class="wkt mono">{{ Tampil::tgl($rw->waktu) }}</span>
        <span class="bul"><x-ikon :n="$ikonPelaku($rw->label_aktor)" :s="12" /></span>
        <span class="isi">
          <span class="pelaku">{{ $rw->label_aktor }}</span>
          <b>{{ $rw->aksi }}</b>
          @if($akhir)<span class="kini">Terkini · {{ $r->posisiRek()->label() }}</span>@endif
        </span>
      </li>
    @endforeach
  </ol>
</div>
