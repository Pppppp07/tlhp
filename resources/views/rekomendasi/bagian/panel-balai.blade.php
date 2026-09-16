@php
  use App\Aksi\Kemajuan;
  use App\Support\Tampil;

  /* Satuan kerja mengisi tindak lanjut satu baris — padanan PanelBalai.
     Isian dimuat dari draf yang tersimpan, bukan dari kosong: itu yang
     membuatnya bisa disunting lagi. Hitungan tombol dan kalimat bilah
     dikerjakan skrip; penjaganya tetap di TanggapanController. */
  $draf = $x->draf;
  $bukti = collect($draf?->bukti ?? []);
  $setoran = collect($draf?->setoran ?? []);
  $item = $r->permintaanUntuk($x->satker_id, $x->tindakan_id)->flatMap->item->where('terpenuhi', false)->values();
  $dana = $r->progresDana($x->satker_id, $x->tindakan_id);
  $angsur = $r->rencanaAngsur();
  $endap = $draf ? ['umur' => \App\Models\Rekomendasi::selisih($draf->terakhir)] : null;
  if ($endap) {
    $endap['sisa'] = max(0, Kemajuan::HARI_ENDAP - $endap['umur']);
    $endap['jatuh'] = $endap['umur'] >= Kemajuan::HARI_ENDAP;
  }
  $bentukLain = $r->tindakan->filter(fn ($tk) => $r->semuaBaris()->contains(fn ($y) => $y->tindakan_id === $tk->id && $y->satker_id === $x->satker_id))->count() > 1;
  $berkasLama = $r->lampiran->filter(fn ($d) => $d->label_oleh === $x->satker->namaPendek()
    && (! $d->tindakan_id || $d->tindakan_id === $x->tindakan_id))->map(fn ($d) => $d->nama_asli)->values();
  $idx = 0;
  $lewat = $x->batas_perbaikan && $x->batas_perbaikan->copy()->startOfDay()->lt(now()->startOfDay());
@endphp

<form method="post" action="{{ route('tanggapan.simpan', $x) }}" class="tindakan dibaris" data-panel-balai
  data-target="{{ $dana['target'] ?? 0 }}" data-masuk="{{ $dana['masuk'] ?? 0 }}"
  data-angsur-rencana="{{ $angsur['rencana'] ?? 0 }}" data-angsur-kunci="{{ ($angsur['kunci'] ?? false) ? 1 : 0 }}"
  data-angsur-sudah="{{ $r->pemulihan->count() }}" data-setoran-lama="{{ $r->pemulihan->count() }}"
  data-bentuk="{{ $x->tindakan?->namaBentuk() }}" data-bentuk-lain="{{ $bentukLain ? 1 : 0 }}"
  data-berkas-lama='@json($berkasLama)'
  data-endap='@json($endap)'>
  @csrf
  <input type="hidden" name="tanggal" value="{{ $draf?->tanggal?->toDateString() ?? now()->toDateString() }}">

  <div class="judul">
    <x-ikon n="ClipboardCheck" :s="16" />
    Isi tindak lanjut{{ $x->tindakan?->bentuk ? ' — '.$x->tindakan->namaBentuk() : '' }}
    <x-info teks="Isian ini khusus untuk bentuk tindak lanjut tersebut. Bentuk lain pada rekomendasi yang sama punya isiannya sendiri." />
  </div>

  @if($x->kembali_dari || $x->batas_perbaikan)
    <div class="akibat{{ $lewat ? ' bad' : '' }}" style="margin-top:0;margin-bottom:12px">
      <x-ikon n="AlertTriangle" :s="15" />
      <span>
        Ditolak {{ $x->kembali_dari ?: 'Inspektorat' }}, dikirim ulang Setba untuk pemberkasan
        {{ 'ulang' }}@if($x->batas_perbaikan) — perbaiki paling lambat <b>{{ Tampil::tgl($x->batas_perbaikan) }}</b>{{ $lewat ? ' (sudah lewat)' : '' }}@endif.
        @if($x->alasan_perbaikan) Alasan: {{ $x->alasan_perbaikan }}@endif
        @if($x->keterangan_setba)<span style="display:block;margin-top:4px">Keterangan Setba: {{ $x->keterangan_setba }}</span>@endif
        @if($x->dokumen_diminta)<span style="display:block;margin-top:4px">Dokumen yang diminta: {{ implode(', ', $x->dokumen_diminta) }} — lampirkan di daftar dokumen di bawah.</span>@endif
      </span>
    </div>
  @endif

  <div class="isianberkas">
    <label class="isibaris">
      <span class="lbl">Apa yang sudah dikerjakan</span>
      <textarea name="uraian" placeholder="Jelaskan tindakan yang sudah diambil pada tahap ini." data-uraian>{{ $draf?->uraian }}</textarea>
      <div class="hint">Kalimat ini yang dibaca Setba lebih dulu, sebelum membuka berkasnya.</div>
    </label>

    <div class="isibaris">
      <div class="kepalaisi">
        <span class="lbl">Dokumen yang diminta</span>
        @if($item->isNotEmpty())<span class="tanda" data-sisa-dok>{{ $item->count() }} dari {{ $item->count() }} belum diunggah</span>@endif
      </div>

      <div class="fld" style="margin-bottom:0">
        @if($item->isNotEmpty())
          <ul class="ceklis">
            @foreach($item as $i)
              @php $b = $bukti->first(fn ($y) => (int) ($y['untuk'] ?? 0) === $i->id); $n = $idx++; @endphp
              <li data-butir="{{ $i->id }}">
                <span style="flex:none;margin-top:1px" data-tanda-butir>
                  <span data-ikon-ada @if(! $b) hidden @endif style="color:var(--ok)"><x-ikon n="CheckCircle2" :s="16" /></span>
                  <span data-ikon-belum @if($b) hidden @endif style="color:var(--ink-3)"><x-ikon n="Circle" :s="16" /></span>
                </span>
                <span style="flex:1;min-width:0">
                  <span @class(['sudah' => (bool) $b]) data-nama-butir>{{ $i->nama }}</span>
                  <span style="display:block;margin-top:7px" data-isi-butir @if(! $b) hidden @endif>
                    <span class="duo">
                      <label class="fld"><span class="lbl">Judul berkas</span>
                        <input type="text" name="bukti[{{ $n }}][nama]" value="{{ $b['nama'] ?? '' }}" placeholder="{{ $i->nama }}" @if(! $b) disabled @endif data-bukti-nama></label>
                      <label class="fld"><span class="lbl">Tautan berkas</span>
                        <input type="text" class="mono" name="bukti[{{ $n }}][tautan]" value="{{ $b['tautan'] ?? '' }}" placeholder="https://…" @if(! $b) disabled @endif data-bukti-tautan></label>
                    </span>
                    <input type="hidden" name="bukti[{{ $n }}][jenis]" value="{{ $i->nama }}" @if(! $b) disabled @endif>
                    <input type="hidden" name="bukti[{{ $n }}][untuk]" value="{{ $i->id }}" @if(! $b) disabled @endif>
                  </span>
                </span>
                <button type="button" class="btn btn-s" data-hapus-butir @if(! $b) hidden @endif><x-ikon n="X" :s="12" /> Hapus</button>
                <button type="button" class="btn btn-s" data-tambah-butir @if($b) hidden @endif><x-ikon n="ExternalLink" :s="13" /> Tambah tautan</button>
              </li>
            @endforeach
          </ul>
        @endif

        <div data-berkas-lepas data-mulai="{{ $idx + 100 }}">
          @foreach($bukti->filter(fn ($y) => empty($y['untuk'])) as $b)
            @php $n = $idx++; @endphp
            <div style="display:flex;align-items:flex-start;gap:10px;margin-top:10px" data-lepas>
              <span style="flex:1;min-width:0">
                <span class="duo">
                  <label class="fld"><span class="lbl">Judul berkas</span>
                    <input type="text" name="bukti[{{ $n }}][nama]" value="{{ $b['nama'] ?? '' }}" placeholder="Contoh: Bukti setor dan Nota Konfirmasi KPPN" data-bukti-nama></label>
                  <label class="fld"><span class="lbl">Tautan berkas</span>
                    <input type="text" class="mono" name="bukti[{{ $n }}][tautan]" value="{{ $b['tautan'] ?? '' }}" placeholder="https://…" data-bukti-tautan></label>
                </span>
                <input type="hidden" name="bukti[{{ $n }}][jenis]" value="{{ $b['jenis'] ?? 'Bukti dukung' }}">
              </span>
              <button type="button" class="btn btn-s" style="margin-top:22px" data-hapus-lepas><x-ikon n="X" :s="12" /> Hapus</button>
            </div>
          @endforeach
        </div>
        <template data-templat-lepas>
          <div style="display:flex;align-items:flex-start;gap:10px;margin-top:10px" data-lepas>
            <span style="flex:1;min-width:0">
              <span class="duo">
                <label class="fld"><span class="lbl">Judul berkas</span>
                  <input type="text" name="bukti[__i__][nama]" placeholder="Contoh: Bukti setor dan Nota Konfirmasi KPPN" data-bukti-nama></label>
                <label class="fld"><span class="lbl">Tautan berkas</span>
                  <input type="text" class="mono" name="bukti[__i__][tautan]" placeholder="https://…" data-bukti-tautan></label>
              </span>
              <input type="hidden" name="bukti[__i__][jenis]" value="Bukti dukung">
            </span>
            <button type="button" class="btn btn-s" style="margin-top:22px" data-hapus-lepas><x-ikon n="X" :s="12" /> Hapus</button>
          </div>
        </template>

        <button type="button" class="btn btn-s" style="margin-top:12px" data-tambah-lepas>
          <x-ikon n="Plus" :s="13" /> {{ $item->isNotEmpty() ? 'Tambah tautan lain' : 'Tambah tautan berkas' }}
        </button>
        <div class="hint" style="color:var(--jingga);margin-top:8px" data-bukti-kurang hidden></div>
      </div>
    </div>

    @if($dana)
      <button type="button" class="btn btn-lebar" data-buka-pulih @if($setoran->isNotEmpty()) hidden @endif>
        <x-ikon n="Wallet" :s="15" /> Pemulihan nilai
        <span class="ket">{{ Tampil::rupiah($dana['target']) }} belum dicatat pemulihannya</span>
      </button>

      <div class="isibaris" data-isi-pulih @if($setoran->isEmpty()) hidden @endif>
        <div class="kepalaisi">
          <span class="lbl">Pemulihan nilai</span>
          @if($angsur)<span class="tanda" data-tanda-angsur></span>@endif
        </div>
        <div class="fld" style="margin-bottom:0">
          <div style="margin-bottom:12px">
            <div class="prog">
              <span class="bar dana"><i data-bar-pulih style="width:0%"></i></span>
              <b data-bakal>Rp 0</b><span>dari {{ Tampil::rupiah($dana['target']) }}</span>
            </div>
            <div class="hint" style="color:var(--bad)" data-lebih hidden></div>
          </div>

          <div data-daftar-setor data-mulai="{{ $setoran->count() }}">
            @foreach($setoran as $n => $st)
              @include('rekomendasi.bagian.baris-setor', ['n' => $n, 'st' => $st])
            @endforeach
          </div>
          <template data-templat-setor>
            @include('rekomendasi.bagian.baris-setor', ['n' => '__i__', 'st' => []])
          </template>

          <button type="button" class="btn" data-tambah-setor><x-ikon n="Plus" :s="14" /> Tambah baris pemulihan</button>
          <div class="hint" style="margin-top:7px" data-kuota-habis hidden>
            Rencana {{ $angsur['rencana'] ?? 0 }} angsuran sudah terpakai seluruhnya. Minta Setba menyesuaikan rencananya bila masih ada setoran lain.
          </div>
          <div class="hint" style="margin-top:7px;color:var(--warn)" data-lewat-rencana hidden>
            Melebihi rencana {{ $angsur['rencana'] ?? 0 }} angsuran — tetap dicatat.
          </div>
          <div class="hint" style="margin-top:7px;color:var(--bad)" data-setor-kurang hidden></div>
        </div>
      </div>
    @endif
  </div>

  <div class="bilah">
    <button type="submit" class="btn" name="aksi" value="draf" data-simpan-draf>
      <x-ikon n="Check" :s="14" /> {{ $draf ? 'Perbarui draf' : 'Simpan draf' }}
    </button>
    <x-info teks="Berkas tidak pindah ke mana-mana. Isinya masih bisa diubah sampai Anda menekan Kirim." />
    <button type="submit" class="btn btn-p" name="aksi" value="kirim" data-kirim-setba data-pastikan='{}'>
      <x-ikon n="Send" :s="14" /> Kirim ke Setba
    </button>
    <span class="ket" data-ket-bilah></span>
  </div>

  <div class="pesan" style="margin-top:12px;margin-bottom:0" data-pesan-endap hidden>
    <x-ikon n="Clock" :s="16" /><span data-teks-endap></span>
  </div>
</form>
