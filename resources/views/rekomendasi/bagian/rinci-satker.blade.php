@php
  use App\Support\Tampil;

  /* Rincian satu satuan kerja pada satu bentuk tindak lanjut — padanan
     RinciSatker. Yang diminta, yang dilaporkan, setorannya, dan berkasnya:
     semuanya dikerucutkan ke baris ini. */
  $minta = $r->permintaanUntuk($x->satker_id, $x->tindakan_id)->flatMap->item;
  $jawab = $r->tanggapan->where('sasaran_id', $x->id);
  $setor = $r->pemulihan->where('sasaran_id', $x->id);
  /* Berkas kiriman satuan kerja ini untuk bentuk ini; bukti setor tidak ikut
     — ia tampil di barisnya sendiri. */
  $berkas = $r->lampiran->filter(fn ($d) => $d->label_oleh === $x->satker->namaPendek()
    && (! $d->tindakan_id || $d->tindakan_id === $x->tindakan_id));
  $dana = (int) $x->nilai > 0 ? $r->progresDana($x->satker_id, $x->tindakan_id) : null;
  $tolak = $r->totalTolakan($x->satker_id, $x->tindakan_id);
  $kosong = $minta->isEmpty() && ! $dana && ! $tolak && $setor->isEmpty() && $jawab->isEmpty() && $berkas->isEmpty() && ! $x->catatan;
@endphp

@if($kosong)
  <div class="konteks" style="color:var(--ink-2)">
    Belum ada yang dilaporkan untuk tindak lanjut ini.
    Tanggapan dan bukti akan tampil di sini setelah dikirim satuan kerja.
  </div>
@else
  <div class="tl-bukti">
    @if($berkas->isNotEmpty())
      <section>
        <h4>Bukti yang sudah dikirim</h4>
        <div class="tl-isi"><div style="display:grid;gap:6px">
          @foreach($berkas as $d)<x-berkas :b="$d" />@endforeach
        </div></div>
      </section>
    @endif
    @if($minta->isNotEmpty())
      <section>
        <h4>Kelengkapan dokumen yang diminta</h4>
        <div class="tl-isi">
          <details @if($minta->contains(fn ($i) => ! $i->terpenuhi)) open @endif>
            <summary>{{ $minta->where('terpenuhi', true)->count() }} dari {{ $minta->count() }} dokumen diminta sudah terlampir</summary>
            <ul class="ceklisrapat">
              @foreach($minta as $item)
                <li @class(['ada' => $item->terpenuhi])>
                  <x-ikon :n="$item->terpenuhi ? 'CheckCircle2' : 'Circle'" :s="15" />
                  <span>{{ $item->nama }}</span>
                </li>
              @endforeach
            </ul>
          </details>
        </div>
      </section>
    @endif
    @if($jawab->isNotEmpty())
      <section>
        <h4>Tanggapan satuan kerja</h4>
        <div class="tl-isi">
          <ul class="barisrinci">
            @foreach($jawab as $t)
              <li><span class="tgl mono">{{ Tampil::tgl($t->tanggal) }}</span><span>{{ $t->uraian }}</span></li>
            @endforeach
          </ul>
        </div>
      </section>
    @endif
    @if($dana)
      <section>
        <h4>Pemulihan nilai</h4>
        <div class="tl-isi"><span class="mono">{{ $dana['masuk'] ? Tampil::rupiah($dana['masuk']) : 'Rp 0' }} dari {{ Tampil::rupiah($dana['target']) }}</span></div>
        <div class="lbl" style="margin-top:5px">{{ $dana['sisa'] === 0 ? 'lunas' : 'sisa '.Tampil::rupiah($dana['sisa']) }}</div>
      </section>
    @endif
    @if($tolak > 0)
      <section>
        <h4>Tidak diterima BPK<x-info :teks="[
          'Nilai yang sudah disetor tapi buktinya tidak diterima BPK.',
          'Bukti setornya tetap tersimpan — yang dicabut hanya pengakuannya, jadi sisa tagihannya terbuka lagi sebesar ini.',
        ]" /></h4>
        <div class="tl-isi"><span class="mono" style="color:var(--bad)">{{ Tampil::rupiah($tolak) }}</span></div>
        <div class="lbl" style="margin-top:5px">harus ditindaklanjuti ulang</div>
      </section>
    @endif
    @if($setor->isNotEmpty())
      <section>
        <h4>Bukti setor</h4>
        <div class="tl-isi">
          <ul class="barisrinci">
            @foreach($setor as $s)
              <li>
                <span class="tgl mono">{{ Tampil::tgl($s->tanggal) }}</span>
                <span class="nilai"><b class="mono">{{ Tampil::rupiah($s->nilai) }}</b><span class="lbl">NTPN {{ $s->ntpn }}</span></span>
              </li>
            @endforeach
          </ul>
        </div>
      </section>
    @endif
    @if($x->catatan)
      <section>
        <h4>Catatan penilai</h4>
        <div class="tl-isi">{{ $x->catatan }}</div>
      </section>
    @endif
    @if($berkas->isEmpty())
      <p class="tl-kosong" style="padding:0;margin:0">Belum ada bukti terkirim untuk tindak lanjut ini.</p>
    @endif
  </div>
@endif
