@php
  use App\Enums\HasilTelaah;
  use App\Enums\PosisiBerkas;
  use App\Http\Controllers\RincianController;
  use App\Support\Tampil;

  /* Satuan kerja hanya melihat bagiannya sendiri — `$baris` sudah dipangkas. */
  $isi = $baris->where('tindakan_id', $tk->id)->values();
@endphp

@if($isi->isNotEmpty())
@php
  $lewatRenaksi = $tk->tgl_renaksi && $tk->tgl_renaksi->copy()->startOfDay()->lt(now()->startOfDay()) && ! $r->tanpaTenggat();
  $memadaiTk = $isi->filter(fn ($x) => $x->hasil === HasilTelaah::M)->count();
  $perluTk = $isi->filter(fn ($x) => RincianController::aksiBaris($r, $x, $peran, $u->satker_id))->count();
  $adaBpk = $jenis->melewatiSiptl();
  $idTiket = 'tiket-'.$tk->id;
@endphp

<div class="tlblok tl-tiket" data-tiket>
  <button type="button" class="kep" aria-expanded="false" aria-controls="{{ $idTiket }}" data-buka-tiket>
    <span class="tiket-utama">
      <span class="no">{{ $k + 1 }}</span>
      <span class="inti">
        <span class="nama">{{ $tk->bentuk?->nama ?? 'Tindak lanjut' }}</span>
        <span class="meta">
          {{-- Angka satuan kerja tidak disebutkan kepada satuan kerja. --}}
          @if(! $balai)
            <span class="tiket-info"><x-ikon n="Users" :s="12" />{{ $isi->count() }} satuan kerja</span>
            <span class="tiket-info"><x-ikon n="CheckCircle2" :s="12" />{{ $memadaiTk }} {{ mb_strtolower(HasilTelaah::M->nama($jenis)) }}</span>
          @endif
          @if($tk->tgl_renaksi)
            <span class="tiket-info" @if($lewatRenaksi) style="color:var(--bad)" @endif>
              <x-ikon n="Calendar" :s="12" />Rencana aksi {{ Tampil::tgl($tk->tgl_renaksi) }}
            </span>
          @endif
        </span>
      </span>
    </span>
    <span class="tiket-sisi">
      @if($perluTk > 0)<span class="pil biru">{{ $perluTk }} perlu dikerjakan</span>@endif
      <span class="ajak">
        <span data-ajak-tutup hidden>Tutup rincian</span><span data-ajak-buka>Lihat satuan kerja</span>
        <span class="panahbaris"><x-ikon n="ChevronRight" :s="14" /></span>
      </span>
    </span>
  </button>

  {{-- Tabel satuan kerja — padanan RuangTindakLanjut. Tertutup sejak awal;
       skrip yang menyembunyikannya, jadi tanpa skrip isinya tetap terbaca. --}}
  <div class="tw tl-tabel-wrap" id="{{ $idTiket }}" data-isi-tiket>
    <table class="tabtl tl-tabel" aria-label="Tindak lanjut satuan kerja" data-tabel-tl data-satu="{{ $isi->count() === 1 ? 1 : 0 }}">
      <colgroup>
        <col style="width:48px"><col><col style="width:140px"><col>
        <col style="width:65px"><col style="width:65px">
        @if($adaBpk)<col style="width:65px">@endif<col style="width:112px">
      </colgroup>
      <thead><tr>
        <th class="num kolno">No</th><th>Satuan kerja</th><th class="num">Nilai</th>
        <th>Posisi berkas</th><th class="selhasil" title="Hasil validasi UKI">UKI</th>
        <th class="selhasil" title="Hasil verifikasi Inspektorat">Itjen</th>
        @if($adaBpk)<th class="selhasil" title="Status BPK yang dicatat dari SIPTL">SIPTL</th>@endif
        <th class="kolaksi">Aksi</th>
      </tr></thead>
      <tbody>
        @foreach($isi as $i => $x)
          @php
            $pos = $x->pos();
            $info = $x->presentasi($jenis);
            $aks = RincianController::aksiBaris($r, $x, $peran, $u->satker_id);
            $kunci = $x->tindakan_id.'|'.$x->satker_id;
            $idRinci = 'rinci-'.$x->id;
            $keSiptl = $adaBpk && $pos === PosisiBerkas::TUNTAS && ! $info['selesai'];
            $perbaikan = in_array($pos, [PosisiBerkas::SATKER, PosisiBerkas::SETBA_KEMBALI], true) ? $x->alasan_perbaikan : '';
            $penilaian = [
              ['nama' => 'Validasi UKI', 'hasil' => $x->hasil_uki ? $x->hasil_uki->nama($jenis) : 'Belum tercatat'],
              ['nama' => 'Verifikasi Inspektorat', 'hasil' => $x->hasil ? $x->hasil->nama($jenis) : 'Belum tercatat'],
            ];
            if ($adaBpk) {
              $penilaian[] = ['nama' => 'Penilaian BPK', 'hasil' => $x->siptl_tanggal
                ? ($x->status_bpk ?? \App\Enums\StatusTindakLanjut::BT)->pendek() : 'Belum diunggah ke SIPTL'];
            }
          @endphp
          <tr class="bukaan" data-baris-tl="{{ $idRinci }}" data-ada-aksi="{{ $aks ? 1 : 0 }}">
            <td class="num kolno mono">{{ $i + 1 }}</td>
            <td><button type="button" class="tl-buka-nama" aria-expanded="false" aria-controls="{{ $idRinci }}" title="{{ $x->satker->nama }}" data-buka-baris>
              <span class="panahbaris"><x-ikon n="ChevronRight" :s="13" /></span>{{ $x->satker->namaPendek() }}
            </button></td>
            <td class="num mono" style="font-weight:600">{{ (int) $x->nilai > 0 ? Tampil::rupiah($x->nilai) : '—' }}</td>
            <td><span>{{ $info['judul'] }}</span><span class="tl-di-meja">{{ $info['selesai'] ? 'Selesai' : 'Di '.$info['pemegang'] }}</span></td>
            @foreach($penilaian as $p)
              @php
                $kode = ['Memadai' => 'M', 'Belum memadai' => 'BM', 'Sesuai' => 'SS', 'Belum sesuai' => 'BS',
                  'Sudah sesuai' => 'SS', 'Belum ditindaklanjuti' => 'BT', 'Tidak dapat ditindaklanjuti' => 'TD'][$p['hasil']] ?? null;
                $nada = in_array($kode, ['M', 'SS'], true) ? 'baik' : (in_array($kode, ['BM', 'BS'], true) ? 'kurang' : '');
              @endphp
              <td class="selhasil">
                @if($kode)
                  <span class="tl-status {{ $nada }}" title="{{ $p['hasil'] }}" aria-label="{{ $p['hasil'] }}">{{ $kode }}</span>
                @else
                  <span class="tl-di-meja" title="{{ $p['hasil'] }}" aria-label="{{ $p['hasil'] }}">—</span>
                @endif
              </td>
            @endforeach
            <td class="kolaksi">
              <button type="button" class="btn btn-s{{ $aks ? ' btn-p' : '' }}" aria-controls="{{ $idRinci }}" aria-expanded="false" data-kerjakan>
                <span data-label-tutup hidden><x-ikon n="ChevronUp" :s="13" />Tutup</span>
                <span data-label-buka><x-ikon n="ListChecks" :s="13" />{{ $aks ? 'Kerjakan' : 'Lihat' }}</span>
              </button>
            </td>
          </tr>
          <tr class="lebar" data-rinci-tl="{{ $idRinci }}">
            <td colspan="{{ $adaBpk ? 8 : 7 }}">
              <div id="{{ $idRinci }}" class="isilebar">
                <section class="tl-detail" aria-label="Tindak lanjut {{ $x->satker->namaPendek() }}" data-detail-tl>
                  @include('rekomendasi.bagian.rel-baris', ['posisi' => $pos, 'kembaliDari' => $x->kembali_dari])
                  <div class="tl-petunjuk">
                    <p>{{ $info['langkah'] }}</p>
                    @if($keSiptl)
                      <a class="taut" href="#r-siptl" data-tunjuk="r-siptl">Buka urusan SIPTL<x-ikon n="ArrowRight" :s="13" /></a>
                    @endif
                  </div>
                  @if($perbaikan)
                    <div class="tl-perbaikan" data-perbaikan><b>Catatan pengembalian{{ $x->kembali_dari ? ' · '.$x->kembali_dari : '' }}</b>
                      <p>{{ $perbaikan }}</p>@if($x->batas_perbaikan)<span>Batas perbaikan: {{ Tampil::tgl($x->batas_perbaikan) }}</span>@endif</div>
                  @endif
                  <div class="tl-panel-nav">
                    <div class="tl-tabs" role="tablist" aria-label="Isi tindak lanjut">
                      <button type="button" role="tab" aria-selected="true" data-tab-tl="bukti"><x-ikon n="Paperclip" :s="14" />Bukti &amp; tanggapan</button>
                      @if($aks)
                        <button type="button" role="tab" aria-selected="false" data-tab-tl="kerja"><x-ikon n="ListChecks" :s="14" />Kerjakan</button>
                      @endif
                    </div>
                    <button type="button" class="taut tl-riwayat" data-lihat-riwayat="{{ $kunci }}"><x-ikon n="Clock" :s="13" />Lihat riwayat</button>
                  </div>
                  <div role="tabpanel" class="tl-panel" data-panel-tl="bukti">
                    @include('rekomendasi.bagian.rinci-satker', ['x' => $x])
                  </div>
                  @if($aks)
                    <div role="tabpanel" class="tl-panel tl-form" data-panel-tl="kerja" hidden>
                      @if($peran === \App\Enums\PeranPengguna::SATKER)
                        @include('rekomendasi.bagian.panel-balai', ['x' => $x])
                      @elseif($pos === PosisiBerkas::SETBA_KEMBALI)
                        @include('rekomendasi.bagian.panel-kirim-ulang', ['x' => $x])
                      @elseif(in_array($pos, [PosisiBerkas::SETBA_TINJAU, PosisiBerkas::SETBA_TERUSKAN], true))
                        @include('rekomendasi.bagian.panel-teruskan', ['x' => $x])
                      @else
                        @include('rekomendasi.bagian.panel-periksa', ['x' => $x])
                      @endif
                    </div>
                  @endif
                </section>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif
