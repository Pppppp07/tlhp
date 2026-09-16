@php
  use App\Enums\PeranPengguna;
  use App\Enums\StatusTindakLanjut;
  use App\Http\Controllers\RincianController;
  use App\Support\Tampil;

  /* SATU bagian untuk seluruh urusan SIPTL — padanan kartu Urusan SIPTL dan
     PanelSiptlSatker. Tampil untuk semua peran; formnya hanya untuk Setba. */
  $keadaanSiptl = RincianController::keadaanSiptl($r, $peran, $u->satker_id);
  $bolehKerja = $peran === PeranPengguna::SETBA;
@endphp

@if($keadaanSiptl)
@php
  $barisSiptl = $r->tindakan->flatMap(fn ($tk) => $baris->where('tindakan_id', $tk->id)->map(fn ($x) => ['x' => $x, 'tk' => $tk]))->values();
  $banyakBentuk = $barisSiptl->pluck('tk.id')->unique()->count() > 1;
  $adaYangNaik = $barisSiptl->contains(fn ($b) => (bool) $b['x']->siptl_tanggal);
  $adaNilai = $barisSiptl->contains(fn ($b) => (int) $b['x']->nilai > 0);
  $jmlKolom = 3 + ($bolehKerja ? 1 : 0) + ($adaNilai ? 1 : 0) + ($adaYangNaik ? 2 : 0);
  $hari = now()->toDateString();
@endphp
<div id="r-siptl" class="card" style="margin-top:16px">
  <div class="judulkartu">
    <span class="ic-kotak {{ $keadaanSiptl['nada'] }}"><x-ikon n="Landmark" :s="17" /></span>
    <h3>Urusan SIPTL</h3>
    <x-info :teks="[
      'SIPTL aplikasi milik BPK, di luar sistem ini. Yang diunggah ke sana adalah tindak lanjut satuan kerjanya — rekomendasinya sendiri tidak menempuh proses apa pun.',
      'Seluruhnya dikerjakan Setba dengan tangan: mengunggah di SIPTL, lalu membaca statusnya di sana dan menyalinnya kembali ke sini.',
      'BPK tidak mengirim pemberitahuan apa pun, jadi statusnya harus dicek berkala.',
      'Status BPK bisa Belum Sesuai gara-gara Unor lain yang bukan urusan BPSDM. Alasannya ditulis di catatan BPK.',
    ]" />
  </div>

  <div style="font-size:13px;margin-bottom:14px">{{ $keadaanSiptl['kalimat'] }}</div>

  <div id="r-bpk">
    <div class="tindakan">
      <div class="judul">
        <x-ikon n="Landmark" :s="18" /> Daftar satuan kerja
        <x-info :teks="[
          'Yang diunggah ke SIPTL adalah tindak lanjut tiap satuan kerja, jadi tiap satuan kerja punya unggahan, status, dan catatannya sendiri.',
          'Satu satuan kerja boleh naik ke SIPTL begitu tindak lanjutnya dinyatakan memadai — tidak perlu menunggu satuan kerja lain selesai.',
          'Status rekomendasinya dirangkum dari sini: yang paling belakang menentukan. Satu satuan kerja belum ditindaklanjuti membuat seluruh rekomendasi belum ditindaklanjuti.',
          'Aturan rangkuman itu diambil dari lembar pemantauan mereka sendiri — kolom Rank Status SiPTL dan Max Rank per Reff IDT.',
          'Selama belum ada satu pun yang diunggah, kolom Status BPK dan Catatan BPK tidak digambar: BPK memang belum melihat apa pun, jadi keduanya cuma akan berisi garis.',
        ]" />
      </div>

      <div class="tw">
        <table class="tabsatker">
          <thead>
            <tr>
              <th class="num" style="width:42px">No</th>
              <th>Satuan kerja{{ $banyakBentuk ? ' / tindak lanjut' : '' }}</th>
              @if($adaNilai)<th class="num" style="width:130px">Nilai tindak lanjut</th>@endif
              <th style="width:118px">Diunggah ke SIPTL</th>
              @if($adaYangNaik)<th style="width:132px">Status BPK</th><th>Catatan BPK</th>@endif
              @if($bolehKerja)<th style="width:150px">Aksi</th>@endif
            </tr>
          </thead>
          <tbody>
            @foreach($barisSiptl as $i => $b)
              @php
                $x = $b['x'];
                $siapNaik = $x->perluUnggah($jenis);
                $belumSelesai = ! $x->tuntas();
                $st = $x->status_bpk ?? StatusTindakLanjut::BT;
                $idSunting = 'siptl-'.$x->id;
              @endphp
              <tr data-baris-siptl="{{ $idSunting }}">
                <td class="num mono">{{ $i + 1 }}</td>
                <td style="font-weight:600">
                  {{ $x->satker->namaPendek() }}
                  @if($banyakBentuk)<span class="lbl" style="display:block;margin:0">{{ $b['tk']->namaBentuk() }}</span>@endif
                </td>
                @if($adaNilai)<td class="num mono">{{ (int) $x->nilai > 0 ? Tampil::rupiah($x->nilai) : '—' }}</td>@endif
                <td>
                  @if($x->siptl_tanggal)
                    <span class="mono">{{ Tampil::tgl($x->siptl_tanggal) }}</span>
                  @else
                    <span style="color:var(--ink-3)">belum</span>
                  @endif
                </td>
                @if($adaYangNaik)
                  <td>
                    @if($x->siptl_tanggal)
                      <span class="cap {{ $st->cap() }}" title="{{ $st->pendek() }}">{{ $st->value }}</span>
                      @if($x->tgl_pantau)<span class="lbl" style="display:block;margin:3px 0 0">dipantau {{ Tampil::tgl($x->tgl_pantau) }}</span>@endif
                    @else
                      <span style="color:var(--ink-3)">—</span>
                    @endif
                  </td>
                  <td>
                    @if($x->catatan_bpk)
                      <button type="button" title="{{ $x->catatan_bpk }}" class="catatanpotong potong" data-catatan-potong>{{ $x->catatan_bpk }}</button>
                    @else
                      <span style="color:var(--ink-3)">—</span>
                    @endif
                  </td>
                @endif
                @if($bolehKerja)
                  <td>
                    @if(! $belumSelesai)
                      <span class="menuaksi" data-menu-aksi>
                        <button type="button" class="btn btn-s" aria-expanded="false">Aksi <x-ikon n="ChevronDown" :s="13" /></button>
                        <div class="daftaraksi" hidden>
                          <button type="button" data-buka-sunting="{{ $idSunting }}" data-mode="siptl">
                            <x-ikon :n="$siapNaik ? 'Upload' : 'Clock'" :s="14" /><span>{{ $siapNaik ? 'Catat unggahan ke SIPTL' : 'Perbarui status SIPTL' }}</span>
                          </button>
                          @if($st === StatusTindakLanjut::BS)
                            <button type="button" class="bad" data-buka-sunting="{{ $idSunting }}" data-mode="kembali">
                              <x-ikon n="RotateCcw" :s="14" /><span>Kirim ulang ke satuan kerja</span>
                            </button>
                          @endif
                        </div>
                      </span>
                    @else
                      <span class="lbl" style="display:block;margin:5px 0 0">belum selesai diperiksa</span>
                    @endif
                  </td>
                @endif
              </tr>

              @if($bolehKerja && ! $belumSelesai)
                <tr id="{{ $idSunting }}" data-sunting-siptl hidden>
                  <td colspan="{{ $jmlKolom }}" style="padding:0 10px 12px">
                    {{-- Mencatat unggahan, atau mencatat status BPK. --}}
                    <form method="post" data-mode-sunting="siptl" hidden
                      action="{{ $siapNaik ? route('siptl.unggah', $x) : route('siptl.status', $x) }}"
                      class="suntingsatker biru" data-form-siptl data-siap-naik="{{ $siapNaik ? 1 : 0 }}"
                      data-satker="{{ $x->satker->namaPendek() }}" data-hari="{{ $hari }}">
                      @csrf
                      <div class="kep">
                        <x-ikon :n="$siapNaik ? 'Upload' : 'Clock'" :s="17" style="color:var(--aksen)" />
                        <b>{{ $siapNaik ? 'Catat unggahan ke SIPTL' : 'Status SIPTL' }} — {{ $x->satker->namaPendek() }}</b>
                      </div>
                      @if($siapNaik)
                        <div class="hint" style="margin-bottom:10px">
                          Unggahannya dikerjakan di aplikasi SIPTL milik BPK. Yang dicatat di sini tanggalnya, dan statusnya
                          otomatis jadi Belum Ditindaklanjuti sampai BPK memutus. <b>Tanggalnya dikunci begitu dicatat</b> —
                          pastikan sama dengan yang tertulis di SIPTL.
                        </div>
                        {{-- Kotak tanggal unggah hanya muncul saat memang tahap unggah. --}}
                        <label class="fld" style="max-width:230px">
                          <span class="lbl">Tanggal unggah ke SIPTL</span>
                          <input type="date" name="tanggal" max="{{ $hari }}" data-tanggal-unggah>
                        </label>
                      @else
                        <div class="fld">
                          <span class="lbl">Hasil pemantauan BPK</span>
                          <input type="hidden" name="status" value="{{ in_array($st->value, ['BS', 'SS'], true) ? $st->value : '' }}" data-status-pilih>
                          <div class="pilihstatus dua">
                            @foreach([StatusTindakLanjut::BS, StatusTindakLanjut::SS] as $s)
                              <button type="button" class="kartupilih {{ $s->cap() }}" aria-pressed="{{ $st === $s ? 'true' : 'false' }}" data-kartu-status="{{ $s->value }}">
                                <b>{{ $s->value }}</b><span>{{ $s->pendek() }}</span>
                              </button>
                            @endforeach
                          </div>
                        </div>
                        <div data-bila-pilih @if(! in_array($st->value, ['BS', 'SS'], true)) hidden @endif>
                          <label class="fld" style="max-width:230px">
                            <span class="lbl">Tanggal pemantauan — boleh dikosongkan</span>
                            <input type="date" name="tgl_pantau" value="{{ $x->tgl_pantau?->toDateString() }}">
                          </label>
                          <label class="fld" style="margin-bottom:0">
                            <span class="lbl">Catatan BPK untuk satuan kerja ini <span data-wajib-catatan>— boleh dikosongkan</span></span>
                            <textarea rows="3" maxlength="500" name="catatan" placeholder="Salin apa yang tertulis di SIPTL untuk satuan kerja ini." data-hitung-huruf>{{ $x->catatan_bpk }}</textarea>
                            <div class="hitunghuruf" data-jumlah-huruf>{{ mb_strlen((string) $x->catatan_bpk) }}/500</div>
                          </label>
                        </div>
                        <div class="akibat bad" data-akibat-bs @if($st !== StatusTindakLanjut::BS) hidden @endif>
                          <x-ikon n="AlertTriangle" :s="15" />
                          <span>Sesudah disimpan, berkas satuan kerja ini bisa dikirim ulang lewat menu Aksi di barisnya.</span>
                        </div>
                      @endif
                      <div class="bilah" style="margin-top:14px">
                        <button type="submit" class="btn btn-p" data-pastikan='{}' data-simpan-siptl>
                          <x-ikon n="Check" :s="14" /> {{ $siapNaik ? 'Catat unggahan' : 'Simpan' }}
                        </button>
                        <button type="button" class="btn" data-tutup-sunting>Batal</button>
                        <span class="lbl" data-ket-siptl></span>
                      </div>
                    </form>

                    @if($st === StatusTindakLanjut::BS)
                      <form method="post" action="{{ route('siptl.ulangBpk', $r) }}" data-mode-sunting="kembali" hidden class="suntingsatker" data-form-ulang-bpk>
                        @csrf
                        <input type="hidden" name="sasaran_id" value="{{ $x->id }}">
                        <div class="kep">
                          <x-ikon n="RotateCcw" :s="17" style="color:var(--bad)" />
                          <b>Kirim ulang ke {{ $x->satker->namaPendek() }}</b>
                          @if((int) $x->nilai > 0)
                            <span class="nilai">
                              <span class="lbl" style="margin:0">Nilai tindak lanjut</span>
                              <b class="mono" style="display:block;font-size:var(--t4);color:var(--bad)">{{ Tampil::rupiah($x->nilai) }}</b>
                            </span>
                          @endif
                        </div>
                        @if((int) $x->nilai > 0)
                          <label class="fld" style="max-width:250px">
                            <span class="lbl">Nilai yang tidak diterima BPK — boleh dikosongkan</span>
                            <input type="number" class="mono" min="0" placeholder="0" name="nilai">
                          </label>
                        @endif
                        <label class="fld" style="margin-bottom:0">
                          <span class="lbl">Catatan untuk satuan kerja ini — wajib diisi</span>
                          <textarea rows="3" maxlength="500" name="alasan" placeholder="Sebutkan apa yang kurang di satuan kerja ini." data-hitung-huruf data-alasan-bpk>{{ $x->catatan_bpk }}</textarea>
                          <div class="hitunghuruf" data-jumlah-huruf>{{ mb_strlen((string) $x->catatan_bpk) }}/500</div>
                          <div class="hint">Kalimat inilah yang dibaca {{ $x->satker->namaPendek() }} saat berkasnya kembali.{{ $x->catatan_bpk ? ' Diisikan dari catatan BPK di barisnya.' : '' }}</div>
                        </label>
                        <label class="fld" style="margin-top:10px;margin-bottom:0">
                          <span class="lbl">Keterangan tambahan dari Setba — boleh dikosongkan</span>
                          <textarea rows="2" maxlength="500" name="keterangan" placeholder="Perjelas maksud catatan BPK supaya satuan kerja mudah memperbaikinya."></textarea>
                        </label>
                        <div class="fld" style="margin-top:10px;margin-bottom:0">
                          <span class="lbl">Dokumen yang diminta — boleh dikosongkan</span>
                          @include('rekomendasi.bagian.daftar-isian', ['nama' => 'dokumen', 'nilai' => [''], 'placeholder' => 'Nama dokumen', 'tambah' => 'Tambah dokumen'])
                        </div>
                        @php
                          $pastikanUlang = ['judul' => 'Kirim ulang ke '.$x->satker->namaPendek().' untuk pemberkasan ulang?',
                            'ket' => 'Tandanya berubah jadi belum memadai, berkasnya kembali ke mejanya, dan catatan BPK ini yang dibacanya bersama keterangan Setba. Catatan unggahannya dilepas, jadi sesudah diperbaiki berkasnya diunggah ulang ke SIPTL.',
                            'tombol' => 'Ya, kirim ulang', 'nada' => 'jingga'];
                        @endphp
                        <div class="bilah" style="margin-top:14px">
                          <button type="submit" class="btn btn-p" style="background:var(--bad);border-color:var(--bad)" data-kirim-ulang-bpk
                            data-pastikan='@json($pastikanUlang)'>
                            <x-ikon n="RotateCcw" :s="14" /> Kirim ulang ke satuan kerja
                          </button>
                          <button type="button" class="btn" data-tutup-sunting>Batal</button>
                          <span class="lbl" data-alasan-kosong @if($x->catatan_bpk) hidden @endif>Catatan harus terisi</span>
                        </div>
                      </form>
                    @endif
                  </td>
                </tr>
              @endif
            @endforeach
          </tbody>
        </table>
      </div>

      @if($r->nilaiRek() > 0 && $bolehKerja)
        <div class="kakinilai">
          <span><span class="lbl">Nilai rekomendasi</span><b class="mono">{{ Tampil::rupiah($r->nilaiRek()) }}</b></span>
          <span><span class="lbl">Diakui BPK</span>
            <b class="mono" style="color:{{ $r->nilaiDiakuiBpk() ? 'var(--ok)' : 'var(--ink-3)' }}">{{ $r->nilaiDiakuiBpk() ? Tampil::rupiah($r->nilaiDiakuiBpk()) : 'Rp 0' }}</b></span>
          <span><span class="lbl">Belum diakui</span>
            <b class="mono" style="color:{{ $r->sisaNilaiBpk() > 0 ? 'var(--bad)' : 'var(--ok)' }}">{{ $r->sisaNilaiBpk() > 0 ? Tampil::rupiah($r->sisaNilaiBpk()) : 'Rp 0' }}</b></span>
        </div>
      @endif
    </div>
  </div>
</div>
@endif
