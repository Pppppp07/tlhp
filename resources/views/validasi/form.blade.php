@extends('rangka')
@section('judul', 'Terbitkan LHV')
@section('isi')
@php use App\Support\Tampil; @endphp

<div class="kartu" style="margin-bottom:16px">
  <div class="lbl" style="margin-bottom:4px">Laporan Hasil Validasi</div>
  <p style="margin:0;font-size:12.5px;color:var(--ink-3);max-width:74ch;line-height:1.6">
    Satu LHV bernomor memuat hasil validasi untuk beberapa berkas sekaligus &mdash; begitulah
    bentuk kertasnya, dan nomornya yang dipakai menelusuri berkas di luar sistem.
    Yang divalidasi adalah <b>berkas tiap satuan kerja</b>, bukan rekomendasinya seutuhnya:
    satu rekomendasi yang dipikul tiga satker menghasilkan tiga baris di sini, dan
    ketiganya bisa berbeda hasilnya.
  </p>
</div>

@if(session('pesan'))<div class="pesan ok">{{ session('pesan') }}</div>@endif
@if($errors->has('putusan'))<div class="pesan info">{{ $errors->first('putusan') }}</div>@endif

@if($antre->isEmpty())
  <div class="pesan info">Tidak ada berkas yang menunggu telaah UKI.</div>
@else
<form method="post" action="{{ route('validasi.simpan') }}">
  @csrf

  <div class="kartu" style="margin-bottom:16px">
    <div class="lbl" style="margin-bottom:10px">Data surat</div>
    <div class="duo">
      <label class="f">
        <span class="lbl">Nomor surat</span>
        <input type="text" name="nomor_surat" value="{{ old('nomor_surat') }}"
          placeholder="41/VAL/UKI-BPSDM/IX/2026" required>
        @error('nomor_surat')<span class="hint" style="color:var(--verm)">{{ $message }}</span>@enderror
      </label>
      <label class="f">
        <span class="lbl">Tanggal surat</span>
        <input type="date" name="tgl_surat" value="{{ old('tgl_surat', now()->toDateString()) }}" required>
        @error('tgl_surat')<span class="hint" style="color:var(--verm)">{{ $message }}</span>@enderror
      </label>
      <label class="f">
        <span class="lbl">Periode</span>
        <input type="text" name="periode" value="{{ old('periode') }}"
          placeholder="Triwulan III 2026" required>
      </label>
      <label class="f">
        <span class="lbl">Pejabat penanda tangan</span>
        <input type="text" name="pejabat" value="{{ old('pejabat') }}"
          placeholder="Kepala Unit Kepatuhan Internal" required>
      </label>
    </div>
  </div>

  <div class="judulbagian">
    <b>Berkas yang divalidasi</b>
    <span class="n">{{ $antre->count() }}</span>
    <span class="garis"></span>
  </div>
  <p style="margin:0 0 14px;font-size:12.5px;color:var(--ink-3)">
    Isi hanya yang memang disebut dalam surat. Yang dibiarkan kosong tetap menunggu surat berikutnya.
  </p>

  @foreach($antre as $s)
    @php
      $rek = $s->tindakan->rekomendasi;
      $sumber = $rek->temuan->laporan->sumber;
      $dok = $s->progresDokumen();
      $old = old('putusan.' . $s->id, []);
    @endphp
    <div class="kartu" style="margin-bottom:12px">
      <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:7px">
        <span class="sumber sumber-{{ $sumber->value }}">{{ $sumber->value }}</span>
        <a class="mono" style="font-size:11.5px" href="{{ route('rekomendasi.show', $rek) }}">{{ $rek->kode }}</a>
        <div style="flex:1"></div>
        <span class="keping"><b>{{ $s->satker?->namaPendek() }}</b></span>
      </div>
      <div style="font-size:13px;margin-bottom:10px">{{ $rek->uraian }}</div>

      <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:12px">
        @if($dok)
          <div class="prog"><span style="min-width:54px">Dokumen</span>
            <span class="bar"><i style="width:{{ $dok[1] ? round($dok[0] / $dok[1] * 100) : 0 }}%"></i></span>
            <b>{{ $dok[0] }} dari {{ $dok[1] }}</b></div>
        @endif
        @if($s->nilai)
          <div class="prog"><span style="min-width:54px">Dana</span>
            <span class="bar dana"><i style="width:{{ min(100, round($s->nilaiTerpulihkan() / max(1, $s->nilai) * 100)) }}%"></i></span>
            <b>{{ Tampil::rupiah($s->nilaiTerpulihkan()) }}</b>
            <span>dari {{ Tampil::rupiah($s->nilai) }}</span></div>
        @endif
      </div>

      <div class="duo">
        <label class="f">
          <span class="lbl">Hasil validasi</span>
          <select name="putusan[{{ $s->id }}][hasil]">
            <option value="">&mdash; tidak disebut dalam surat ini &mdash;</option>
            <option value="M" @selected(($old['hasil'] ?? '') === 'M')>
              {{ \App\Enums\HasilTelaah::M->nama($sumber) }} &mdash; diteruskan ke Setba</option>
            <option value="BM" @selected(($old['hasil'] ?? '') === 'BM')>
              {{ \App\Enums\HasilTelaah::BM->nama($sumber) }} &mdash; kembali ke satuan kerja</option>
          </select>
        </label>
        <label class="f">
          <span class="lbl">Kesimpulan &mdash; wajib bila hasilnya diisi</span>
          <textarea name="putusan[{{ $s->id }}][catatan]" style="min-height:44px"
            placeholder="Contoh: bukti setor dan Nota Konfirmasi KPPN sudah lengkap dan cocok dengan nilai bagiannya.">{{ $old['catatan'] ?? '' }}</textarea>
          @error("putusan.{$s->id}.catatan")
            <span class="hint" style="color:var(--verm)">{{ $message }}</span>
          @enderror
        </label>
      </div>
    </div>
  @endforeach

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px">
    <button class="btn btn-p" type="submit">Terbitkan LHV</button>
    <a class="btn" href="{{ route('rekomendasi.index') }}">Batal</a>
  </div>
  <div class="hint" style="margin-top:8px">
    LHV menandai berkas tiap satuan kerja memadai atau belum. Ia <b>tidak</b> mengubah status
    BPK &mdash; status itu hanya berubah lewat SIPTL, dan putusan atas seluruh rekomendasi hanya
    lahir dari surat CHV Inspektorat.
  </div>
</form>
@endif

@if($riwayat->isNotEmpty())
<div class="judulbagian" style="margin-top:26px">
  <b>LHV yang sudah terbit</b>
  <span class="n">{{ $riwayat->count() }}</span>
  <span class="garis"></span>
</div>
<div class="tw">
  <table>
    <thead><tr><th>Nomor surat</th><th>Tanggal</th><th>Periode</th><th>Pejabat</th></tr></thead>
    <tbody>
    @foreach($riwayat as $v)
      <tr>
        <td class="mono" style="font-size:11.5px">{{ $v->nomor_surat }}</td>
        <td class="mono" style="font-size:12px">{{ Tampil::tgl($v->tgl_surat) }}</td>
        <td style="font-size:12.5px">{{ $v->periode }}</td>
        <td style="font-size:12.5px">{{ $v->pejabat }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif
@endsection
