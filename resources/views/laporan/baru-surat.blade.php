@php
  use App\Enums\SumberLaporan;
  use App\Support\Tampil;
  use App\Models\Rekomendasi;

  /* Langkah 1 — data surat. Satu-satunya bagian di langkah ini, jadi ia
     terbuka sejak awal: layar pertama yang tidak punya satu isian pun yang
     terlihat cuma menambah satu tekanan sebelum mulai. */
  $s = $d['surat'];
  $sumber = SumberLaporan::from($s['sumber']);
  $salah = $form->salahTanggal($s);
  $ini = now()->toDateString();
  $renaksi = $s['tgl_terima']
    ? Rekomendasi::hitungTenggat(\Illuminate\Support\Carbon::parse($s['tgl_terima']), $sumber)
    : null;
@endphp

<div class="card">
  <div class="grup buka" data-grup>
    <button class="judul" type="button" aria-expanded="true" data-buka-grup>
      <span class="panah buka">›</span>
      <span class="nm">Data surat</span>
      <span class="tanda" style="color:{{ $ok1 ? 'var(--ok)' : 'var(--ink-3)' }};font-weight:600">
        {{ $ok1 ? 'lengkap' : 'belum lengkap' }}
      </span>
    </button>

    <div class="isi">
      <div class="duo">
        <label class="fld">
          <span class="lbl">Sumber laporan</span>
          {{-- Mengganti sumber mengganti daftar kategori temuan dan dasar
               hitungan tenggatnya, jadi halamannya langsung disegarkan. --}}
          <select name="surat[sumber]" data-kirim>
            <option value="LHP" @selected($s['sumber'] === 'LHP')>LHP — Laporan Hasil Pemeriksaan</option>
            <option value="LHA" @selected($s['sumber'] === 'LHA')>LHA — Laporan Hasil Audit</option>
          </select>
          <div class="hint">
            Tenggat {{ $sumber->hariTenggat() }} {{ $sumber->satuanTenggat() }} · {{ $sumber->dasarHukum() }}
          </div>
        </label>
        <label class="fld">
          <span class="lbl">Nomor surat</span>
          <input type="text" class="mono" name="surat[nomor]" value="{{ $s['nomor'] }}"
            placeholder="000/LHP/XVIII/00/2026">
        </label>
        <label class="fld">
          <span class="lbl">Tanggal surat</span>
          {{-- `max` supaya peramban ikut menolak tanggal di depan. Bukan
               penjaga sesungguhnya — itu `salahTanggal` di peladen. --}}
          <input type="date" name="surat[tgl_surat]" value="{{ $s['tgl_surat'] }}" max="{{ $ini }}">
          <div class="hint">Tanggal yang tercetak di suratnya.</div>
        </label>
        <label class="fld">
          <span class="lbl">Tanggal surat diterima <x-info :teks="[
            'Diambil dari cap terima suratnya, bukan tanggal Anda mengisi formulir ini.',
            'Dari tanggal inilah tenggat jawaban dihitung — jadi salah satu hari saja menggeser tenggat seluruh rekomendasi laporan ini.',
            'Tanggal Anda mencatatnya di sistem disimpan terpisah, diisi sendiri oleh sistem. Jaraknya dari tanggal ini ikut ditampilkan.',
          ]" /></span>
          <input type="date" name="surat[tgl_terima]" value="{{ $s['tgl_terima'] }}"
            min="{{ $s['tgl_surat'] ?: '' }}" max="{{ $ini }}" data-kirim>
          <div class="hint" @if($salah) style="color:var(--bad)" @endif>
            {{ $salah ?: ($renaksi ? 'Rencana aksi: '.Tampil::tgl($renaksi) : ' ') }}
          </div>
        </label>
      </div>

      {{-- Pindaian suratnya bagian dari data surat itu sendiri, bukan urusan
           terpisah. Surat aslinya tebal — LHP bisa ratusan halaman — jadi yang
           disimpan tautannya, bukan berkasnya. --}}
      <div class="fld" style="margin-bottom:0">
        <span class="lbl">Tautan surat asli — tidak wajib <x-info :teks="[
          'Boleh dilengkapi belakangan. Suratnya kerap sampai lebih dulu daripada salinan arsipnya.',
          'Menahan pencatatan sampai tautannya ada justru merugikan: tenggat jawabannya sudah berjalan sementara belum ada satu pun satuan kerja yang bisa mengerjakannya.',
          'Selama belum ada, tanggal diterima tidak bisa dicocokkan dengan cap terima suratnya — dan itu ditandai di halaman laporannya.',
        ]" /></span>
        <div class="duo">
          <label class="fld">
            <span class="lbl">Judul berkas</span>
            <input type="text" name="berkas[judul]" value="{{ $d['berkas']['judul'] }}"
              placeholder="Laporan Hasil Pemeriksaan beserta lampirannya">
          </label>
          <label class="fld">
            <span class="lbl">Tautan berkas</span>
            <input type="text" class="mono" name="berkas[tautan]" value="{{ $d['berkas']['tautan'] }}"
              placeholder="https://…">
          </label>
        </div>
      </div>
    </div>
  </div>
</div>
