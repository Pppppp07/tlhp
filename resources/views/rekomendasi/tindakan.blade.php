@php
  use App\Enums\PeranPengguna;
  use App\Enums\PosisiBerkas;
  use App\Support\Tampil;

  $u = auth()->user();
  $peran = $u->peran;
  $setba = $peran === PeranPengguna::SETBA;
  $sumber = $r->temuan->laporan->sumber;

  /* Semua tindakan tingkat 1 bekerja pada BARIS, bukan pada rekomendasi.
     Rekomendasi yang dipikul tiga satuan kerja berada di tiga tahap sekaligus;
     satu tombol untuk semuanya akan memindahkan berkas dua satker yang belum
     selesai bekerja. */
  $semuaBaris = $r->daftarSasaran();

  $barisku = $semuaBaris->filter(function ($s) use ($peran, $u) {
      if ($peran === PeranPengguna::SATKER) {
          return $s->satker_id === $u->satker_id && $s->posisi === PosisiBerkas::SATKER;
      }
      return $s->posisi?->pemegang() === $peran;
  })->values();

  /* Berkas yang rekomendasinya sudah diputus lewat CHV terkunci sama sekali —
     isian itu sudah jadi dasar surat resmi bernomor. */
  $terkunci = $r->terkunciOlehSurat();

  $bolehSiptl = $setba && $r->posisi === PosisiBerkas::SIPTL;
  $bolehBpk   = $setba && $r->posisi === PosisiBerkas::BPK;

  $adaTindakan = ($barisku->isNotEmpty() && ! $terkunci) || $bolehSiptl || $bolehBpk;
@endphp

{{-- Pesan terkunci turun ke dalam barisnya masing-masing: yang terkunci
     berkas satuan kerjanya, dan pembacanya perlu melihatnya di baris itu. --}}

{{-- ================================================================
     TINGKAT 2 — rekomendasinya sendiri
     ================================================================ --}}
@if($bolehSiptl)
<div class="kartu" style="margin-bottom:16px;border-color:var(--brass)">
  <div class="lbl" style="margin-bottom:4px">Perlu diunggah ke SIPTL</div>
  <p style="margin:0 0 14px;font-size:12.5px;color:var(--ink-3)">
    Jalur LHP belum berhenti di surat Inspektorat. Berkasnya masih harus disampaikan ke BPK
    lewat SIPTL, dan BPK yang memberi penilaian akhir. Mencatat unggahan tidak mengubah status.
  </p>
  <form method="post" action="{{ route('rekomendasi.siptl', $r) }}">
    @csrf
    <div class="duo">
      <label class="f">
        <span class="lbl">Tanggal unggah</span>
        <input type="date" name="siptl_tanggal" value="{{ old('siptl_tanggal', now()->toDateString()) }}" required>
      </label>
      <label class="f">
        <span class="lbl">Nomor tanda terima SIPTL</span>
        <input type="text" name="siptl_tanda_terima" value="{{ old('siptl_tanda_terima') }}"
               placeholder="mis. TT-SIPTL/2026/00318" required>
      </label>
    </div>
    <button class="btn btn-p" type="submit">Catat unggahan SIPTL</button>
  </form>
</div>
@endif

@if($bolehBpk)
<div class="kartu" style="margin-bottom:16px;border-color:var(--stamp)">
  <div class="lbl" style="margin-bottom:4px">Status dari SIPTL</div>
  <p style="margin:0 0 14px;font-size:12.5px;color:var(--ink-3)">
    Sudah di SIPTL sejak {{ Tampil::tgl($r->siptl_tanggal) }}@if($r->siptl_tanda_terima),
    tanda terima <span class="mono">{{ $r->siptl_tanda_terima }}</span>@endif.
    Statusnya tidak keluar sendiri &mdash; Setba membukanya di SIPTL, lalu menyalinnya apa adanya
    ke sini.
  </p>
  <form method="post" action="{{ route('rekomendasi.bpk', $r) }}">
    @csrf
    <div class="trio">
      <label class="f">
        <span class="lbl">Status yang tertera di SIPTL</span>
        <select name="hasil" required>
          <option value="SS">SS &middot; Sesuai dengan rekomendasi &mdash; selesai</option>
          <option value="BS">BS &middot; Belum sesuai &mdash; kembali ke satuan kerja</option>
          <option value="TD">TD &middot; Tidak dapat ditindaklanjuti &mdash; ditutup</option>
        </select>
      </label>
      <label class="f">
        <span class="lbl">Tanggal status keluar</span>
        <input type="date" name="tanggal" value="{{ old('tanggal') }}">
        <div class="hint">beda dari tanggal Anda mencatatnya</div>
      </label>
      <label class="f">
        <span class="lbl">Catatan dari SIPTL</span>
        <input type="text" name="catatan" value="{{ old('catatan') }}">
      </label>
    </div>
    <button class="btn btn-p" type="submit">Catat status SIPTL</button>
  </form>
</div>
@endif

@unless($adaTindakan)
<div class="pesan info">
  Belum ada yang perlu Anda kerjakan pada berkas ini. Keadaannya sekarang:
  <b>{{ strtolower($r->posisiTampil()?->label() ?? 'belum ditugaskan ke satuan kerja') }}</b>@if($r->posisiTampil()?->pemegang()) &mdash; bola ada di tangan {{ $r->posisiTampil()->pemegang()->nama() }}@endif.
  @if($semuaBaris->count() > 1)
    <div class="lbl" style="margin-top:8px">
      @foreach($r->sebaranPosisi() as $nama => $n)
        <span class="keping">{{ $nama }} {{ $n }}</span>
      @endforeach
    </div>
  @endif
</div>
@endunless
