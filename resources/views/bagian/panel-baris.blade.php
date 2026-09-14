@php
  use App\Enums\PeranPengguna;
  use App\Enums\PosisiBerkas;
  use App\Support\Tampil;

  /* Yang bisa dikerjakan pada SATU baris satuan kerja, di dalam barisnya
     sendiri.

     Dulu seluruh panel berdiri di bawah tabel. Isinya sudah per baris, tapi
     tempatnya membuat pengisinya mencocokkan sendiri panel mana milik baris
     mana — padahal barisnya sudah menyebutkan namanya persis di atas. Satu
     rekomendasi bisa dipikul tiga satuan kerja, dan tiga panel berturut-turut
     di bawah tabel terbaca seperti tiga formulir yang tidak berhubungan.

     Butuh $s (Sasaran) dan $r (Rekomendasi). */
  $u = auth()->user();
  $peran = $u->peran;
  $setba = $peran === PeranPengguna::SETBA;
  $sumber = $r->temuan->laporan->sumber;

  /* Berkas yang rekomendasinya sudah diputus lewat CHV terkunci sama sekali —
     isian itu sudah jadi dasar surat resmi bernomor. */
  $terkunci = $r->terkunciOlehSurat();

  /* Baris lain pada rekomendasi yang sama — dipakai menyebut siapa lagi yang
     masih ditunggu sesudah baris ini diteruskan. */
  $semuaBaris = $r->daftarSasaran();

  $dok = $s->progresDokumen();
  $sisaDok = $dok ? $dok[1] - $dok[0] : 0;
  $bentuk = $s->tindakan?->bentuk?->nama;
  $namaSatker = $s->satker?->namaPendek() ?? 'satuan kerja';
@endphp

@if($terkunci)
  <div class="pesan info" style="margin:0">
    Rekomendasi ini sudah diputus lewat surat verifikasi bernomor. Berkasnya
    terkunci &mdash; isian dan lampirannya tidak bisa diubah, ditarik, atau
    dihapus siapa pun.
  </div>
@else
  {{-- ---- satuan kerja mengisi tindak lanjutnya ---- --}}
  @if($peran === PeranPengguna::SATKER)
  <div class="kartu" style="margin-bottom:16px;border-color:var(--brass)">
    <div class="lbl" style="margin-bottom:4px">Isi tindak lanjut &mdash; {{ $bentuk ?? 'bagian Anda' }}</div>
    <p style="margin:0 0 14px;font-size:12.5px;color:var(--ink-3)">
      Yang Anda isi di sini hanya menyangkut bagian {{ $namaSatker }}. Tanggapan bisa dikirim
      bertahap; setiap penyimpanan menambah baris ke rekam jejak, tidak menimpa yang sebelumnya.
    </p>

    <form method="post" action="{{ route('tanggapan.simpan', $s) }}">
      @csrf

      {{-- Satu wadah, satu daftar ke bawah. Uraian di paling atas: itu yang
           ditulis lebih dulu, dan yang lain menjelaskannya. --}}
      {{-- Uraian di paling atas. Kalimat inilah yang dibaca Setba lebih dulu,
           sebelum ia membuka berkasnya — jadi ia juga yang ditulis lebih
           dulu. --}}
      <label class="f">
        <span class="lbl">Apa yang sudah dikerjakan</span>
        <textarea name="uraian" required minlength="6"
          placeholder="Jelaskan tindakan yang sudah diambil pada tahap ini.">{{ old('uraian') }}</textarea>
        <div class="hint">
          Kalimat ini yang dibaca Setba lebih dulu, sebelum membuka berkasnya.
        </div>
      </label>

      {{-- Buktinya berupa TAUTAN ke arsip masing-masing, bukan salinan yang
           diunggah ke sini. Rapat 30 Agustus: berkasnya sudah tersimpan di
           arsip satuan kerja, dan menyalinnya ke sistem ini berarti dua tempat
           menyimpan satu berkas — lalu keduanya bisa berbeda tanpa ada yang
           tahu mana yang benar.

           Tidak ada centang terpisah. Butir dianggap terpenuhi begitu
           tautannya lengkap — centang manual membuat satuan kerja bisa
           menyatakan dokumen terkirim padahal tidak ada apa-apa yang
           dilampirkan, dan yang memeriksanya berikutnya baru tahu sesudah
           membuka berkasnya. --}}
      @php
        $butirBelum = $s->permintaanDokumen->flatMap->item->where('terpenuhi', false)->values();
      @endphp

      @php $semuaButir = $s->permintaanDokumen->flatMap->item; @endphp

      <div class="f">
        <div class="kepalaisi">
          <span class="lbl" style="margin:0">
            Dokumen yang diminta
            <x-info :teks="[
              'Buktinya berupa tautan ke arsip satuan kerja sendiri, bukan salinan yang diunggah ke sini.',
              'Judulnya Anda yang menulis — supaya yang membacanya tahu isi tautannya apa tanpa harus membukanya satu per satu.',
              'Butir dianggap terpenuhi begitu judul dan tautannya terisi. Tidak ada centang terpisah yang bisa berbeda dari kenyataan berkasnya.',
            ]" />
          </span>
          @if($semuaButir->isNotEmpty())
            <span class="tanda">{{ $sisaDok }} dari {{ $semuaButir->count() }} belum ditautkan</span>
          @endif
        </div>

        @if($semuaButir->isNotEmpty())
          <ul class="ceklis">
            @foreach($semuaButir as $it)
              <li>
                @if($it->terpenuhi)
                  {{-- Yang sudah bertautan tinggal dibaca. Menyunting tautan
                       yang sudah terkirim bukan lewat sini — itu permintaan
                       perubahan, dan yang memutus bukan pengirimnya. --}}
                  <span class="tik ada">&check;</span>
                  <span class="isi"><span class="sudah">{{ $it->nama }}</span></span>
                @else
                  <span class="tik">&cir;</span>
                  <span class="isi">
                    {{-- Isiannya di balik tombol, bukan berdiri permanen: empat
                         nama butir dengan lingkaran terbaca sekilas sebagai
                         daftar yang harus dikerjakan, sementara delapan kotak
                         isian kosong terbaca sebagai formulir panjang yang
                         harus diisi semuanya sekarang — padahal satuan kerja
                         memang mengirimnya bertahap.

                         `<details>`, bukan tombol berskrip: tanpa JavaScript
                         pun isiannya tetap bisa dibuka. --}}
                    <details class="tautanbutir">
                      <summary>
                        <span class="namabutir">{{ $it->nama }}</span>
                        <span class="btn btn-s">Tambah tautan</span>
                      </summary>
                      <div class="duo">
                        <label class="f">
                          <span class="lbl">Judul berkas</span>
                          <input type="text" name="bukti[{{ $it->id }}][judul]"
                            value="{{ old('bukti.'.$it->id.'.judul') }}"
                            placeholder="{{ $it->nama }}">
                        </label>
                        <label class="f">
                          <span class="lbl">Tautan berkas</span>
                          <input type="url" class="mono" name="bukti[{{ $it->id }}][tautan]"
                            value="{{ old('bukti.'.$it->id.'.tautan') }}"
                            placeholder="https://…">
                        </label>
                      </div>
                    </details>
                  </span>
                @endif
              </li>
            @endforeach
          </ul>
        @endif

        {{-- Bukti yang tidak menjawab butir tertentu. Satuan kerja kerap punya
             lampiran pendukung yang memang tidak diminta namanya. --}}
        <details class="tautanbutir lepas">
          <summary>
            <span class="btn btn-s">
              + {{ $semuaButir->isNotEmpty() ? 'Tambah tautan lain' : 'Tambah tautan berkas' }}
            </span>
          </summary>
          <div class="duo">
            <label class="f">
              <span class="lbl">Judul berkas</span>
              <input type="text" name="lain[judul]" value="{{ old('lain.judul') }}"
                placeholder="Contoh: Bukti setor dan Nota Konfirmasi KPPN">
            </label>
            <label class="f">
              <span class="lbl">Tautan berkas</span>
              <input type="url" class="mono" name="lain[tautan]"
                value="{{ old('lain.tautan') }}" placeholder="https://…">
            </label>
          </div>
        </details>
      </div>

      @if($s->nilai)
        <div class="f">
          <span class="lbl">Pemulihan nilai &mdash; bagian {{ $namaSatker }}</span>
          <div class="prog" style="margin-bottom:6px">
            <span class="bar dana"><i style="width:{{ min(100, round($s->nilaiTerpulihkan() / max(1, $s->nilai) * 100)) }}%"></i></span>
            <b>{{ Tampil::rupiah($s->nilaiTerpulihkan()) }}</b>
            <span>dari {{ Tampil::rupiah($s->nilai) }}</span>
          </div>
          <div class="hint" style="margin-bottom:10px">
            Sisa {{ Tampil::rupiahSisa($s->sisaPemulihan()) }}. Boleh diangsur &mdash; tiap angsuran
            satu baris tersendiri, dan sisanya dihitung sistem.
          </div>

          <div class="kartu" style="background:var(--rule-2)">
            {{-- Baris pertama: cara, nilai, tanggal. Baris kedua: nomor
                 buktinya. Yang dibaca berurutan tidak boleh berselang-seling
                 dengan yang dicocokkan. --}}
            <div class="trio">
              <label class="f">
                <span class="lbl">Cara pemulihan</span>
                <select name="pemulihan[0][jenis]">
                  <option value="setor">Setoran ke kas negara</option>
                  <option value="perbaikan">Perbaikan fisik atau pengembalian barang</option>
                </select>
              </label>
              <label class="f">
                <span class="lbl">Nilai yang dipulihkan</span>
                <input type="text" name="pemulihan[0][nilai]" placeholder="0">
              </label>
              <label class="f">
                <span class="lbl">Tanggal setor</span>
                <input type="date" name="pemulihan[0][tanggal]">
              </label>
            </div>
            <div class="trio">
              <label class="f">
                <span class="lbl">Nomor SSBP</span>
                <input type="text" name="pemulihan[0][no_ssbp]" placeholder="SSBP/2026/00/00000">
              </label>
              <label class="f">
                <span class="lbl">NTPN</span>
                <input type="text" name="pemulihan[0][ntpn]" maxlength="16" placeholder="16 karakter">
                <div class="hint">wajib untuk setoran tunai</div>
              </label>
              <label class="f">
                <span class="lbl">Nota Konfirmasi KPPN</span>
                <input type="text" name="pemulihan[0][no_nota_kppn]" placeholder="NK-000/KPPN-000/2026">
              </label>
            </div>
            <label class="f" style="max-width:320px">
              <span class="lbl">Nomor berita acara</span>
              <input type="text" name="pemulihan[0][no_berita_acara]" placeholder="BA-000/PPK/2026">
              <div class="hint">wajib untuk perbaikan tanpa uang masuk</div>
            </label>
            <div class="hint">Baris yang buktinya belum lengkap tidak akan ikut tersimpan.</div>
          </div>
        </div>
      @endif

      <div style="border-top:1px solid var(--rule);padding-top:14px;margin-top:6px">
        <div class="lbl" style="margin-bottom:9px">Keadaan bagian Anda</div>
        <ul style="list-style:none;margin:0 0 14px;padding:0;font-size:13px">
          <li style="padding:5px 0">
            <span style="color:@if($s->dokumenLengkap())var(--stamp)@else var(--verm)@endif;font-weight:700">
              {{ $s->dokumenLengkap() ? '✓' : '○' }}
            </span>
            Seluruh dokumen yang diminta sudah bertautan
            @if($sisaDok)<span class="lbl"> &mdash; {{ $sisaDok }} masih kurang</span>@endif
          </li>
          <li style="padding:5px 0">
            {{-- Kelunasan adalah TANDA, bukan penguncian. Pemulihan dana bisa
                 memakan bertahun-tahun; menahan berkasnya sampai lunas berarti
                 tidak ada yang bisa memeriksa kemajuannya selama itu. --}}
            <span style="color:@if($s->lunas())var(--stamp)@else var(--brass)@endif;font-weight:700">
              {{ $s->lunas() ? '✓' : '·' }}
            </span>
            Nilai yang harus dipulihkan
            @if(! $s->lunas())
              <span class="lbl"> &mdash; sisa {{ Tampil::rupiah($s->sisaPemulihan()) }}, boleh dikirim dulu</span>
            @endif
          </li>
        </ul>

        <div style="display:flex;gap:9px;flex-wrap:wrap">
          <button class="btn" type="submit" name="kirim" value="0">Simpan pembaruan</button>
          <button class="btn btn-p" type="submit" name="kirim" value="1">Kirim ke Setba</button>
        </div>
        <div class="hint" style="margin-top:9px;line-height:1.6">
          Menyimpan tidak memindahkan berkas &mdash; Setba tetap mendapat pemberitahuan kemajuannya.
          Kirim tertahan hanya kalau masih ada dokumen yang diminta dan belum bertautan.
          Sisa nilai yang belum disetor TIDAK menahan pengiriman: pemulihan bisa memakan
          bertahun-tahun, dan menahannya sampai lunas berarti tidak ada yang bisa memeriksa
          kemajuannya selama itu.
        </div>
      </div>
    </form>
  </div>
  @endif

  {{-- ---- Setba meneruskan ---- --}}
  @if($peran === PeranPengguna::SETBA)
  <div class="kartu" style="margin-bottom:16px;border-color:var(--brass)">
    <div class="lbl" style="margin-bottom:10px">
      Tindakan Setba &mdash; berkas {{ $namaSatker }}
    </div>

    <form method="post" action="{{ route('sasaran.teruskan', $s) }}" style="margin-bottom:14px">
      @csrf
      {{-- Nomor surat pengantarnya ikut dicatat. Nomor itu yang dipakai
           menelusuri berkas di luar sistem; tanpanya jejak kertasnya hilang. --}}
      <div class="trio">
        <label class="f">
          <span class="lbl">Nomor surat pengantar</span>
          <input type="text" name="nomor" placeholder="kosongkan bila belum ada">
        </label>
        <label class="f">
          <span class="lbl">Tanggal surat</span>
          <input type="date" name="tanggal" value="{{ now()->toDateString() }}">
        </label>
        <label class="f">
          <span class="lbl">&nbsp;</span>
          <button class="btn btn-p" type="submit">
            Teruskan ke {{ $s->posisi === PosisiBerkas::SETBA_TINJAU ? 'UKI' : 'Inspektorat' }}
          </button>
        </label>
      </div>
    </form>

    {{-- Setba TIDAK punya "Kembalikan ke satuan kerja". Kata Mas Naufal di
         rapat, "Kita nggak punya hak untuk menolak"; kata Mbak Puspi, "untuk
         ngecek kebenaran dokumennya bukan di kita. Kita cuma ada apa nggak."

         Jadi yang boleh cuma meminta yang memang belum ada — dan itu memang
         memundurkan berkasnya, tapi karena kurang, bukan karena dinilai salah.
         Menilai benar-tidaknya isi dokumen wewenang UKI. --}}
    @include('bagian.minta-dokumen', ['s' => $s, 'bentuk' => $bentuk])
  </div>
  @endif

  {{-- ---- UKI menelaah / Inspektorat memverifikasi ---- --}}
  @if(in_array($peran, [PeranPengguna::UKI, PeranPengguna::INSPEKTORAT], true))
  <div class="kartu" style="margin-bottom:16px;border-color:var(--brass)">
    <div class="lbl" style="margin-bottom:4px">
      {{ $peran === PeranPengguna::UKI ? 'Telaah UKI' : 'Verifikasi Inspektorat' }}
      &mdash; berkas {{ $namaSatker }}
    </div>
@php
      $lain = $semuaBaris->reject(fn ($x) => $x->id === $s->id);
      $lainBelum = $lain->reject(fn ($x) => $x->hasil?->memadai());
    @endphp

    <p style="margin:0 0 14px;font-size:12.5px;color:var(--ink-3)">
      @if($peran === PeranPengguna::UKI)
        Yang ditelaah adalah berkas {{ $namaSatker }}, bukan rekomendasinya seutuhnya.
        Hasilnya menandai baris ini saja &mdash; status BPK tidak tersentuh.
      @else
        Tandai berkas {{ $namaSatker }} memadai atau belum, lalu terbitkan surat CHV-nya.
        Suratnya memutus <b>seluruh rekomendasi</b>: ia berbunyi memadai hanya kalau
        semua satuan kerjanya sudah ditandai memadai.
      @endif
    </p>

    @if($peran !== PeranPengguna::UKI && $lainBelum->isNotEmpty())
      {{-- Kata Pak Iwan: "kalau di saat 3 satker itu belum beres, dia dianggap
           belum memadai semua." Disebut sebelum tombolnya ditekan, bukan
           sesudah — supaya yang menandatangani tahu apa yang sedang ia
           tandatangani. --}}
      <div class="pesan info" style="margin-bottom:14px">
        {{ $lainBelum->count() }} satuan kerja lain belum ditandai memadai:
        {{ $lainBelum->map(fn ($x) => $x->satker?->namaPendek())->filter()->join(', ') }}.
        Suratnya akan berbunyi <b>belum memadai</b> walau berkas ini Anda nilai cukup.
      </div>
    @endif

    <form method="post" action="{{ route('sasaran.telaah', $s) }}">
      @csrf
      <label class="f" style="max-width:340px">
        <span class="lbl">Hasil</span>
        <select name="hasil" required>
          <option value="M">{{ \App\Enums\HasilTelaah::M->nama($sumber) }} &mdash; berkas cukup</option>
          <option value="BM">{{ \App\Enums\HasilTelaah::BM->nama($sumber) }} &mdash; kembali ke satuan kerja</option>
        </select>
      </label>

      @if($peran === PeranPengguna::UKI)
        <label class="f">
          <span class="lbl">Nomor surat LHV</span>
          <input type="text" name="nomor_surat" placeholder="kosongkan bila belum ada">
          <span class="hint">Untuk menerbitkan LHV yang memuat beberapa berkas sekaligus,
            pakai layar <a href="{{ route('validasi.form') }}">Terbitkan LHV</a>.</span>
        </label>
      @else
        {{-- Surat CHV terbit di sini, bukan di layar tersendiri. Putusan
             memadai selalu membawa nomor surat: tanpa suratnya tidak ada dasar
             hukum untuk menyebutnya memadai, dan yang tersisa cuma centang di
             layar. --}}
        <div class="f">
          <span class="lbl">Surat CHV &mdash; wajib bila hasilnya memadai</span>
          <div class="trio">
            <label class="f" style="margin-bottom:0">
              <span class="lbl">Nomor surat</span>
              <input type="text" name="nomor_surat" value="{{ old('nomor_surat') }}"
                placeholder="88/CHV/ITJEN/IX/2026">
              @error('nomor_surat')<span class="hint" style="color:var(--verm)">{{ $message }}</span>@enderror
            </label>
            <label class="f" style="margin-bottom:0">
              <span class="lbl">Tanggal surat</span>
              <input type="date" name="tgl_surat" value="{{ old('tgl_surat', now()->toDateString()) }}">
              @error('tgl_surat')<span class="hint" style="color:var(--verm)">{{ $message }}</span>@enderror
            </label>
            <label class="f" style="margin-bottom:0">
              <span class="lbl">Periode</span>
              <input type="text" name="periode" value="{{ old('periode') }}"
                placeholder="Semester II 2026">
            </label>
          </div>
          <span class="hint">
            Satu surat bernomor boleh memuat beberapa rekomendasi sekaligus &mdash; nomor yang
            sama cukup diketik ulang, dan putusannya menempel ke surat yang sudah ada.
          </span>
        </div>

        @if($lainBelum->isNotEmpty())
          <label style="display:flex;gap:9px;align-items:flex-start;padding:10px 12px;margin-bottom:14px;
                        background:var(--verm-bg);border-left:3px solid var(--verm);border-radius:0 5px 5px 0">
            <input type="checkbox" name="diakui" value="1" style="width:auto;margin-top:3px">
            <span style="font-size:12.5px;color:var(--verm)">
              Suratnya tetap menyebut rekomendasi ini memadai walau ada satuan kerja yang belum.
              Sistem tidak menghalangi &mdash; hanya memastikan keadaannya tercatat, bukan lolos
              diam-diam.
            </span>
          </label>
        @endif
      @endif
      <label class="f">
        <span class="lbl">
          {{ $peran === PeranPengguna::UKI ? 'Kesimpulan telaah' : 'Kesimpulan verifikasi' }}
          &mdash; wajib diisi
        </span>
        <textarea name="catatan" required minlength="6" style="min-height:52px"
          placeholder="{{ $peran === PeranPengguna::UKI
            ? 'Contoh: bukti setor dan Nota Konfirmasi KPPN sudah lengkap dan cocok dengan nilai temuan.'
            : 'Contoh: seluruh kewajiban terpenuhi, tidak ada yang menggantung.' }}"></textarea>
      </label>
      <button class="btn btn-p" type="submit">Simpan hasil</button>
      <div class="hint" style="margin-top:8px">
        Telaah tanpa kesimpulan bukan telaah &mdash; yang membaca berikutnya perlu tahu apa yang dinilai.
      </div>
    </form>

    <form method="post" action="{{ route('sasaran.kembalikan', $s) }}"
          style="margin-top:14px;padding-top:12px;border-top:1px solid var(--rule-2)">
      @csrf
      <label class="f">
        <span class="lbl">Kembalikan tanpa menelaah &mdash; alasan wajib diisi</span>
        <textarea name="alasan" required minlength="6" style="min-height:52px"></textarea>
      </label>
      <button class="btn" type="submit">Kembalikan ke {{ $namaSatker }}</button>
      <div class="hint" style="margin-top:8px">Status tidak berubah, dan tidak ada tanda memadai yang ditulis.</div>
    </form>

    @include('bagian.minta-dokumen', ['s' => $s, 'bentuk' => $bentuk])
  </div>
  @endif
@endif
