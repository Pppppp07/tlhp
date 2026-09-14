<form method="post" action="{{ route('laporan.baru.temuan') }}">
  @csrf

  @foreach($d['temuan'] as $i => $t)
    <details class="temblok" @if($i === 0) open @endif style="margin-bottom:14px">
      <summary>
        <span class="no mono">{{ $i + 1 }}</span>
        <span class="isi">
          <b>{{ $t['judul'] ?: 'Temuan ' . ($i + 1) . ' — belum diberi judul' }}</b>
          <span class="lbl">
            {{ count($t['rekom']) }} rekomendasi
            @if($t['nomor_pada_surat']) &middot; butir {{ $t['nomor_pada_surat'] }} @endif
          </span>
        </span>
        @if(count($d['temuan']) > 1)
          <button class="btn btn-s" type="submit" name="aksi" value="hapus-temuan:{{ $i }}"
            title="Hapus temuan ini">Hapus</button>
        @endif
      </summary>

      <div class="isitem">
        <div class="duo">
          <label class="f">
            <span class="lbl">Nomor temuan pada surat</span>
            <input type="text" class="mono" name="temuan[{{ $i }}][nomor_pada_surat]"
              value="{{ $t['nomor_pada_surat'] }}" placeholder="3.1.2">
          </label>
          <div class="f">
            <span class="lbl">Satuan kerja terperiksa <x-info :teks="[
              'Satuan kerja tempat temuannya terjadi. Boleh lebih dari satu — satu temuan lazim mengenai beberapa satuan kerja sekaligus.',
              'Berbeda dari satuan kerja penanggung jawab pada tiap rekomendasi: yang menanggung perbaikan belum tentu yang diperiksa.',
              'Kalau dipecah jadi beberapa temuan supaya bisa menyebut semuanya, nilai temuannya ikut terhitung berkali-kali.',
            ]" /></span>
            {{-- Kotak centang, bukan pilihan tunggal. Yang dicentang tidak
                 punya urutan dan tidak ada yang "utama" — semuanya diperiksa. --}}
            <div class="pilihsatker">
              @foreach($satker as $sk)
                <label>
                  <input type="checkbox" name="temuan[{{ $i }}][satker][]" value="{{ $sk->id }}"
                    @checked(in_array((int) $sk->id, array_map('intval', (array) $t['satker']), true))>
                  <span>{{ $sk->namaPendek() }}</span>
                </label>
              @endforeach
            </div>
          </div>
        </div>

        <label class="f">
          <span class="lbl">Judul temuan</span>
          <input type="text" name="temuan[{{ $i }}][judul]" value="{{ $t['judul'] }}"
            placeholder="Kalimat pendek yang menyebut perkaranya">
        </label>

        {{-- Kondisi dan kriteria tidak dikumpulkan. Kondisi berbeda-beda tiap
             satuan kerja sehingga menumpuknya jadi satu kolom justru
             mengaburkan siapa kena apa, dan kriteria berisi terlalu banyak
             peraturan untuk diketik ulang. Keduanya tetap ada di dokumen
             LHP-nya, dan berkasnya tersimpan di laporan ini. --}}
        <div class="duo">
          <label class="f">
            <span class="lbl">Sebab</span>
            <textarea name="temuan[{{ $i }}][sebab]" style="min-height:52px">{{ $t['sebab'] }}</textarea>
          </label>
          <label class="f">
            <span class="lbl">Akibat</span>
            <textarea name="temuan[{{ $i }}][akibat]" style="min-height:52px">{{ $t['akibat'] }}</textarea>
          </label>
        </div>

        <div class="duo">
          <label class="f">
            <span class="lbl">Kategori temuan</span>
            <select name="temuan[{{ $i }}][kategori]">
              <option value="">— pilih —</option>
              @foreach($kategori as $k)
                <option value="{{ $k->id }}" @selected((string) $t['kategori'] === (string) $k->id)>{{ $k->nama }}</option>
              @endforeach
            </select>
            <span class="hint">Daftarnya mengikuti sumber laporan yang dipilih di langkah pertama.</span>
          </label>
          <label class="f">
            <span class="lbl">Kategori internal</span>
            <select name="temuan[{{ $i }}][kategori_intern]">
              <option value="">— pilih —</option>
              @foreach($intern as $k)
                <option value="{{ $k->id }}" @selected((string) $t['kategori_intern'] === (string) $k->id)>{{ $k->nama }}</option>
              @endforeach
            </select>
          </label>
          <label class="f">
            <span class="lbl">Nilai temuan</span>
            <input type="text" class="mono" inputmode="numeric" name="temuan[{{ $i }}][nilai]"
              value="{{ $t['nilai'] }}" placeholder="0">
            <span class="hint">Kosongkan bila temuannya administratif dan tidak bernilai rupiah.</span>
          </label>
        </div>

        <div class="lbl" style="margin:18px 0 9px">
          Rekomendasi &mdash; inilah yang dipantau, bukan temuannya
        </div>

        @foreach($t['rekom'] as $j => $r)
          <div class="kartu" style="background:var(--surface-2);margin-bottom:10px">
            <div style="display:flex;align-items:center;gap:9px;margin-bottom:11px">
              <span class="mono" style="font-weight:700">{{ $i + 1 }}.{{ $j + 1 }}</span>
              <div style="flex:1"></div>
              @if(count($t['rekom']) > 1)
                <button class="btn btn-s" type="submit" name="aksi" value="hapus-rekom:{{ $i }}:{{ $j }}">Hapus</button>
              @endif
            </div>

            <label class="f">
              <span class="lbl">Uraian rekomendasi</span>
              <textarea name="temuan[{{ $i }}][rekom][{{ $j }}][uraian]"
                style="min-height:52px">{{ $r['uraian'] }}</textarea>
            </label>

            {{-- Sifat rekomendasi berdiri di tingkat rekomendasi: ia sebutan
                 dari suratnya, bukan sifat salah satu bentuk tindak lanjutnya. --}}
            <label class="f">
              <span class="lbl">Sifat rekomendasi <x-info :teks="[
                'Tertulis di suratnya: administratif, atau menyangkut kerugian negara.',
                'Yang menyangkut kerugian negara menuntut penyetoran ke kas negara; yang administratif cukup dilengkapi dokumennya atau diperbaiki prosedurnya.',
              ]" /></span>
              <select name="temuan[{{ $i }}][rekom][{{ $j }}][sifat]">
                <option value="">— pilih —</option>
                @foreach($sifat as $x)
                  <option value="{{ $x->id }}" @selected((string) ($r['sifat'] ?? '') === (string) $x->id)>{{ $x->nama }}</option>
                @endforeach
              </select>
            </label>

            {{-- Satu rekomendasi bisa menuntut beberapa bentuk tindak lanjut
                 sekaligus — menyetor ke kas negara DAN membenahi prosedurnya —
                 dan tiap bentuk bisa dibebankan ke satuan kerja yang berbeda.

                 Selama formulirnya memaksa satu bentuk, yang begitu harus
                 dipecah jadi dua rekomendasi, dan uraiannya jadi ditulis dua
                 kali padahal suratnya cuma menyebut satu. --}}
            @foreach($r['tindakan'] as $u => $tk)
              <div class="kotaktindakan">
                <div class="kepalatindakan">
                  <span class="lbl" style="margin:0">
                    Bentuk tindak lanjut @if(count($r['tindakan']) > 1) {{ $u + 1 }} @endif
                  </span>
                  <div style="flex:1"></div>
                  @if(count($r['tindakan']) > 1)
                    <button class="btn btn-s" type="submit" name="aksi"
                      value="hapus-tindakan:{{ $i }}:{{ $j }}:{{ $u }}">Hapus bentuk</button>
                  @endif
                </div>

                <div class="duo">
                  <label class="f">
                    <span class="lbl">Jenis tindakan <x-info :teks="[
                      'Delapan jenis resmi menurut SOP, ditambah satu penampung untuk yang tidak masuk kelompok mana pun.',
                      'Pilihan ini menentukan dokumen bukti yang nanti diusulkan saat Setba meminta kelengkapan.',
                    ]" /></span>
                    <select name="temuan[{{ $i }}][rekom][{{ $j }}][tindakan][{{ $u }}][bentuk]">
                      <option value="">— pilih —</option>
                      @foreach($bentuk as $b)
                        <option value="{{ $b->id }}" @selected((string) $tk['bentuk'] === (string) $b->id)>{{ $b->nama }}</option>
                      @endforeach
                    </select>
                  </label>

                  {{-- Kosongkan berarti ikut tanggal rekomendasinya, yang
                       dihitung sistem dari tanggal laporan diterima berikut
                       dasar hukumnya. Diisi hanya kalau suratnya memang memberi
                       tenggat berbeda untuk bentuk ini — perbaikan fisik lazim
                       diberi waktu lebih panjang daripada yang cuma menuntut
                       surat. --}}
                  <label class="f">
                    <span class="lbl">Rencana aksi bentuk ini <x-info :teks="[
                      'Kosongkan bila mengikuti tenggat rekomendasinya — itu yang dihitung sistem dari tanggal laporan diterima, berikut dasar hukumnya.',
                      'Diisi hanya kalau suratnya memberi tenggat berbeda untuk bentuk tindak lanjut ini.',
                      'Satuan kerja yang memikul dua bentuk mengejar yang lebih dulu jatuh tempo.',
                    ]" /></span>
                    <input type="date" class="mono"
                      name="temuan[{{ $i }}][rekom][{{ $j }}][tindakan][{{ $u }}][tgl_renaksi]"
                      value="{{ $tk['tgl_renaksi'] ?? '' }}">
                  </label>
                </div>

                {{-- Satu baris satu satuan kerja, berikut bagiannya.

                     Nilainya tidak diketik sekali lalu dibagi: yang dibagi
                     harus disebut per satuan kerja, karena itulah yang
                     ditagihkan kepada masing-masing dan itulah yang mereka
                     lihat di layarnya sendiri. --}}
                <div class="f" style="margin-bottom:0">
                  @php
                    $tagihan = collect($tk['sasaran'] ?? [])
                      ->sum(fn ($x) => (int) preg_replace('/\D/', '', (string) ($x['nilai'] ?? '')));
                  @endphp
                  <span class="lbl">
                    Ditujukan ke satuan kerja
                    @if($tagihan > 0)
                      &middot; total {{ \App\Support\Tampil::rupiah($tagihan) }}
                    @endif
                    <x-info :teks="[
                      'Satu bentuk tindak lanjut bisa dibebankan ke beberapa satuan kerja, dan nominalnya dipecah antar mereka.',
                      'Berkas tiap satuan kerja lalu berjalan sendiri-sendiri: yang satu bisa sudah di Inspektorat sementara yang lain masih menyusun jawaban.',
                      'Satuan kerja yang sama boleh muncul di dua bentuk — itu memang dua kewajiban terpisah, dan keduanya harus tuntas sendiri-sendiri.',
                      'Bentuk yang tidak menuntut penyetoran uang cukup dikosongkan nilainya.',
                    ]" />
                  </span>

                  @foreach($tk['sasaran'] as $k => $x)
                    <div class="barissasaran">
                      <select name="temuan[{{ $i }}][rekom][{{ $j }}][tindakan][{{ $u }}][sasaran][{{ $k }}][satker]">
                        <option value="">— pilih satuan kerja —</option>
                        @foreach($satker as $sk)
                          <option value="{{ $sk->id }}" @selected((string) $x['satker'] === (string) $sk->id)>
                            {{ $sk->namaPendek() }}
                          </option>
                        @endforeach
                      </select>
                      <input type="text" class="mono" inputmode="numeric"
                        name="temuan[{{ $i }}][rekom][{{ $j }}][tindakan][{{ $u }}][sasaran][{{ $k }}][nilai]"
                        value="{{ $x['nilai'] }}" placeholder="0">
                      @if(count($tk['sasaran']) > 1)
                        <button class="btn btn-s" type="submit" name="aksi"
                          value="hapus-sasaran:{{ $i }}:{{ $j }}:{{ $u }}:{{ $k }}"
                          title="Buang satuan kerja ini">&times;</button>
                      @else
                        <span></span>
                      @endif
                    </div>
                  @endforeach

                  <button class="btn btn-s" type="submit" name="aksi"
                    value="tambah-sasaran:{{ $i }}:{{ $j }}:{{ $u }}">
                    + Tambah satuan kerja
                  </button>
                </div>
              </div>
            @endforeach

            <button class="btn btn-s" type="submit" name="aksi"
              value="tambah-tindakan:{{ $i }}:{{ $j }}" style="margin-bottom:12px">
              + Tambah bentuk tindak lanjut
            </button>

            <label class="f" style="margin-bottom:0">
              <span class="lbl">Catatan Setba untuk satuan kerja</span>
              <textarea name="temuan[{{ $i }}][rekom][{{ $j }}][catatan]"
                style="min-height:44px">{{ $r['catatan'] }}</textarea>
            </label>
          </div>
        @endforeach

        <button class="btn btn-s" type="submit" name="aksi" value="tambah-rekom:{{ $i }}">
          + Tambah rekomendasi pada temuan ini
        </button>
      </div>
    </details>
  @endforeach

  <button class="btn" type="submit" name="aksi" value="tambah-temuan" style="margin-bottom:18px">
    + Tambah temuan
  </button>

  <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap">
    <button class="btn" type="submit" name="ke" value="1">
      <x-ik nama="panah-kiri" ukuran="15" /> Sebelumnya
    </button>
    <button class="btn btn-p" type="submit" name="aksi" value="lanjut">
      Berikutnya: Tinjau &amp; kirim <x-ik nama="panah-kanan" ukuran="15" />
    </button>
    {{-- Kalau tombolnya mati, sebutkan isian mana yang kurang. "Lengkapi isian
         pada langkah ini" memaksa pengisi menebak-nebak sendiri. --}}
    <span class="hint">{{ $kurang2 ?: 'Sudah lengkap' }}</span>
  </div>
</form>
