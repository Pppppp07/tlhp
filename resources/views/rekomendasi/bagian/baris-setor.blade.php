@php $tunai = ($st['jenis'] ?? 'setor') !== 'perbaikan'; @endphp
{{-- Satu baris pemulihan. Nomor urutnya dan keadaan lengkapnya ditulis skrip. --}}
<div class="brs" data-setor>
  <div class="kepala">
    <span class="lbl" data-judul-setor>Pemulihan</span>
    <span class="lbl" style="color:var(--verm)" data-setor-belum hidden>belum lengkap</span>
    <span style="flex:1"></span>
    <button type="button" class="lbl" style="color:var(--verm)" data-hapus-setor>Hapus</button>
  </div>
  <div class="trio lebar1">
    <label class="fld">
      <span class="lbl">Cara pemulihan</span>
      <select name="setoran[{{ $n }}][jenis]" data-f="jenis">
        <option value="setor" @selected($tunai)>Setoran ke kas negara</option>
        <option value="perbaikan" @selected(! $tunai)>Perbaikan fisik atau pengembalian barang</option>
      </select>
    </label>
    <label class="fld">
      <span class="lbl">Nilai yang dipulihkan</span>
      <input type="text" class="mono" name="setoran[{{ $n }}][nilai]" value="{{ $st['nilai'] ?? '' }}" placeholder="0" data-f="nilai">
      <div class="hint" data-hint-nilai>&nbsp;</div>
    </label>
    <label class="fld">
      <span class="lbl" data-label-tanggal>{{ $tunai ? 'Tanggal setor' : 'Tanggal perbaikan selesai' }}</span>
      <input type="date" name="setoran[{{ $n }}][tanggal]" value="{{ $st['tanggal'] ?? '' }}" data-f="tanggal">
    </label>
  </div>
  <div class="trio" data-hanya-tunai @if(! $tunai) hidden @endif>
    <label class="fld">
      <span class="lbl">Nomor SSBP</span>
      <input type="text" class="mono" name="setoran[{{ $n }}][ssbp]" value="{{ $st['ssbp'] ?? '' }}" placeholder="SSBP/2026/00/00000" data-f="ssbp">
    </label>
    <label class="fld">
      <span class="lbl">NTPN</span>
      <input type="text" class="mono" name="setoran[{{ $n }}][ntpn]" value="{{ $st['ntpn'] ?? '' }}" placeholder="16 karakter" maxlength="16" data-f="ntpn">
      <div class="hint" data-hint-ntpn>16 karakter</div>
    </label>
    <label class="fld">
      <span class="lbl">Nota Konfirmasi KPPN</span>
      <input type="text" class="mono" name="setoran[{{ $n }}][notaKppn]" value="{{ $st['notaKppn'] ?? '' }}" placeholder="NK-000/KPPN-000/2026" data-f="notaKppn">
    </label>
  </div>
  <label class="fld" style="max-width:300px" data-hanya-perbaikan @if($tunai) hidden @endif>
    <span class="lbl">Nomor berita acara</span>
    <input type="text" class="mono" name="setoran[{{ $n }}][noBa]" value="{{ $st['noBa'] ?? '' }}" placeholder="BA-000/PPK/2026" data-f="noBa">
  </label>
  <div class="fld" style="margin-bottom:0">
    <span class="lbl" data-label-bukti>{{ $tunai ? 'Bukti setor (SSBP)' : 'Berita acara perbaikan' }}</span>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap" data-isi-bukti-setor @if(empty($st['berkas']) && empty($st['tautan'])) hidden @endif>
      <span style="flex:1;min-width:0">
        <span class="duo">
          <label class="fld"><span class="lbl">Judul berkas</span>
            <input type="text" name="setoran[{{ $n }}][berkas]" value="{{ trim($st['berkas'] ?? '') }}" placeholder="{{ $tunai ? 'Bukti setor kas negara (SSBP)' : 'Berita acara perbaikan' }}" data-f="berkas"></label>
          <label class="fld"><span class="lbl">Tautan berkas</span>
            <input type="text" class="mono" name="setoran[{{ $n }}][tautan]" value="{{ $st['tautan'] ?? '' }}" placeholder="https://…" data-f="tautan"></label>
        </span>
      </span>
      <button type="button" class="btn btn-s" style="margin-top:22px" data-hapus-bukti-setor><x-ikon n="X" :s="12" /> Hapus</button>
    </div>
    <button type="button" class="btn btn-s" data-tambah-bukti-setor @if(! empty($st['berkas']) || ! empty($st['tautan'])) hidden @endif>
      <x-ikon n="ExternalLink" :s="13" /> Tambah tautan bukti
    </button>
  </div>
</div>
