@php
  use App\Enums\HasilTelaah;
  use App\Enums\StatusTindakLanjut;
  use App\Support\Tampil;

  /* Riwayat status tindak lanjut — padanan RiwayatStatusTL. Kartu ini menyorot
     SATU pasangan bentuk tindak lanjut dan satuan kerja; tiap pasangan punya
     periodenya sendiri, jadi seluruhnya digambar dan skrip menukar yang
     tampil. Tanpa skrip yang pertama yang terlihat. */
  $SUMBER = ['UKI' => ['uki', 'UKI'], 'ITJEN' => ['itjen', 'Itjen'], 'SIPTL' => ['siptl', 'SIPTL'],
    'KEMBALI' => ['balik', 'Kembali'], 'SATKER' => ['satker', 'Satker'], 'SETBA' => ['setba', 'Setba']];
  $keping = function (?string $kode) use ($jenis) {
      if (! $kode) {
          return '';
      }
      if ($s = StatusTindakLanjut::tryFrom($kode)) {
          return '<span class="cap '.$s->cap().'" title="'.e($s->pendek()).'">'.$kode.'</span>';
      }
      $h = HasilTelaah::tryFrom($kode);

      return $h ? view('components.cap-hasil', ['jenis' => $jenis, 'hasil' => $h])->render() : e($kode);
  };
  $banyak = count($riwayat) > 1;
@endphp

@foreach($riwayat as $n => $kartu)
<div id="{{ $n === 0 ? 'r-riwayat' : 'r-riwayat-'.$n }}" class="card rw-riwayat" style="margin-bottom:14px;padding:0"
  data-riwayat-lingkup="{{ $kartu['kunci'] }}" @if($n > 0) hidden @endif>
  <div style="padding:18px 20px 4px">
    <div class="judulkartu">
      <span class="ic-kotak"><x-ikon n="Clock" :s="17" /></span>
      <h3>
        Riwayat status tindak lanjut
        <x-info :teks="[
          'Seluruh perjalanan status satu tindak lanjut dalam satu tabel, dari yang terbaru ke yang terdahulu.',
          'Yang disorot tertulis tepat di bawah judul: satuan kerja mana, dan bentuk tindak lanjut yang mana.',
          'Satu satuan kerja bisa memikul dua bentuk tindak lanjut sekaligus, dan keduanya punya riwayat sendiri-sendiri. Karena itu tombol di atas memilih PASANGAN tindak lanjut dan satuan kerja, bukan satuan kerjanya saja.',
          'Isinya gabungan lima catatan yang dulu berdiri sendiri-sendiri: hasil telaah, riwayat validasi UKI, riwayat verifikasi Inspektorat, pengembalian berkas, dan perubahan status barisnya.',
          'Satu putusan satu baris: tanda statusnya, suratnya, dan pengembaliannya ditulis bersama.',
          'Baris Satker dan Setba mencatat pergerakan berkas: kapan satuan kerja mengirim, dan kapan Setba meneruskannya ke UKI atau Inspektorat dengan surat apa.',
          'Perjalanan periode di atas tabel menunjukkan ke mana saja berkasnya bergerak di periode yang sedang berjalan, dan di mana sekarang. Rute periode yang sudah ditutup tertulis di kepala periodenya.',
          'Surat CHV tergambar pada tindak lanjut yang diputusnya. Kalau rekomendasinya secara keseluruhan belum memadai, keterangannya ikut tertulis.',
          'UKI, Itjen, dan SIPTL menilai hal yang berbeda, jadi ketiganya boleh berselisih. Urutan di sinilah yang menjelaskan mana yang berlaku sekarang.',
          'Yang tercatat nama pengetiknya, bukan nama otoritasnya. Hasil verifikasi Inspektorat sering diketik Setba.',
          'Baris bertanda biru di paling atas adalah peristiwa terakhir.',
          'Satu periode verifikasi adalah satu putaran penuh: dari satuan kerja, naik sampai gerbang terakhirnya, lalu dinilai.',
          'Periode ditutup kalau gerbang terakhir itu menolak dan berkasnya dikembalikan — LHP di BPK lewat SIPTL, LHA di verifikasi Inspektorat.',
          'Penolakan UKI, dan penolakan Inspektorat pada LHP, tidak menutup periode: berkasnya memang mundur, tapi putarannya belum pernah selesai.',
        ]" />
      </h3>
      <span class="n">{{ count($kartu['baris']) }} peristiwa</span>
    </div>

    <div class="lingkupriwayat">
      <span><x-ikon n="Building2" :s="13" style="color:var(--ink-3)" /><b title="{{ $kartu['satker']->nama }}">{{ $kartu['satker']->namaPendek() }}</b></span>
      @if($kartu['bentuk'])
        <span class="pisah">|</span>
        <span><x-ikon n="ListChecks" :s="13" style="color:var(--ink-3)" />{{ $kartu['bentuk'] }}</span>
      @endif
      <span class="pisah">|</span>
      <span title="{{ $jenis->melewatiSiptl()
        ? 'Satu periode ditutup kalau BPK menolak lewat SIPTL dan berkasnya dikembalikan ke satuan kerja.'
        : 'Satu periode ditutup kalau Inspektorat menilai belum sesuai dan berkasnya dikembalikan ke satuan kerja.' }}">
        <x-ikon n="RotateCcw" :s="13" style="color:var(--ink-3)" /><b>Periode {{ $kartu['periodeKini'] }}</b>
      </span>
    </div>

    @if($banyak)
      <div class="saringriwayat">
        @foreach($riwayat as $l)
          <button type="button" aria-pressed="{{ $l['kunci'] === $kartu['kunci'] ? 'true' : 'false' }}" data-pilih-riwayat="{{ $l['kunci'] }}">{{ $l['nama'] }}</button>
        @endforeach
      </div>
    @endif

    <div class="rw-perjalanan">
      <div class="rw-rute-kep">Perjalanan periode {{ $kartu['periodeKini'] }}</div>
      <div class="rw-rute" role="group" tabindex="0" aria-label="Perjalanan berkas periode {{ $kartu['periodeKini'] }}, urut dari awal ke akhir">
        @foreach($kartu['jalur'] as $i => $j)
          @php $akhir = $i === count($kartu['jalur']) - 1; @endphp
          <div class="rw-ruas">
            @if($i > 0)<x-ikon n="ArrowRight" :s="13" class="rw-panah" />@endif
            <span class="rw-tempat{{ $akhir ? ($j['tempat'] === 'Selesai' ? ' selesai' : ' kini') : '' }}" title="{{ $j['tanggal'] ? Tampil::tgl($j['tanggal']) : 'awal periode' }}">
              <b>@if($j['tempat'] === 'Selesai')<x-ikon n="CheckCircle2" :s="12" />@endif{{ $j['tempat'] }}</b>
              <small>{{ $j['tanggal'] ? Tampil::tgl($j['tanggal']) : 'Awal periode' }}</small>
            </span>
          </div>
        @endforeach
      </div>
      <div class="rw-sekarang{{ $kartu['selesai'] ? ' selesai' : '' }}">
        <x-ikon :n="$kartu['selesai'] ? 'CheckCircle2' : 'CircleDot'" :s="15" />
        <span><span class="rw-redup">Posisi saat ini</span><b>{{ $kartu['sekarang'] }}</b></span>
      </div>
    </div>
  </div>

  <div class="rw-urutan"><span>Catatan peristiwa</span><span><x-ikon n="ChevronDown" :s="12" />Terbaru di atas</span></div>
  <div class="rw-tabel-wrap">
    @php $jmlKolom = 4 + ($kartu['adaSurat'] ? 1 : 0); $pertama = $kartu['baris'][0]['id'] ?? null; @endphp
    <table class="tabriwayat" aria-label="Riwayat tindak lanjut {{ $kartu['satker']->namaPendek() }}">
      <thead>
        <tr>
          <th scope="col" style="width:116px">Tanggal</th>
          <th scope="col" style="width:82px">Sumber</th>
          <th scope="col" style="width:205px">Peristiwa &amp; hasil</th>
          <th scope="col">Catatan</th>
          @if($kartu['adaSurat'])<th scope="col" style="width:180px">Surat terkait</th>@endif
        </tr>
      </thead>
      <tbody>
        @if(! count($kartu['baris']))
          <tr><td colspan="{{ $jmlKolom }}"><span class="hint" style="margin:0">Belum ada perubahan status yang tercatat untuk tindak lanjut ini.</span></td></tr>
        @endif
        @foreach($kartu['babak'] as $g)
          @if($kartu['periodeKini'] > 1)
            <tr class="periode{{ $g['k'] === $kartu['periodeKini'] ? ' kini' : '' }}">
              <td colspan="{{ $jmlKolom }}">
                <div class="isi">
                  <x-ikon n="RotateCcw" :s="12" />
                  Periode {{ $g['k'] }}{{ $g['k'] === $kartu['periodeKini'] ? ' · berjalan' : '' }}
                  @if($g['jalur'])
                    <span class="jalurmini" title="Perjalanan berkas di periode ini">{{ collect($g['jalur'])->pluck('tempat')->join(' → ') }}</span>
                  @endif
                  <span class="kanan">{{ ! count($g['isi']) ? 'belum ada kegiatan' : ($g['tutup'] ? 'ditutup '.Tampil::tgl($g['tutup']) : count($g['isi']).' peristiwa') }}</span>
                </div>
              </td>
            </tr>
          @endif
          @foreach($g['isi'] as $b)
            @php
              [$kelas, $namaSumber] = $SUMBER[$b['sumber']] ?? ['siptl', $b['sumber'] ?: '—'];
              $terkini = $b['id'] === $pertama;
              $ket = $b['ket'] ?? null;
              $judul = $ket ?: match ($b['sumber']) {
                  'UKI' => 'Hasil validasi UKI',
                  'ITJEN' => 'Hasil verifikasi Inspektorat',
                  'SIPTL' => (($b['dari'] ?? '') === 'BS' && ($b['ke'] ?? '') === 'BT') ? 'Dikirim ulang ke satker'
                      : ((empty($b['dari']) && ($b['ke'] ?? '') === 'BT') ? 'Diunggah ke SIPTL' : 'Hasil penilaian BPK'),
                  default => 'Perubahan status',
              };
              $ke = $b['ke'] ?? '';
              $arti = $ke ? (StatusTindakLanjut::tryFrom($ke)?->pendek() ?? HasilTelaah::tryFrom($ke)?->nama($jenis) ?? '') : '';
              $catatan = (string) ($b['catatan'] ?? '');
            @endphp
            <tr @class(['terkini' => $terkini])>
              <td class="rw-tanggal">
                <time datetime="{{ $b['tanggal'] }}">{{ Tampil::tgl($b['tanggal']) }}</time>
                @if($terkini)<span class="rw-terbaru">Terbaru</span>@endif
              </td>
              <td><span class="asal {{ $kelas }}">{{ $namaSumber }}</span></td>
              <td>
                <div class="rw-peristiwa">
                  <div class="rw-judul-peristiwa">{{ $judul }}</div>
                  @if(! $ket && $ke)
                    <div class="rw-perubahan" aria-label="{{ ! empty($b['dari']) && $b['dari'] !== $ke ? 'Status '.$b['dari'].' menjadi '.$ke : 'Status '.$ke }}">
                      @if(! empty($b['dari']) && $b['dari'] !== $ke)
                        <span class="rw-status-lama">{!! $keping($b['dari']) !!}</span><x-ikon n="ArrowRight" :s="13" />
                      @endif
                      {!! $keping($ke) !!}
                    </div>
                    @if($arti)<span class="rw-arti-hasil">{{ $arti }}</span>@endif
                  @endif
                </div>
              </td>
              <td>
                @if($catatan === '')
                  <span class="rw-redup">Tidak ada catatan tambahan.</span>
                @elseif(mb_strlen($catatan) <= 180)
                  <p class="rw-catatan-teks">{{ $catatan }}</p>
                @else
                  <details class="rw-catatan-panjang">
                    <summary><span class="rw-cuplikan">{{ preg_replace('/\s+\S*$/u', '', mb_substr($catatan, 0, 180)) }}…</span>
                      <span class="rw-baca"><span class="rw-buka">Baca selengkapnya</span><span class="rw-tutup">Tutup catatan</span><x-ikon n="ChevronDown" :s="12" /></span>
                    </summary>
                    <p class="rw-catatan-teks">{{ $catatan }}</p>
                  </details>
                @endif
                @if(! empty($b['tolak']))
                  <div class="rw-pengembalian">
                    {{ ! empty($b['kembali']) ? 'Berkas dikembalikan ke satuan kerja' : 'Berkas ke Setba untuk dikirim ulang' }}{{ ! empty($b['batasWaktu']) ? ' · perbaiki paling lambat '.Tampil::tgl($b['batasWaktu']) : '' }}
                  </div>
                @endif
                @if(! empty($b['dokumenDiminta']))
                  <details class="rw-dokumen"><summary>{{ count($b['dokumenDiminta']) }} dokumen diminta untuk perbaikan</summary>
                    <ul>@foreach($b['dokumenDiminta'] as $nama)<li>{{ $nama }}</li>@endforeach</ul>
                  </details>
                @endif
                @if(! empty($b['kembali']['keteranganSetba']))
                  <div style="font-size:12.5px;margin-top:3px">Keterangan Setba: {{ $b['kembali']['keteranganSetba'] }}</div>
                @endif
                @if(! empty($b['catatanRek']))<div class="lbl" style="margin-top:3px">{{ $b['catatanRek'] }}</div>@endif
                @if(! empty($b['oleh']) || ($b['lingkup'] ?? '') === 'rek')
                  <div class="rw-pencatat">{{ collect([! empty($b['oleh']) ? 'Dicatat oleh '.$b['oleh'] : '', ($b['lingkup'] ?? '') === 'rek' ? 'berlaku untuk seluruh rekomendasi' : ''])->filter()->join(' · ') }}</div>
                @endif
              </td>
              @if($kartu['adaSurat'])
                <td class="rw-surat">
                  @if(! empty($b['nomor']))
                    <div class="rw-nomor-surat"><x-ikon n="FileText" :s="13" /><span>{{ $b['nomor'] }}</span></div>
                  @endif
                  @if(! empty($b['nomorLhv']) || ! empty($b['tglLhv']))
                    <div class="lbl" style="margin-bottom:4px">LHV {{ $b['nomorLhv'] ?? '' }}{{ ! empty($b['tglLhv']) ? ' · '.Tampil::tgl($b['tglLhv']) : '' }}</div>
                  @endif
                  @if(! empty($b['berkas']))
                    <x-berkas :b="$b['berkas']" :jenis="$b['judulBerkas'] ?? null" :oleh="$b['oleh'] ?? null" :tanggal="$b['tanggal']" />
                  @elseif(empty($b['nomor']) && empty($b['nomorLhv']) && empty($b['tglLhv']))
                    <span class="rw-redup">Tidak tercatat</span>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endforeach
