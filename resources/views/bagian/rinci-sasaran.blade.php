@php
  use App\Support\Tampil;

  /* Rincian satu baris: apa yang diminta dari satuan kerja ini pada bentuk
     tindak lanjut ini, dan apa yang sudah dikerjakannya.

     Dikerucutkan ke SATU sasaran, bukan ke rekomendasinya. Satuan kerja yang
     memikul dua bentuk tindak lanjut punya dua daftar dokumen, dua kiriman,
     dan dua kemajuan nilai — digabung, orang mengira semuanya harus
     dilampirkan sekaligus. */
  $dok = $s->progresDokumen();
  $butir = $s->permintaanDokumen->flatMap->item;
  $setor = $s->pemulihan;
  $lapor = $s->tanggapan;
  $berkas = $s->lampiran->reject(fn ($b) => $b->ditarik());
@endphp

<dl class="kv rincibaris">
  @if($butir->isNotEmpty())
    <dt>Dokumen diminta</dt>
    <dd>
      <ul class="ceklisrapat">
        @foreach($butir as $it)
          <li @class(['ada' => $it->terpenuhi])>
            <span class="tik">{{ $it->terpenuhi ? '✓' : '○' }}</span>
            <span>{{ $it->nama }}</span>
          </li>
        @endforeach
      </ul>
      <div class="lbl" style="margin-top:5px">{{ $dok[0] }} dari {{ $dok[1] }} terpenuhi</div>
    </dd>
  @endif

  @if($s->nilai > 0)
    {{-- Keduanya ditulis penuh. Yang dipendekkan jadi "Rp 18 jt" berdampingan
         dengan "Rp 17.800.000" terbaca seperti dua besaran yang berbeda. --}}
    <dt>Pemulihan nilai</dt>
    <dd class="mono">
      {{ $s->nilaiTerpulihkan() ? Tampil::rupiah($s->nilaiTerpulihkan()) : 'Rp 0' }}
      dari {{ Tampil::rupiah($s->nilai) }}
      <span class="lbl">&middot;
        {{ $s->lunas() ? 'lunas' : 'sisa '.Tampil::rupiah($s->sisaPemulihan()) }}</span>
    </dd>
  @endif

  @if($setor->isNotEmpty())
    <dt>Bukti setor</dt>
    <dd>
      <ul class="barisrinci">
        @foreach($setor as $x)
          <li>
            <span class="tgl mono">{{ Tampil::tgl($x->tanggal) }}</span>
            <span class="nilai">
              <b class="mono">{{ Tampil::rupiah($x->nilai) }}</b>
              <span class="lbl">
                @if($x->ntpn) NTPN {{ $x->ntpn }}
                @elseif($x->no_berita_acara) BA {{ $x->no_berita_acara }}
                @endif
              </span>
            </span>
          </li>
        @endforeach
      </ul>
    </dd>
  @endif

  @if($lapor->isNotEmpty())
    <dt>Dilaporkan</dt>
    <dd>
      <ul class="barisrinci">
        @foreach($lapor as $x)
          <li>
            <span class="tgl mono">{{ Tampil::tgl($x->tanggal) }}</span>
            <span>{{ $x->uraian }}</span>
          </li>
        @endforeach
      </ul>
    </dd>
  @endif

  @if($berkas->isNotEmpty())
    <dt>Berkas terkirim</dt>
    <dd>
      <div style="display:grid;gap:6px">
        @foreach($berkas as $b)
          <div>
            @include('bagian/berkas', ['b' => $b])
            <span class="lbl">&middot; {{ $b->jenisDokumen?->nama }}
              &middot; {{ Tampil::tgl($b->diunggah_pada) }}</span>
          </div>
        @endforeach
      </div>
    </dd>
  @endif

  @if($s->surat->isNotEmpty())
    {{-- Nomor surat pengantar. Inilah yang disebut orang saat menanyakan
         berkasnya lewat telepon, dan sampai sekarang ia tercatat tapi tidak
         pernah bisa dibaca dari halaman mana pun. --}}
    <dt>Surat pengantar</dt>
    <dd>
      <ul class="barisrinci">
        @foreach($s->surat as $x)
          <li>
            <span class="tgl mono">{{ Tampil::tgl($x->tanggal) }}</span>
            <span>
              <b class="mono">{{ $x->nomor }}</b>
              <span class="lbl">&middot; {{ $x->ringkas() }}</span>
              @if($x->perihal)<div class="lbl">{{ $x->perihal }}</div>@endif
              @if($x->lampiran)
                <div style="margin-top:4px">@include('bagian/berkas', ['b' => $x->lampiran])</div>
              @endif
            </span>
          </li>
        @endforeach
      </ul>
    </dd>
  @endif

  @if($s->telaah->isNotEmpty())
    <dt>Telaah atas baris ini</dt>
    <dd>
      <ul class="barisrinci">
        @foreach($s->telaah as $x)
          <li>
            <span class="tgl mono">{{ Tampil::tgl($x->tanggal) }}</span>
            <span>
              @if($x->hasil)
                <span class="cap {{ $x->hasil->cap() }}">{{ $x->hasil->nama($sumber) }}</span>
              @endif
              {{ $x->catatan }}
              <span class="lbl">&middot; {{ $x->label_oleh }}</span>
            </span>
          </li>
        @endforeach
      </ul>
    </dd>
  @endif

  @if($s->pengembalian->isNotEmpty())
    <dt>Pernah dikembalikan</dt>
    <dd>
      <ul class="barisrinci">
        @foreach($s->pengembalian as $x)
          <li>
            <span class="tgl mono">{{ Tampil::tgl($x->tanggal) }}</span>
            <span>{{ $x->alasan }} <span class="lbl">&middot; {{ $x->label_oleh }}</span></span>
          </li>
        @endforeach
      </ul>
    </dd>
  @endif

  @if($s->catatan)
    <dt>Catatan</dt>
    <dd>{{ $s->catatan }}</dd>
  @endif
</dl>

@if($butir->isEmpty() && $s->nilai == 0 && $setor->isEmpty() && $lapor->isEmpty()
    && $berkas->isEmpty() && $s->telaah->isEmpty() && $s->pengembalian->isEmpty()
    && $s->surat->isEmpty() && ! $s->catatan)
  <div style="font-size:13px;color:var(--ink-3)">
    Belum ada yang tercatat pada baris ini.
  </div>
@endif
