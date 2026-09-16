@php
  use App\Enums\HasilTelaah;
  use App\Enums\PeranPengguna;
  use App\Enums\PosisiBerkas;

  /* Putusan UKI atau Inspektorat atas satu baris — padanan PanelPeriksa.
     Daftarnya berisi satu satuan kerja saja: yang barisnya sedang dibuka. */
  $uki = $x->pos() === PosisiBerkas::UKI;
  $atasNama = ! $uki && $peran === PeranPengguna::SETBA;
  $kataM = HasilTelaah::M->nama($jenis);
  $kataBM = HasilTelaah::BM->nama($jenis);
  $sebutSurat = $uki ? 'surat hasil validasi' : 'CHV';
  /* Satuan kerja LAIN yang belum memadai: surat CHV-nya akan berbunyi belum
     memadai walau baris ini ditandai memadai — satu CHV memutus seluruh
     rekomendasi. */
  $lainBelum = $uki ? collect() : $r->semuaBaris()
    ->filter(fn ($y) => $y->satker_id !== $x->satker_id && $y->hasil !== HasilTelaah::M)
    ->map->satker->unique('id')->values();
@endphp

<form method="post" action="{{ route('sasaran.putus', $x) }}" id="r-periksa" data-panel-periksa
  data-uki="{{ $uki ? 1 : 0 }}" data-kata-m="{{ $kataM }}" data-kata-bm="{{ $kataBM }}"
  data-lain-belum="{{ $lainBelum->count() }}" data-hari="{{ now()->toDateString() }}">
  @csrf
  <input type="hidden" name="hasil" value="" data-mode>
  <div class="tindakan dibaris">
    <div class="judul">
      <x-ikon n="ClipboardCheck" :s="16" />
      {{ $uki ? 'Hasil validasi UKI' : 'Hasil verifikasi Inspektorat' }}
      @if($atasNama)
        <x-info :teks="[
          'Meja Inspektorat boleh dikerjakan Inspektorat sendiri, dan boleh juga oleh Setba.',
          'Kalau Setba yang mengerjakan, yang dicatat adalah isi surat CHV yang dikirim Inspektorat.',
          'Riwayatnya menyebut Setba sebagai pencatat, dengan Inspektorat sebagai yang berwenang.',
        ]" />
      @endif
    </div>

    <div data-mode-awal>
      <div class="hint" style="margin-bottom:10px">
        {{ $atasNama
          ? 'Anda mengerjakan meja Inspektorat atas nama mereka. Salin apa yang tertulis di surat CHV — riwayat akan mencatat Setba sebagai pencatatnya.'
          : 'Putusannya ditentukan '.($uki ? 'UKI' : 'Inspektorat').'. Aplikasi tidak menghitungnya sendiri dari kelengkapan berkas.' }}
      </div>
      <div style="display:flex;gap:9px;flex-wrap:wrap">
        <button type="button" class="btn btn-ok" data-pilih-mode="M"><x-ikon n="Check" :s="14" /> {{ $kataM }}</button>
        <button type="button" class="btn btn-bad" data-pilih-mode="BM"><x-ikon n="RotateCcw" :s="14" /> {{ $kataBM }}</button>
      </div>
    </div>

    <div data-mode-isi hidden>
      <div class="hint" style="margin-bottom:12px" data-hint-m>
        {{ $uki ? 'Berkas kembali ke Setba untuk diteruskan ke Inspektorat.'
          : ($lainBelum->isNotEmpty() ? 'Tindak lanjut satuan kerja ini ditandai memadai dan berkasnya berhenti di sini.' : 'Rekomendasi dinyatakan selesai diverifikasi.') }}
      </div>
      <div class="hint" style="margin-bottom:12px" data-hint-bm hidden>
        Berkas kembali ke Setba, lalu Setba mengirimkannya ulang ke satuan kerja untuk pemberkasan ulang.
      </div>

      @if(! $uki && $lainBelum->isNotEmpty())
        <div class="hint" style="margin-bottom:12px;color:var(--jingga)" data-hanya-m>
          Surat CHV-nya tercatat <b>{{ mb_strtolower($kataBM) }}</b>. Tanda untuk satuan kerja ini tetap {{ mb_strtolower($kataM) }},
          tapi satu CHV memutus seluruh rekomendasi — dan {{ $lainBelum->count() }} satuan kerja lain belum:
          {{ $lainBelum->take(3)->map->namaPendek()->join(', ') }}{{ $lainBelum->count() > 3 ? ' dan '.($lainBelum->count() - 3).' lainnya' : '' }}.
          <x-info :teks="[
            'Tanda per satuan kerja dan putusan surat itu dua hal yang berbeda.',
            'Tandanya menggerakkan berkas satuan kerja ini; putusan suratnya menilai perkaranya secara utuh.',
            'Mbak Puspi: “satker 1 sama satker 2 sudah, cuma satker 3 karena masih ada kekurangan makanya rekomendasi ini belum selesai, belum memadai.”',
            'Begitu satuan kerja terakhir memadai, CHV berikutnya baru bisa berbunyi memadai — dan yang dipakai selalu CHV yang terakhir terbit.',
          ]" />
        </div>
      @endif

      <div class="fld">
        <span class="lbl">Tanda tiap satuan kerja
          <x-info :teks="[
            'Ini catatan siapa yang sudah dan siapa yang belum, bukan penentu putusan.',
            'Putusan rekomendasi tetap satu: '.mb_strtolower($kataM).' kalau semuanya sudah, '.mb_strtolower($kataBM).' kalau ada satu pun yang belum.',
            'Boleh dikosongkan — yang kosong ikut putusan akhir.',
          ]" />
        </span>
        <div style="display:grid;gap:8px">
          <div class="kartutanda">
            <input type="hidden" name="tanda_hasil" value="" data-tanda-hasil>
            <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
              <span style="font-weight:600;font-size:13px;flex:1;min-width:150px">{{ $x->satker->namaPendek() }}</span>
              <div style="display:flex;gap:5px">
                <button type="button" class="btn btn-s" data-tanda="M">{{ $kataM }}</button>
                <button type="button" class="btn btn-s" data-tanda="BM">{{ $kataBM }}</button>
              </div>
            </div>
            <input type="text" name="tanda_catatan" placeholder="Catatan untuk satuan kerja ini — boleh dikosongkan" data-tanda-catatan>
          </div>
        </div>
      </div>

      <div class="hint" style="color:var(--jingga);margin-bottom:12px" data-beda-usul hidden></div>

      <div class="duo">
        <label class="fld">
          <span class="lbl">Nomor {{ $sebutSurat }} <span data-wajib-surat>— wajib diisi</span>
            <x-info :teks="[
              'Status berdiri di atas surat. Menyatakan memadai tanpa nomor suratnya berarti menyatakan sesuatu yang tidak punya dasar.',
              'Waktu mengembalikan berkas, nomornya boleh menyusul — yang wajib catatannya, supaya satuan kerja tahu apa yang kurang.',
            ]" />
          </span>
          <input type="text" class="mono" name="nomor" placeholder="{{ $uki ? '031/VAL-UKI/BPSDM/VIII/2026' : '65/CHV/ITJEN/VIII/2026' }}" data-f="nomor">
        </label>
        <label class="fld">
          <span class="lbl">Tanggal surat <span data-wajib-surat>— wajib diisi</span></span>
          <input type="date" name="tgl_surat" max="{{ now()->toDateString() }}" data-f="tgl_surat">
        </label>
      </div>

      @if(! $uki)
        <label class="fld" style="max-width:260px" data-hanya-bm hidden>
          <span class="lbl">Batas waktu perbaikan — wajib diisi
            <x-info :teks="[
              'Ditetapkan Inspektorat bersama penolakannya — sampai kapan satuan kerja harus memperbaiki.',
              'Satuan kerja membacanya sesudah Setba mengirim ulang berkasnya, bersama alasannya.',
            ]" />
          </span>
          <input type="date" name="batas_waktu" min="{{ now()->toDateString() }}" data-f="batas_waktu">
        </label>
      @endif

      <label class="fld">
        <span class="lbl">Catatan <span data-ket-catatan>— boleh dikosongkan</span></span>
        <textarea name="catatan" data-f="catatan"
          data-contoh-m="Contoh: bukti setor dan Nota Konfirmasi KPPN sudah lengkap dan cocok dengan nilai temuan."
          data-contoh-bm="Contoh: bukti setor belum dilampiri Nota Konfirmasi KPPN."></textarea>
      </label>

      <div class="fld" data-hanya-bm hidden>
        <span class="lbl">Dokumen yang diminta — boleh dikosongkan
          <x-info :teks="[
            'Dokumen yang harus dilengkapi satuan kerja saat pemberkasan ulang.',
            'Berkasnya ke Setba dulu. Setba memeriksa daftar ini, boleh menyesuaikannya, lalu mengirim ulang ke satuan kerja.',
          ]" />
        </span>
        @include('rekomendasi.bagian.daftar-isian', ['nama' => 'dokumen', 'nilai' => [''], 'placeholder' => 'Contoh: Nota Konfirmasi KPPN setoran sisa', 'tambah' => 'Tambah dokumen'])
      </div>

      <div class="opsional" data-opsional>
        <button type="button" title="Buka isian yang boleh dikosongkan">
          <span data-opsional-ikon-buka><x-ikon n="Plus" :s="15" /></span><span data-opsional-ikon-tutup hidden><x-ikon n="ChevronUp" :s="15" /></span>
          {{ $uki ? 'Perihal surat dan tautan berkas' : 'LHV, perihal surat, dan tautan berkas' }}
          <span class="n" data-opsional-ket>{{ $uki ? 2 : 4 }} isian · boleh dikosongkan</span>
        </button>
        <div class="isi" hidden>
          @if(! $uki)
            <div class="duo">
              <label class="fld"><span class="lbl">Nomor LHV (surat pengantar)</span>
                <input type="text" class="mono" name="nomor_lhv" placeholder="PW.02.01-Ij/412"></label>
              <label class="fld"><span class="lbl">Tanggal LHV</span>
                <input type="date" name="tgl_lhv" max="{{ now()->toDateString() }}"></label>
            </div>
          @endif
          <label class="fld"><span class="lbl">Perihal surat</span>
            <input type="text" name="perihal" placeholder="{{ $uki ? 'Hasil validasi tindak lanjut' : 'Catatan hasil verifikasi tindak lanjut' }}"></label>
          <div class="fld">
            <span class="lbl">Tautan berkas hasil {{ $uki ? 'validasi' : 'verifikasi' }}</span>
            <div class="duo">
              <label class="fld"><span class="lbl">Judul berkas</span>
                <input type="text" name="berkas" placeholder="{{ $uki ? 'Catatan hasil validasi UKI' : 'Catatan hasil verifikasi Inspektorat' }}"></label>
              <label class="fld"><span class="lbl">Tautan berkas</span>
                <input type="text" class="mono" name="tautan" placeholder="https://…"></label>
            </div>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:9px;flex-wrap:wrap">
        <button type="submit" class="btn" data-tetapkan data-pastikan='{}'>
          <x-ikon n="Check" :s="14" /> <span data-teks-tetapkan>Tetapkan</span>
        </button>
        <button type="button" class="btn" data-batal-mode><x-ikon n="X" :s="14" /> Batal</button>
      </div>
      <div class="hint" style="margin-top:8px" data-belum-siap></div>
    </div>
  </div>
</form>
