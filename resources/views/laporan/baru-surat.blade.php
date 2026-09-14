@php use App\Enums\SumberLaporan; @endphp

<form method="post" action="{{ route('laporan.baru.surat') }}">
  @csrf
  <div class="kartu" style="margin-bottom:16px">
    <div class="judulkartu">
      <span class="ic-kotak"><x-ik nama="berkas-teks" ukuran="17" /></span>
      <h3>Data surat</h3>
    </div>

    <div class="duo">
      <label class="f">
        <span class="lbl">Sumber laporan <x-info :teks="[
          'Menentukan tenggat jawaban, panjang tahapan, dan daftar kategori temuan yang bisa dipilih.',
          'LHP dari BPK: 60 hari kalender, dasar UU 15/2004 Pasal 20, dan penutupannya lewat SIPTL.',
          'LHA dari Inspektorat: 30 hari kerja, seluruh tahapnya selesai di dalam instansi.',
        ]" /></span>
        <select name="sumber">
          @foreach(SumberLaporan::cases() as $j)
            <option value="{{ $j->value }}" @selected($s['sumber'] === $j->value)>
              {{ $j->value }} &mdash; {{ $j->nama() }}
            </option>
          @endforeach
        </select>
        <span class="hint">
          Tenggat {{ SumberLaporan::from($s['sumber'])->hariTenggat() }}
          {{ SumberLaporan::from($s['sumber'])->pakaiHariKerja() ? 'hari kerja' : 'hari kalender' }}
          &middot; {{ SumberLaporan::from($s['sumber'])->dasarHukum() }}
        </span>
      </label>

      <label class="f">
        <span class="lbl">Nomor surat</span>
        <input type="text" class="mono" name="nomor" value="{{ old('nomor', $s['nomor']) }}"
          placeholder="000/LHP/XVIII/00/{{ now()->year }}" required>
      </label>

      <label class="f">
        <span class="lbl">Tanggal surat</span>
        <input type="date" name="tgl_surat" value="{{ old('tgl_surat', $s['tgl_surat']) }}"
          max="{{ now()->toDateString() }}" required>
      </label>

      <label class="f">
        <span class="lbl">Tanggal surat diterima Setba <x-info :teks="[
          'Diambil dari cap terima suratnya, bukan dari hari pencatatannya di sistem.',
          'Dari tanggal inilah tenggat jawaban dihitung — jadi surat yang lama menganggur sebelum dicatat sudah memakan tenggatnya sendiri.',
        ]" /></span>
        {{-- `min` mengikuti tanggal suratnya: surat tidak mungkin diterima
             sebelum suratnya dibuat. `max`/`min` cuma anjuran peramban —
             penjaga sesungguhnya `validate()` di pengendali. --}}
        <input type="date" name="tgl_terima" value="{{ old('tgl_terima', $s['tgl_terima']) }}"
          @if($s['tgl_surat']) min="{{ $s['tgl_surat'] }}" @endif
          max="{{ now()->toDateString() }}" required>
        @error('tgl_terima')<span class="hint" style="color:var(--verm)">{{ $message }}</span>@enderror
      </label>
    </div>

    {{-- Pindaian suratnya bagian dari data surat itu sendiri, bukan urusan
         terpisah — memberinya judul sendiri membuat langkah pertama terlihat
         lebih panjang daripada isinya. --}}
    <div class="f" style="margin-bottom:0">
      <span class="lbl">Pindaian surat &mdash; tidak wajib <x-info :teks="[
        'Boleh dilengkapi belakangan. Suratnya kerap sampai lebih dulu daripada pindaiannya.',
        'Menahan pencatatan sampai pindaiannya ada justru merugikan: tenggat jawabannya sudah berjalan sementara belum ada satu pun satuan kerja yang bisa mengerjakannya.',
        'Selama belum ada, tanggal diterima tidak bisa dicocokkan dengan cap terima suratnya — dan itu ditandai di halaman laporannya.',
      ]" /></span>
      @if($s['berkas'])
        <div style="display:flex;align-items:center;gap:9px">
          <span class="berkas"><x-ik nama="klip" ukuran="13" /> surat-laporan.pdf</span>
          <button class="btn btn-s" type="submit" name="berkas" value="0">Ganti</button>
        </div>
      @else
        <button class="btn" type="submit" name="berkas" value="1">
          <x-ik nama="klip" ukuran="14" /> Pilih berkas
        </button>
        <span class="hint">Boleh menyusul. Selama belum ada, laporannya ditandai belum bertautan.</span>
      @endif
    </div>
  </div>

  <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
    <button class="btn btn-p" type="submit" name="ke" value="2" @disabled(! $lengkap1)>
      Berikutnya: Temuan &amp; rekomendasi <x-ik nama="panah-kanan" ukuran="15" />
    </button>
    <span class="hint">
      {{ $lengkap1 ? 'Sudah lengkap' : 'Nomor surat dan kedua tanggalnya wajib diisi.' }}
    </span>
  </div>
</form>
