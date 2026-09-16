@php
  use App\Enums\PosisiBerkas;

  /* Setba meneruskan berkas SATU baris — padanan panelSetbaBaris. Isian
     suratnya langsung terbuka; tidak ada tombol pembuka lebih dulu. */
  $keUki = $x->pos() === PosisiBerkas::SETBA_TINJAU;
  $terakhir = $suratTerakhir[$keUki ? 'uki' : 'inspektorat'] ?? null;
  $tujuan = $keUki ? 'UKI' : 'Inspektorat';
  $banyakBentuk = $r->semuaBaris()->where('satker_id', $x->satker_id)->pluck('tindakan_id')->unique()->count() > 1;
  $pastikan = [
    'judul' => "Teruskan berkas ke {$tujuan}?",
    'ket' => 'Berkas '.$x->satker->namaPendek().($banyakBentuk ? ' ('.$x->tindakan->namaBentuk().')' : '')
      .' keluar dari meja Setba. Untuk menariknya kembali harus menunggu pihak tujuan mengembalikannya. Satuan kerja lain pada rekomendasi ini tidak ikut berangkat.',
    'tombol' => "Ya, teruskan ke {$tujuan}",
  ];
@endphp

<form method="post" action="{{ route('sasaran.teruskan', $x) }}" id="r-terus" class="tindakan dibaris rata" data-form-surat>
  @csrf
  <div class="isian">
    <div>
      <span class="lbl"><x-ikon n="Send" :s="13" /> Tindakan Setba</span>
      <div style="font-size:var(--t3);font-weight:600">
        {{ $x->satker->namaPendek() }}
        <x-info :teks="[
          $keUki ? 'UKI tidak menerima berkas tanpa surat pengantar dari Setba.' : 'Nomor surat ini yang dirujuk Inspektorat saat menerbitkan CHV.',
          'Yang berangkat hanya satuan kerja pada baris ini. Yang lain tetap di tempatnya.',
        ]" />
      </div>
    </div>

    {{-- Surat terakhir ke tujuan yang sama ditawarkan, dan disebut terang-
         terangan supaya tidak terkirim tanpa diperiksa. --}}
    @if($terakhir)
      <div data-surat-terakhir='@json($terakhir)'>
        <span class="lbl"></span>
        <div class="akibat" style="margin-top:0">
          <x-ikon n="Send" :s="14" />
          <span>Surat terakhir ke {{ $tujuan }}: <b class="mono">{{ $terakhir['nomor'] }}</b>.
            <button type="button" class="taut" data-pakai-surat>Pakai surat yang sama</button></span>
        </div>
      </div>
    @endif

    <div>
      <span class="lbl">Nomor surat
        <x-info :teks="[
          $keUki ? 'Surat permohonan validasi dari Setba kepada UKI.' : 'Surat permohonan verifikasi dari Setba kepada Inspektorat.',
          'Nomor, tanggal, dan perihal wajib diisi. Tautan pindaiannya boleh menyusul.',
          'Satu surat boleh memuat beberapa satuan kerja — nomor yang sama tinggal diisikan lagi pada barisnya masing-masing.',
        ]" />
      </span>
      <input type="text" class="mono" name="nomor" placeholder="{{ $keUki ? 'PW.02.02-Sb/128' : 'PW.02.02-Sb/241' }}" required data-surat="nomor">
    </div>
    <div>
      <span class="lbl">Tanggal surat</span>
      <input type="date" name="tanggal" max="{{ now()->toDateString() }}" required data-surat="tanggal">
    </div>
    <div>
      <span class="lbl">Perihal</span>
      <input type="text" name="perihal" placeholder="{{ $keUki ? 'Permohonan validasi tindak lanjut' : 'Permohonan verifikasi tindak lanjut' }}" required data-surat="perihal">
    </div>
    <div>
      <span class="lbl"></span>
      <div class="opsional" data-opsional>
        <button type="button" title="Buka isian yang boleh dikosongkan">
          <span data-opsional-ikon-buka><x-ikon n="Plus" :s="15" /></span><span data-opsional-ikon-tutup hidden><x-ikon n="ChevronUp" :s="15" /></span>
          Tautan surat dan catatan
          <span class="n" data-opsional-ket>2 isian · boleh dikosongkan</span>
        </button>
        <div class="isi" hidden>
          <div class="isian">
            <div><span class="lbl">Tautan surat</span><input type="text" class="mono" name="tautan" placeholder="https://…" data-surat="tautan"></div>
            <div><span class="lbl">Catatan</span><input type="text" name="catatan" placeholder="Keterangan tambahan untuk penerima" data-surat="catatan"></div>
          </div>
        </div>
      </div>
    </div>

    <div>
      <span class="lbl"></span>
      <div style="display:flex;gap:9px;flex-wrap:wrap">
        <button type="submit" class="btn btn-p" data-pastikan='@json($pastikan)' data-butuh-surat>
          <x-ikon n="Send" :s="14" /> Teruskan ke {{ $tujuan }}
        </button>
        <div class="hint" style="margin-top:8px;width:100%" data-surat-kurang>Nomor, tanggal, dan perihal surat harus terisi.</div>
      </div>
    </div>
  </div>
</form>
