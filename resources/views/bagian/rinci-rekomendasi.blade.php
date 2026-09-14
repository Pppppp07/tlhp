@php
  use App\Support\Tampil;
  use App\Support\Terlihat;

  /* Rincian sebuah rekomendasi, dibentangkan di dalam barisnya pada daftar.
     Menjawab tiga pertanyaan yang memang ditanyakan orang, tidak lebih:

       "kenapa belum selesai"  -> baris per satuan kerja, siapa yang menahan
       "sudah sampai mana"     -> posisi berkas tiap satuan kerja
       "berapa angkanya"       -> pemulihan nilai dan kelengkapan dokumen

     Yang perlu bertindak membuka halaman penuh. Panel tindakan tidak ditaruh
     di sini: isiannya panjang, dan mengisinya di dalam baris tabel membuat
     tabelnya melar sampai barisnya sendiri hilang dari pandangan. */

  $lap = $r->temuan->laporan;
  $sumberRinci = $lap->sumber;
  $sayaSatkerRinci = auth()->user()->peran === \App\Enums\PeranPengguna::SATKER;

  $barisRinci = Terlihat::untuk()->barisRekomendasi($r);
  $dok = $r->progresDokumen();
  $telatRinci = $r->lewatTenggat();

  /* Tanggal yang mengikat pembacanya. Satuan kerja melihat tenggat bentuk
     tindak lanjut yang membebaninya, bukan tenggat rekomendasi seutuhnya. */
  $tglRinci = $r->renaksiUntuk($sayaSatkerRinci ? auth()->user()->satker_id : null);
  $sisaHari = $tglRinci
    ? (int) now()->startOfDay()->diffInDays($tglRinci->startOfDay(), false)
    : null;
@endphp

<div class="isilebar">
  <div class="kepala">
    <span class="pil biru">Ref LHP {{ $r->refLhp() }}</span>
    <x-kode ket="Ref IDT" isi="{{ $r->refIdt() }}" />
    <div style="flex:1"></div>
    {{-- Lencana yang sama dengan halaman rinciannya, supaya satu putusan tidak
         tampil dalam dua bentuk berbeda di dua layar. --}}
    @php $putusanRinci = $r->putusan() ?? \App\Enums\HasilTelaah::BM; @endphp
    <span class="cap {{ $putusanRinci->cap() }}">{{ $putusanRinci->nama($sumberRinci) }}</span>
    @if($sumberRinci->melewatiSiptl())
      <span class="cap cap-{{ $r->status->value }}">
        {{ $r->status->value }} &middot; {{ $r->status->nama() }}
      </span>
    @endif
  </div>

  {{-- Temuan asalnya, sebagai keterangan — bukan sebagai judul. Yang dibaca
       orang di sini rekomendasinya; temuannya menjelaskan kenapa. --}}
  <div class="konteks">
    <b>{{ $r->temuan->judul }}.</b> {{ $r->temuan->sebab }}
  </div>

  <div>
    <div class="lbl" style="margin-bottom:7px">
      {{ $sayaSatkerRinci ? 'Tindak lanjut satuan kerja saya' : 'Tindak lanjut tiap satuan kerja' }}
      <x-info :teks="$sayaSatkerRinci ? [
        'Bagian satuan kerja ini saja. Rekomendasi yang sama bisa membebani satuan kerja lain, dan bagian mereka bukan urusan di sini.',
        'Rekomendasi baru bergerak sesudah seluruh satuan kerja yang dituju tuntas.',
      ] : [
        'Inilah jawaban atas pertanyaan yang paling sering muncul: rekomendasi ini kenapa belum selesai.',
        'Tiap satuan kerja menempuh rantainya sendiri, jadi posisinya bisa berbeda-beda.',
        'Rekomendasi baru bergerak sesudah seluruhnya tuntas.',
      ]" />
    </div>

    @forelse($barisRinci as $x)
      <div class="barissatker">
        <b>{{ $x->satker?->namaPendek() ?? '—' }}</b>
        <span class="dimana">{{ $x->sebutanPosisi() }}</span>
        <span class="mono" style="font-size:12px;text-align:right">
          {{ $x->nilai > 0 ? Tampil::rupiah($x->nilai) : '' }}
        </span>
        @if($x->hasil)
          <span class="cap {{ $x->hasil->cap() }}">{{ $x->hasil->nama($sumberRinci) }}</span>
        @else
          <span class="lbl">belum ditelaah</span>
        @endif
      </div>
    @empty
      <div class="lbl">belum ditujukan ke satuan kerja mana pun</div>
    @endforelse
  </div>

  <div class="angkabaris">
    <div>
      <b>{{ Tampil::tgl($tglRinci) }}</b>
      <span class="lbl">
        rencana aksi
        @if($telatRinci) &middot; lewat {{ $telatRinci }} hari
        @elseif($sisaHari === 0) &middot; jatuh tempo hari ini
        @elseif($sisaHari !== null) &middot; {{ $sisaHari }} hari lagi
        @endif
      </span>
    </div>

    @if($dok)
      <div>
        <b>{{ $dok[0] }} dari {{ $dok[1] }}</b>
        <span class="lbl">dokumen yang diminta</span>
      </div>
    @endif

    @if($r->nilai_pulih > 0)
      <div>
        <b class="mono">{{ $r->nilaiTerpulihkan() ? Tampil::rupiah($r->nilaiTerpulihkan()) : 'Rp 0' }}</b>
        <span class="lbl">
          dipulihkan dari {{ Tampil::rupiah($r->nilai_pulih) }}
          @if($r->sisaPemulihan() > 0) &middot; sisa {{ Tampil::rupiah($r->sisaPemulihan()) }}
          @else &middot; lunas
          @endif
        </span>
      </div>
    @endif

    <div style="flex:1"></div>
    <a class="btn btn-p" href="{{ route('rekomendasi.show', $r) }}">
      Buka selengkapnya <x-ik nama="panah-kanan" ukuran="14" />
    </a>
  </div>
</div>
