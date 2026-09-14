@php
  use App\Enums\JenisPermintaanUbah;
  use App\Enums\PeranPengguna;
  use App\Enums\PosisiBerkas;
  use App\Enums\StatusPermintaanUbah;
  use App\Support\Tampil;

  /* Permintaan perubahan atas berkas yang sudah dikirim.

     Berkas yang sudah lepas dari meja satuan kerja tidak bisa ditarik sendiri,
     dan tidak bisa dihapus siapa pun. Yang bisa dilakukan cuma meminta — dan
     yang memegang berkasnya yang memutuskan. Begitu surat verifikasi terbit,
     seluruhnya terkunci. */

  $u = auth()->user();
  $menunggu = $r->permintaanUbah->where('status', StatusPermintaanUbah::MENUNGGU);
  $sudah = $r->permintaanUbah->where('status', '!=', StatusPermintaanUbah::MENUNGGU);
  $sayaPemutus = $u->peran === $r->pemutusPerubahan();

  /* Baris milik pembaca yang berkasnya sudah terkirim. Yang masih di mejanya
     sendiri tidak perlu meminta apa pun — ia tinggal mengubahnya. */
  $barisSaya = $u->peran === PeranPengguna::SATKER
    ? $r->daftarSasaran()
        ->where('satker_id', $u->satker_id)
        ->reject(fn ($s) => $s->posisi === PosisiBerkas::SATKER)
    : collect();

  $bolehMinta = $barisSaya->isNotEmpty()
    && ! $r->terkunciOlehSurat()
    && ! $r->adaPermintaanMenunggu();
@endphp

@if($menunggu->isNotEmpty() || $sudah->isNotEmpty() || $bolehMinta)
<div class="kartu" id="r-ubah" style="margin-bottom:14px;scroll-margin-top:80px">
  <div class="judulkartu">
    <span class="ic-kotak jingga"><x-ik nama="putar-balik" ukuran="17" /></span>
    <h3>Permintaan perubahan</h3>
    @if($menunggu->isNotEmpty())
      <span class="n">{{ $menunggu->count() }} menunggu keputusan</span>
    @endif
    <x-info :teks="[
      'Berkas yang sudah dikirim tidak bisa ditarik sendiri oleh satuan kerja, dan tidak bisa dihapus oleh unit mana pun.',
      'Yang bisa dilakukan hanya meminta — dan yang memegang berkasnya yang memutuskan.',
      'Koreksi dan tarik pengiriman mengembalikan berkasnya ke satuan kerja; yang mengubah isinya tetap satuan kerjanya sendiri.',
      'Begitu surat verifikasi terbit, seluruhnya terkunci dan tidak ada permintaan yang bisa diajukan maupun disetujui.',
    ]" />
  </div>

  @foreach($menunggu as $m)
    <div class="mintaubah tunggu">
      <div style="display:flex;gap:9px;align-items:baseline;flex-wrap:wrap;margin-bottom:5px">
        <span class="cap cap-BT">{{ $m->jenis->nama() }}</span>
        <span class="lbl">
          diajukan {{ $m->label_pengaju }} &middot; {{ Tampil::tgl($m->tanggal) }}
        </span>
      </div>
      <div style="font-size:13px">{{ $m->alasan }}</div>
      @if($m->lampiranSasaran)
        <div class="lbl" style="margin-top:4px">
          berkas yang dituju: {{ $m->lampiranSasaran->labelTampil() }}
        </div>
      @endif

      @if($sayaPemutus && ! $r->terkunciOlehSurat())
        <form method="post" action="{{ route('ubah.putus', $m) }}"
          style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--rule)">
          @csrf
          <label class="f">
            <span class="lbl">Catatan keputusan</span>
            <input type="text" name="catatan"
              placeholder="alasan menyetujui atau menolak">
          </label>
          <div style="display:flex;gap:9px;flex-wrap:wrap">
            <button class="btn btn-p" type="submit" name="putusan" value="setuju">Setujui</button>
            <button class="btn" type="submit" name="putusan" value="tolak">Tolak</button>
          </div>
        </form>
      @else
        <div class="lbl" style="margin-top:6px">
          menunggu keputusan {{ $r->pemutusPerubahan()->pendek() }}
        </div>
      @endif
    </div>
  @endforeach

  @if($bolehMinta)
    <details style="margin-top:{{ $menunggu->isNotEmpty() ? '12px' : '0' }}">
      <summary class="lbl" style="cursor:pointer">
        Perlu mengubah berkas yang sudah dikirim?
      </summary>
      <form method="post" action="{{ route('ubah.ajukan', $barisSaya->first()) }}"
        style="margin-top:11px">
        @csrf
        <label class="f">
          <span class="lbl">Jenis permintaan</span>
          <select name="jenis">
            @foreach(JenisPermintaanUbah::cases() as $j)
              <option value="{{ $j->value }}">{{ $j->nama() }}</option>
            @endforeach
          </select>
          <span class="hint">
            Koreksi dan tarik pengiriman mengembalikan berkasnya ke Anda untuk diperbaiki.
            Batalkan berkas menyatakan satu dokumen tidak berlaku lagi.
          </span>
        </label>

        @php $berkasSaya = $barisSaya->flatMap->lampiran->reject(fn ($b) => $b->ditarik()); @endphp
        @if($berkasSaya->isNotEmpty())
          <label class="f">
            <span class="lbl">Berkas yang dituju &mdash; hanya untuk "Batalkan berkas"</span>
            <select name="lampiran_sasaran_id">
              <option value="">— tidak menunjuk berkas tertentu —</option>
              @foreach($berkasSaya as $b)
                <option value="{{ $b->id }}">{{ $b->labelTampil() }}</option>
              @endforeach
            </select>
          </label>
        @endif

        <label class="f">
          <span class="lbl">Alasan &mdash; wajib diisi</span>
          <textarea name="alasan" required minlength="6" style="min-height:52px"
            placeholder="Sebutkan apa yang keliru dan apa yang perlu diperbaiki."></textarea>
        </label>

        <button class="btn" type="submit">Ajukan permintaan</button>
        <div class="hint" style="margin-top:8px">
          Berkasnya belum berubah saat permintaan dikirim. Ia menunggu keputusan
          {{ $r->pemutusPerubahan()->pendek() }}.
        </div>
      </form>
    </details>
  @elseif($barisSaya->isNotEmpty() && $r->terkunciOlehSurat())
    <div class="hint">
      Surat verifikasi sudah terbit. Berkasnya terkunci dan tidak bisa diubah lagi.
    </div>
  @endif

  @if($sudah->isNotEmpty())
    <div class="lbl" style="margin-top:14px;margin-bottom:8px">Sudah diputus</div>
    <ul class="barisrinci">
      @foreach($sudah as $m)
        <li>
          <span class="tgl mono">{{ Tampil::tgl($m->tgl_putus) }}</span>
          <span>
            <span class="cap {{ $m->status === StatusPermintaanUbah::DISETUJUI ? 'cap-SS' : 'cap-BS' }}">
              {{ $m->status === StatusPermintaanUbah::DISETUJUI ? 'Disetujui' : 'Ditolak' }}
            </span>
            {{ $m->jenis->nama() }}
            <span class="lbl">&middot; {{ $m->label_pengaju }} &rarr; {{ $m->label_pemutus }}</span>
            <div style="font-size:13px">{{ $m->alasan }}</div>
            @if($m->catatan_putus)
              <div class="lbl">{{ $m->catatan_putus }}</div>
            @endif
          </span>
        </li>
      @endforeach
    </ul>
  @endif
</div>
@endif
