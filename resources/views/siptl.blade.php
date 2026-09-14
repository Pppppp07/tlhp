@extends('rangka')
@section('judul', 'Urusan SIPTL')
@section('isi')
@php use App\Support\Tampil; @endphp

<div class="kartu" style="margin-bottom:16px">
  <div class="lbl" style="margin-bottom:4px">Penyampaian ke BPK lewat SIPTL</div>
  <p style="margin:0;font-size:12.5px;color:var(--ink-3);max-width:74ch;line-height:1.6">
    Dua pekerjaan yang selalu berurutan: mengunggah berkasnya, lalu beberapa waktu kemudian
    membuka SIPTL lagi untuk membaca statusnya. Keduanya dikumpulkan di sini karena
    pekerjaannya memang borongan &mdash; satu kali membuka SIPTL, banyak berkas sekaligus.
    Jalur LHA tidak pernah sampai ke halaman ini.
  </p>
</div>

@if(session('pesan'))<div class="pesan ok">{{ session('pesan') }}</div>@endif
@if(session('gagal'))<div class="pesan info">{{ session('gagal') }}</div>@endif

{{-- ============ 1. perlu diunggah ============ --}}
<div class="judulbagian">
  <b>Perlu diunggah ke SIPTL</b>
  <span class="n">{{ $siapUnggah->count() }}</span>
  <span class="garis"></span>
</div>

@forelse($siapUnggah as $r)
  <div class="kartu" style="margin-bottom:10px" id="r-{{ $r->id }}">
    <div style="display:flex;gap:9px;align-items:baseline;flex-wrap:wrap;margin-bottom:6px">
      <span class="sumber sumber-{{ $r->temuan->laporan->sumber->value }}">{{ $r->temuan->laporan->sumber->value }}</span>
      <a class="mono" style="font-size:11.5px" href="{{ route('rekomendasi.show', $r) }}">{{ $r->kode }}</a>
      <div style="flex:1"></div>
      <span class="lbl">{{ $r->temuan->laporan->nomor }}</span>
    </div>
    <div style="font-size:13px;margin-bottom:8px">{{ $r->uraian }}</div>

    <div style="margin-bottom:10px">
      @foreach($r->daftarSasaran() as $x)
        <span class="keping">{{ $x->satker?->namaPendek() }}</span>
      @endforeach
    </div>

    @php $chv = $r->keputusan->last(); @endphp
    @if($chv)
      <div class="lbl" style="margin-bottom:10px">
        Surat CHV {{ $chv->verifikasi->nomor_surat }} &middot;
        {{ Tampil::tgl($chv->verifikasi->tgl_surat) }} &middot;
        {{ $chv->hasil->nama($r->temuan->laporan->sumber) }}
      </div>
    @endif

    <form method="post" action="{{ route('rekomendasi.siptl', $r) }}">
      @csrf
      <div class="trio">
        <label class="f">
          <span class="lbl">Tanggal unggah</span>
          <input type="date" name="siptl_tanggal" value="{{ now()->toDateString() }}" required>
        </label>
        <label class="f">
          <span class="lbl">Nomor tanda terima SIPTL</span>
          <input type="text" name="siptl_tanda_terima" placeholder="TT-SIPTL/2026/00000" required>
        </label>
        <label class="f">
          <span class="lbl">&nbsp;</span>
          <button class="btn btn-p" type="submit">Catat unggahan</button>
        </label>
      </div>
    </form>
  </div>
@empty
  <div class="pesan info" style="margin-bottom:18px">
    Tidak ada yang menunggu diunggah.
    @if($belumDisurati->isNotEmpty())
      {{ $belumDisurati->count() }} rekomendasi sudah tuntas seluruh satuan kerjanya tapi
      belum diputus lewat surat CHV &mdash; itu yang menghalangi berkas berikutnya sampai ke sini.
      Suratnya diterbitkan Inspektorat, bukan Setba.
    @endif
  </div>
@endforelse

{{-- ============ 2. menunggu status keluar ============ --}}
<div class="judulbagian" style="margin-top:24px">
  <b>Sudah diunggah, menunggu status dari BPK</b>
  <span class="n">{{ $menunggu->count() }}</span>
  <span class="garis"></span>
</div>

@forelse($menunggu as $r)
  @php $diam = $r->siptl_tanggal ? (int) $r->siptl_tanggal->diffInDays(now()) : null; @endphp
  <div class="kartu" style="margin-bottom:10px" id="r-{{ $r->id }}">
    <div style="display:flex;gap:9px;align-items:baseline;flex-wrap:wrap;margin-bottom:6px">
      <span class="sumber sumber-{{ $r->temuan->laporan->sumber->value }}">{{ $r->temuan->laporan->sumber->value }}</span>
      <a class="mono" style="font-size:11.5px" href="{{ route('rekomendasi.show', $r) }}">{{ $r->kode }}</a>
      <div style="flex:1"></div>
      {{-- Berapa lama menggantung. Bukan tenggat — BPK tidak punya tenggat
           membalas — tapi yang menggantung berbulan-bulan biasanya perlu
           ditanyakan, bukan ditunggu terus. --}}
      @if($diam !== null)
        <span class="pil {{ $diam > 90 ? 'kuning' : '' }}">{{ $diam }} hari di SIPTL</span>
      @endif
    </div>
    <div style="font-size:13px;margin-bottom:8px">{{ $r->uraian }}</div>
    <div class="lbl" style="margin-bottom:10px">
      Diunggah {{ Tampil::tgl($r->siptl_tanggal) }}
      @if($r->siptl_tanda_terima) &middot; tanda terima <span class="mono">{{ $r->siptl_tanda_terima }}</span>@endif
    </div>

    <form method="post" action="{{ route('rekomendasi.bpk', $r) }}">
      @csrf
      <div class="trio">
        <label class="f">
          <span class="lbl">Status yang tertera di SIPTL</span>
          <select name="hasil" required>
            <option value="">— belum keluar —</option>
            @foreach($status as $s)
              @continue($s === \App\Enums\StatusTindakLanjut::BT)
              <option value="{{ $s->value }}">{{ $s->value }} &middot; {{ $s->nama() }}</option>
            @endforeach
          </select>
        </label>
        <label class="f">
          <span class="lbl">Tanggal status keluar</span>
          <input type="date" name="tanggal">
          <span class="hint">beda dari tanggal Anda mencatatnya</span>
        </label>
        <label class="f">
          <span class="lbl">Catatan dari SIPTL</span>
          <input type="text" name="catatan">
        </label>
      </div>
      <button class="btn btn-p" type="submit">Catat status</button>
      <div class="hint" style="margin-top:8px">
        Sistem tidak menyimpulkan status ini sendiri. BPK yang menilai, Setba yang membacanya,
        dan yang tersimpan di sini hanyalah salinannya.
      </div>
    </form>
  </div>
@empty
  <div class="pesan info" style="margin-bottom:18px">Tidak ada yang sedang menunggu penilaian BPK.</div>
@endforelse

{{-- ============ 3. yang sudah dicatat ============ --}}
@if($sudah->isNotEmpty())
<div class="judulbagian" style="margin-top:24px">
  <b>Status yang sudah dicatat</b>
  <span class="n">{{ $sudah->count() }}</span>
  <span class="garis"></span>
</div>

<div class="tw">
  <table>
    <thead><tr>
      <th>Rekomendasi</th><th>Satuan kerja</th>
      <th>Diunggah</th><th>Status SIPTL</th><th>Dicatat</th><th>Catatan</th>
    </tr></thead>
    <tbody>
    @foreach($sudah as $r)
      <tr>
        <td>
          <a class="mono" style="font-size:11.5px" href="{{ route('rekomendasi.show', $r) }}">{{ $r->kode }}</a>
          <div class="lbl" style="margin-top:3px;max-width:280px">{{ $r->uraian }}</div>
        </td>
        <td style="font-size:12.5px">
          @foreach($r->daftarSasaran() as $x)
            <span class="keping">{{ $x->satker?->namaPendek() }}</span>
          @endforeach
        </td>
        <td class="mono" style="font-size:12px">{{ Tampil::tgl($r->siptl_tanggal) }}</td>
        <td><span class="cap cap-{{ $r->siptl_status->value }}">{{ $r->siptl_status->value }}</span></td>
        <td class="mono" style="font-size:12px">{{ Tampil::tgl($r->siptl_dicatat_pada) }}</td>
        <td style="font-size:12.5px;max-width:260px">{{ $r->siptl_catatan ?: '—' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif
@endsection
