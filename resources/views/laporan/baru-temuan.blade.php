@php
  use App\Support\Tampil;

  /* Langkah 2 — temuan dan rekomendasinya. Temuan ditampilkan satu per satu:
     satu laporan bisa memuat belasan temuan, dan yang ditumpuk memaksa
     pengisinya menggulir jauh hanya untuk melihat yang sedang dikerjakan. */
  $t = $d['temuan'][$iAktif];
  $jml = count($d['temuan']);
  $tahun = substr($d['surat']['tgl_surat'] ?: '', 0, 4);
  $noSurat = explode('/', $d['surat']['nomor'])[0] ?? '';
  /* Sifatnya yang menentukan apakah ada nilai yang ditagih, bukan angkanya:
     memakai angka sebagai saklar membuat pengisinya harus mengetik nilai dulu
     sebelum tahu ke mana ia dibagi — padahal yang tahu duluan justru suratnya. */
  $administratif = $sifat->firstWhere('nama', 'Administratif')?->id;
@endphp

{{-- geser antar temuan — hanya satu temuan tampil sekaligus --}}
<div class="geser">
  <button class="btn btn-s" type="submit" name="aksi" aria-label="Temuan sebelumnya"
    value="temuan:{{ $d['temuan'][$iAktif - 1]['id'] ?? '' }}" @disabled($iAktif === 0)>
    <x-ikon n="ChevronLeft" :s="14" />
  </button>
  @foreach($d['temuan'] as $i => $x)
    <button class="t" type="submit" name="aksi" value="temuan:{{ $x['id'] }}"
      aria-current="{{ $i === $iAktif ? 'true' : 'false' }}">
      <span class="dot" style="background:{{ $form->temOk($x) ? 'var(--stamp)' : 'var(--verm)' }}"></span>
      <span class="n">{{ $i + 1 }}</span>
      <span style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
        {{ $x['judul'] ?: 'belum berjudul' }}
      </span>
      <span class="lbl">{{ count($x['rekom']) }} rek</span>
    </button>
  @endforeach
  <button class="t tambah" type="submit" name="aksi" value="tambah-temuan">
    <x-ikon n="Plus" :s="13" /> Tambah temuan
  </button>
  <button class="btn btn-s" type="submit" name="aksi" aria-label="Temuan berikutnya"
    value="temuan:{{ $d['temuan'][$iAktif + 1]['id'] ?? '' }}" @disabled($iAktif >= $jml - 1)>
    <x-ikon n="ChevronRight" :s="14" />
  </button>
</div>

<input type="hidden" name="t[id]" value="{{ $t['id'] }}">

<div class="blokT">
  <div class="kepala">
    <div class="no">{{ $iAktif + 1 }}</div>
    <div style="flex:1;min-width:140px">
      <div style="font-size:13px;font-weight:600;color:var(--brass)">
        Temuan {{ $iAktif + 1 }} dari {{ $jml }}
      </div>
      <div class="lbl" style="color:var(--brass);opacity:.8">{{ count($t['rekom']) }} rekomendasi</div>
    </div>
    <span class="lbl" style="color:{{ $form->temOk($t) ? 'var(--stamp)' : 'var(--verm)' }}">
      {{ $form->temOk($t) ? 'lengkap' : 'belum lengkap' }}
    </span>
    @if($jml > 1)
      <button class="btn btn-s" type="submit" name="aksi" value="hapus-temuan:{{ $t['id'] }}">
        <x-ikon n="X" :s="12" /> Hapus
      </button>
    @endif
  </div>

  <div class="isi">
    {{-- Urutannya mengikuti cara orang membaca LHP: perkaranya dulu, baru siapa
         yang kena. Judul di paling atas karena itulah yang muncul di seluruh
         daftar dan pemberitahuan. --}}
    <div class="grup buka" data-grup>
      <button class="judul" type="button" aria-expanded="true" data-buka-grup>
        <span class="panah buka">›</span>
        <span class="nm">Temuan</span>
        @php $temIsi = trim($t['judul']) !== '' && $t['kategori'] !== '' && count($t['satker']) > 0; @endphp
        <span class="tanda" style="color:{{ $temIsi ? 'var(--ok)' : 'var(--ink-3)' }};font-weight:600">
          {{ $temIsi ? 'lengkap' : 'belum lengkap' }}
        </span>
      </button>

      <div class="isi">
        <div class="subbag awal">
          <div class="subjudul"><b>Perkaranya</b><span class="garis"></span></div>
          <label class="fld">
            <span class="lbl">Judul temuan</span>
            <input type="text" name="t[judul]" value="{{ $t['judul'] }}"
              placeholder="Satu kalimat yang menyebut perkaranya">
            <div class="hint">Judul inilah yang terbaca di daftar dan pemberitahuan.</div>
          </label>
          <div class="duo">
            <label class="fld">
              <span class="lbl">Nomor temuan pada surat</span>
              <input type="text" class="mono" name="t[nomor]" value="{{ $t['nomor'] }}" placeholder="1.1">
              <div class="hint">Disalin apa adanya dari dokumennya.</div>
            </label>
            <label class="fld" style="margin-bottom:0">
              <span class="lbl">Kategori temuan</span>
              <select name="t[kategori]">
                <option value="">— pilih —</option>
                @foreach($kategori->where('sumber.value', $d['surat']['sumber']) as $k)
                  <option value="{{ $k->id }}" @selected((string) $t['kategori'] === (string) $k->id)>{{ $k->nama }}</option>
                @endforeach
                {{-- Yang sudah dipilih tetap tampil walau sudah dinonaktifkan. --}}
                @php $dipilih = $kategori->firstWhere('id', (int) $t['kategori']); @endphp
                @if($t['kategori'] && ! $dipilih)
                  @php $lama = \App\Models\KategoriTemuan::find($t['kategori']); @endphp
                  @if($lama)<option value="{{ $lama->id }}" selected>{{ $lama->nama }}</option>@endif
                @endif
              </select>
              <div class="hint">Mengikuti sumber laporan di langkah pertama.</div>
            </label>
          </div>
        </div>

        {{-- Barisnya sendiri selebar penuh: dijejalkan ke satu kolom bersama dua
             isian kecil, sepuluh kepingnya membungkus jadi empat baris dan
             mendorong isian di sebelahnya turun sendiri. --}}
        <div class="subbag">
          <div class="subjudul">
            <b>Satuan kerja terperiksa</b>
            <span class="ket">{{ count($t['satker']) ? count($t['satker']).' dipilih' : 'belum dipilih' }}</span>
            <span class="garis"></span>
          </div>
          <div class="fld" style="margin-bottom:0">
            {{-- Dicentang langsung terkirim: daftar inilah yang membatasi
                 pilihan pada tiap tindak lanjut di bawahnya. --}}
            <x-pilih-satker :satker="$satker" :terpilih="$t['satker']" nama="t[satker][]"
              :cari="true" :kirim="true" />
            <div class="hint">
              Boleh lebih dari satu. Daftar ini yang nanti membatasi pilihan pada tiap
              rekomendasi &mdash; satuan kerja yang tidak diperiksa tidak bisa dituntut
              menindaklanjuti.
            </div>
          </div>
        </div>

        {{-- Sebab dan akibat menyusul tanpa pemisah: ketiganya menerangkan satu
             temuan yang sama. --}}
        <div class="subbag">
          <div class="subjudul"><b>Sebab dan akibat</b><span class="garis"></span></div>
          <div class="duo">
            <label class="fld">
              <span class="lbl">Sebab</span>
              <textarea name="t[sebab]" placeholder="Mengapa hal itu bisa terjadi">{{ $t['sebab'] }}</textarea>
              <div class="hint">Cukup yang menyangkut satuan kerja sendiri.</div>
            </label>
            <label class="fld" style="margin-bottom:0">
              <span class="lbl">Akibat</span>
              <textarea name="t[akibat]" placeholder="Kerugian atau risiko yang ditimbulkan">{{ $t['akibat'] }}</textarea>
            </label>
          </div>
        </div>

        <div class="subbag">
          <div class="subjudul"><b>Penggolongan</b><span class="garis"></span></div>
          <label class="fld" style="margin-bottom:0">
            <span class="lbl">Kategori internal</span>
            <select name="t[intern]">
              @foreach($intern->where('aktif', true) as $k)
                <option value="{{ $k->id }}" @selected((string) $t['intern'] === (string) $k->id)>{{ $k->nama }}</option>
              @endforeach
              {{-- Kategori lama yang sudah dinonaktifkan tetap boleh
                   dipertahankan pada temuan yang memang memakainya. --}}
              @php $inAktif = $intern->firstWhere('id', (int) $t['intern']); @endphp
              @if($inAktif && ! $inAktif->aktif)
                <option value="{{ $inAktif->id }}" selected>{{ $inAktif->nama }}</option>
              @endif
            </select>
            <div class="hint">Diatur di menu Data master.</div>
          </label>
        </div>
      </div>
    </div>

    <div class="grup buka" data-grup>
      <button class="judul" type="button" aria-expanded="true" data-buka-grup>
        <span class="panah buka">›</span>
        <x-ikon n="CircleDot" :s="16" />
        <span class="nm">Rekomendasi</span>
        <span class="tanda">{{ count($t['rekom']) }}</span>
      </button>

      <div class="isi">
        {{-- Yang paling baru terbuka, yang lama menutup sendiri — satu temuan
             bisa punya banyak rekomendasi dan tiap satunya belasan isian. --}}
        @foreach($t['rekom'] as $j => $r)
          @php
            $buka = $j === count($t['rekom']) - 1;
            $nama = "t[rekom][{$r['id']}]";
            $nilai = $form->nilaiRek($r);
            $uang = (string) $r['sifat'] !== (string) $administratif;
            $refLhp = trim($r['ref_lhp']);
          @endphp
          <div @class(['blokR', 'buka' => $buka]) data-blok-rek>
            <div class="kepala" role="button" tabindex="0" aria-expanded="{{ $buka ? 'true' : 'false' }}" data-buka-rek>
              <span class="panah{{ $buka ? ' buka' : '' }}">›</span>
              <span class="no">Rek {{ $iAktif + 1 }}.{{ $j + 1 }}</span>
              <span class="ringkas" @if($buka) hidden @endif>{{ trim($r['uraian']) ?: ($r['tindakan'][0]['bentuk'] ?? '') }}</span>
              <span style="flex:1"></span>
              <span style="color:{{ $form->rekOk($r) ? 'var(--ok)' : 'var(--bad)' }}">
                {{ $form->rekOk($r) ? 'lengkap' : 'belum lengkap' }}
              </span>
              @if(count($t['rekom']) > 1)
                <button class="lbl" style="color:var(--bad)" type="submit" name="aksi"
                  value="hapus-rek:{{ $t['id'] }}:{{ $r['id'] }}">Hapus</button>
              @endif
            </div>

            <div class="isi" @if(! $buka) hidden @endif>
              {{-- Tiga pertanyaan berurutan: apa isi rekomendasinya, tindak
                   lanjut apa saja yang diminta dan dari siapa, lalu kapan
                   beserta buktinya. --}}
              <div class="subbag awal">
                <div class="subjudul"><b>Isi rekomendasi</b><span class="garis"></span></div>
                <label class="fld">
                  <span class="lbl">Uraian rekomendasi</span>
                  <textarea name="{{ $nama }}[uraian]" style="min-height:52px"
                    placeholder="Apa yang harus dilakukan satuan kerja">{{ $r['uraian'] }}</textarea>
                </label>
                {{-- Disalin dari suratnya, tidak dirakit sendiri. Bentuknya
                     berbeda tiap tahun LHP, jadi merakitnya dari nomor temuan
                     menghasilkan kode yang tidak cocok dengan surat aslinya. --}}
                <label class="fld" style="margin-bottom:0">
                  <span class="lbl">
                    Ref LHP
                    <x-info :teks="[
                      'Salin persis seperti tertulis di laporan pemeriksaannya.',
                      'Bentuknya berbeda antar tahun: 1.7, 10.a, 22.B.12, I.1.1.a, II.4.4.f.',
                      'Ref IDT dirakit sendiri dari tahun LHP, nomor suratnya, dan kode ini.',
                    ]" />
                  </span>
                  <input type="text" class="mono" name="{{ $nama }}[ref_lhp]" value="{{ $r['ref_lhp'] }}"
                    placeholder="II.4.4.f">
                  <div class="hint">
                    {{ $tahun && $noSurat && $refLhp
                      ? "Ref IDT: {$tahun}.{$noSurat}.{$refLhp}"
                      : 'Kosongkan bila nomor temuan dan huruf rekomendasi sudah cukup.' }}
                  </div>
                </label>
                <div class="duo">
                  <label class="fld" style="margin-bottom:0">
                    <span class="lbl">Sifat rekomendasi</span>
                    <select name="{{ $nama }}[sifat]" data-sifat data-administratif="{{ $administratif }}">
                      @foreach($sifat as $x)
                        <option value="{{ $x->id }}" @selected((string) $r['sifat'] === (string) $x->id)>{{ $x->nama }}</option>
                      @endforeach
                    </select>
                    <div class="hint">Memisahkan yang menuntut uang dari yang menuntut perbaikan.</div>
                  </label>
                  {{-- Dihitung, bukan diketik: angkanya jumlah bagian tiap
                       satuan kerja di bawah. Yang dihitung tidak bisa
                       berselisih dengan jumlah bagiannya. --}}
                  <div class="fld" style="margin-bottom:0">
                    <span class="lbl">Nilai yang harus dipulihkan <x-info :teks="[
                      'Dijumlah dari bagian tiap satuan kerja pada tindak lanjut di bawah.',
                      'Tidak diketik: nominalnya memang ditagihkan per satuan kerja, dan satu angka gabungan di atas cepat atau lambat berselisih dengan jumlah bagiannya.',
                      'Rekomendasi yang tidak menuntut penyetoran cukup diberi sifat Administratif.',
                    ]" /></span>
                    <div class="nilaihitung mono">{{ $nilai > 0 ? Tampil::rupiah($nilai) : '—' }}</div>
                    <div class="hint">
                      {{ $uang
                        ? ($nilai > 0 ? 'Dijumlah dari bagian tiap satuan kerja.' : 'Terisi begitu bagian tiap satuan kerja diisi.')
                        : 'Sifatnya administratif — tidak menuntut penyetoran.' }}
                    </div>
                  </div>
                </div>
              </div>

              <div class="subbag">
                <div class="subjudul">
                  <b>Tindak lanjut yang diminta</b>
                  <span class="ket">{{ count($r['tindakan']) }} tindakan · {{ $form->barisRek($r) }} penugasan</span>
                  <span class="garis"></span>
                </div>

                @foreach($r['tindakan'] as $k => $tk)
                  @php
                    $namaTk = "{$nama}[tindakan][{$tk['id']}]";
                    $dipilihTk = collect($tk['satker'])->pluck('satker')->all();
                    $nilaiTk = collect($tk['satker'])->pluck('nilai', 'satker');
                  @endphp
                  <div class="blokTindak" data-tindak>
                    <div class="kepalaTindak">
                      <span class="no mono">{{ $k + 1 }}</span>
                      {{-- Boleh diketik sendiri. Daftarnya cuma usulan — yang
                           tercatat harus sama dengan yang tertulis di suratnya,
                           dan bunyi surat tidak terbatas pada pilihan yang ada. --}}
                      <input type="text" list="bentuk-tl" name="{{ $namaTk }}[bentuk]" value="{{ $tk['bentuk'] }}"
                        placeholder="Bentuk tindak lanjut yang diminta">
                      @if(count($r['tindakan']) > 1)
                        <button class="lbl" style="color:var(--bad)" type="submit" name="aksi"
                          value="hapus-tindakan:{{ $t['id'] }}:{{ $r['id'] }}:{{ $k }}">Hapus</button>
                      @endif
                    </div>

                    {{-- Satuan kerjanya dipilih di dalam tindakannya sendiri,
                         dan hanya dari yang terperiksa pada temuan ini. --}}
                    <x-pilih-satker :satker="$satker" :terpilih="$dipilihTk" :dari="$t['satker']"
                      nama="{{ $namaTk }}[satker][]"
                      kosong="Pilih dulu satuan kerja terperiksa pada temuannya." />

                    <div class="nilaiTindak" data-nilai-tindak @if(! $uang || ! $dipilihTk) hidden @endif>
                      @foreach($satker as $sk)
                        <div class="barisTindak" data-nilai-satker="{{ $sk->id }}"
                          @if(! in_array($sk->id, $dipilihTk, true)) hidden @endif>
                          <span class="nm">{{ $sk->namaPendek() }}</span>
                          <input type="text" class="mono" name="{{ $namaTk }}[nilai][{{ $sk->id }}]"
                            value="{{ $nilaiTk[$sk->id] ?? '' }}" placeholder="tidak dibebani">
                        </div>
                      @endforeach
                    </div>

                    {{-- Bukti yang diminta melekat pada tindak lanjutnya, bukan
                         pada rekomendasinya: satuan kerja yang cuma diminta
                         membenahi prosedur tidak akan pernah melihat baris Bukti
                         Setor. Ditetapkan di muka, bukan sesudah satuan kerja
                         menjawab — SOP menempatkan penetapan bukti pada tahap
                         Rencana Aksi. --}}
                    <div class="dokTindak">
                      <span class="lbl">
                        Dokumen yang diminta
                        <span style="color:var(--ink-3);font-weight:400">
                          · {{ collect($tk['dokumen'])->filter(fn ($x) => trim($x) !== '')->count() }} butir
                        </span>
                      </span>
                      @foreach($tk['dokumen'] as $j2 => $dok)
                        <div style="display:flex;gap:8px;margin-bottom:7px">
                          <input type="text" name="{{ $namaTk }}[dokumen][]" value="{{ $dok }}" placeholder="Nama dokumen">
                          <button class="btn btn-s" type="submit" aria-label="Hapus dokumen" name="aksi"
                            value="hapus-dok:{{ $t['id'] }}:{{ $r['id'] }}:{{ $k }}:{{ $j2 }}">
                            <x-ikon n="X" :s="12" />
                          </button>
                        </div>
                      @endforeach
                      <button class="btn btn-s" type="submit" name="aksi"
                        value="tambah-dok:{{ $t['id'] }}:{{ $r['id'] }}:{{ $k }}">
                        <x-ikon n="Plus" :s="13" /> Tambah dokumen
                      </button>
                    </div>

                    {{-- Tiap tindak lanjut punya rencana aksinya sendiri.
                         Menyusun SOP tentu lebih lama daripada menerbitkan surat
                         teguran; satu tanggal untuk semuanya memaksa Setba
                         memilih yang paling longgar, dan yang cepat jadi tidak
                         pernah tertagih. --}}
                    <div class="renaksi">
                      <span class="lbl">Rencana aksi tindak lanjut ini</span>
                      <div class="duo" style="margin-top:8px">
                        <label class="fld">
                          <span class="lbl">Tanggal rencana aksi <x-info teks="Surat BPK sendiri tidak bertenggat. Tanggal ini rencana yang disepakati, bukan batas yang mengunci." /></span>
                          <input type="date" name="{{ $namaTk }}[tgl_renaksi]" value="{{ $tk['tgl_renaksi'] }}">
                        </label>
                        <label class="fld">
                          <span class="lbl">Target penyelesaian</span>
                          <input type="date" name="{{ $namaTk }}[target]" value="{{ $tk['target'] }}">
                          <div class="hint">Boleh jauh &mdash; sebagian perkara memang lintas tahun.</div>
                        </label>
                      </div>
                      <label class="fld" style="margin-bottom:0">
                        <span class="lbl">Catatan untuk satuan kerja</span>
                        <textarea name="{{ $namaTk }}[catatan]" style="min-height:44px"
                          placeholder="Bukti apa yang diharapkan">{{ $tk['catatan'] }}</textarea>
                      </label>
                    </div>
                  </div>
                @endforeach

                <button class="btn btn-s" type="submit" style="border-style:dashed" name="aksi"
                  value="tambah-tindakan:{{ $t['id'] }}:{{ $r['id'] }}">
                  <x-ikon n="Plus" :s="13" /> Tambah tindak lanjut
                </button>

                {{-- Rencana angsuran hanya berlaku kalau ada nilai yang ditagih.
                     Nilainya jumlah bagian tiap satuan kerja — jadi isiannya
                     baru muncul sesudah bagiannya diisi, bukan sebelum. --}}
                @if($nilai > 0)
                  <div class="duo">
                    <label class="fld">
                      <span class="lbl">Rencana angsuran</span>
                      <div class="cacah">
                        <input type="text" class="mono" name="{{ $nama }}[angsur]" value="{{ $r['angsur'] }}"
                          placeholder="kosongkan bila sekaligus">
                        {{-- Diketik boleh, dinaik-turunkan juga boleh. Angka ini
                             hampir selalu kecil dan sering dicoba-coba dulu. --}}
                        <span class="tombol">
                          <button type="submit" aria-label="Tambah satu angsuran" name="aksi"
                            value="angsur:naik:{{ $t['id'] }}:{{ $r['id'] }}" @disabled((int) $r['angsur'] >= 24)>
                            <x-ikon n="ChevronUp" :s="13" />
                          </button>
                          <button type="submit" aria-label="Kurangi satu angsuran" name="aksi"
                            value="angsur:turun:{{ $t['id'] }}:{{ $r['id'] }}" @disabled(! (int) $r['angsur'])>
                            <x-ikon n="ChevronDown" :s="13" />
                          </button>
                        </span>
                      </div>
                      <div class="hint">
                        {{ (int) $r['angsur'] > 1
                          ? (int) $r['angsur'].' kali · sekitar '.Tampil::rupiah((int) round($nilai / (int) $r['angsur'])).' per angsuran'
                          : ' ' }}
                      </div>
                    </label>
                    <div class="fld">
                      <span class="lbl">Batas angsuran</span>
                      <label style="display:flex;gap:9px;align-items:flex-start;font-size:13px;padding-top:11px">
                        <input type="checkbox" style="margin-top:3px" name="{{ $nama }}[kunci]" value="1"
                          @checked($r['kunci'] && (int) $r['angsur']) @disabled(! (int) $r['angsur'])>
                        <span>Kunci pada jumlah rencana</span>
                        <x-info :teks="! (int) $r['angsur']
                          ? 'Isi jumlah angsurannya dulu.'
                          : ($r['kunci']
                            ? 'Satuan kerja tidak bisa menyetor lebih banyak dari jumlah yang direncanakan.'
                            : 'Satuan kerja boleh menyetor lebih atau kurang dari rencana.')" />
                      </label>
                    </div>
                  </div>
                @endif
              </div>
            </div>
          </div>
        @endforeach

        <button class="btn" type="submit" style="width:100%;justify-content:center;border-style:dashed"
          name="aksi" value="tambah-rek:{{ $t['id'] }}">
          <x-ikon n="Plus" :s="14" /> Tambah rekomendasi untuk temuan {{ $iAktif + 1 }}
        </button>

        {{-- Nilai temuan berdiri DI BAWAH rekomendasinya, dan tidak diketik:
             angkanya memang lahir dari sini — jumlah yang dituntut dipulihkan
             pada rekomendasi di atasnya. --}}
        <div class="totalnilai">
          <span class="lbl">Nilai temuan</span>
          <b class="mono">{{ Tampil::rupiah($form->nilaiTem($t)) }}</b>
          <x-info :teks="[
            'Dihitung dari jumlah nilai yang harus dipulihkan pada seluruh rekomendasi temuan ini.',
            'Tidak diketik: dua angka untuk satu hal cepat atau lambat berbeda, dan yang menemukannya biasanya pemeriksa.',
            'Temuan yang tidak menuntut pemulihan uang bernilai nol — itu wajar, dan tidak menahan pengiriman.',
          ]" />
        </div>
      </div>
    </div>
  </div>
</div>

<datalist id="bentuk-tl">
  @foreach($bentuk as $b)<option value="{{ $b }}"></option>@endforeach
</datalist>

{{-- navigasi bawah — supaya tidak perlu menggulir kembali ke atas. Hanya muncul
     kalau memang ada tetangga untuk dituju. --}}
<div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
  @if($iAktif > 0)
    <button class="btn" type="submit" name="aksi" value="temuan:{{ $d['temuan'][$iAktif - 1]['id'] }}">
      <x-ikon n="ChevronLeft" :s="14" /> Temuan {{ $iAktif }}
    </button>
  @endif
  @if($iAktif < $jml - 1)
    <button class="btn" type="submit" name="aksi" value="temuan:{{ $d['temuan'][$iAktif + 1]['id'] }}">
      Temuan {{ $iAktif + 2 }} <x-ikon n="ChevronRight" :s="14" />
    </button>
  @endif
  <div style="flex:1"></div>
  <button class="btn" type="submit" style="border-style:dashed" name="aksi" value="tambah-temuan">
    <x-ikon n="Plus" :s="14" /> Tambah temuan
  </button>
</div>

<div class="card" style="display:flex;gap:22px;flex-wrap:wrap;align-items:center">
  @foreach([['temuan', $ringkas['tem']], ['rekomendasi', $ringkas['rek']],
            ['satuan kerja dituju', $ringkas['sat']], ['tenggat berbeda', $ringkas['ten']]] as [$l, $v])
    <div>
      <div class="mono" style="font-size:19px;font-weight:600">{{ $v }}</div>
      <div class="lbl">{{ $l }}</div>
    </div>
  @endforeach
  <div style="flex:1"></div>
  <div class="lbl" style="max-width:220px;line-height:1.5">
    Angka ini yang nanti terbaca di halaman ringkasan
  </div>
</div>
