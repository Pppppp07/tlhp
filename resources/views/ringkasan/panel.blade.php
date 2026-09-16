@php
  use App\Support\PetaData;

  /* Satu panel peta data — padanan `PanelPeta`. Pilihannya di kepala,
     gambarnya di badan, dan satu kalimat yang menyebut apa yang sedang
     dilihat. Kalimat itu bukan hiasan: tanpanya pembaca harus membaca ulang
     keempat kotak pilihan untuk tahu artinya. */
  $data = $p['data'];
  $D = $data['D'];
  $U = $data['U'];
  $baris = $data['baris'];
  $dimNama = mb_strtolower($D['nama']);
  $ukurNama = mb_strtolower($U['nama']);

  /* Donat menutup diri sendiri kalau kelompoknya terlalu banyak — lebih baik
     bentuknya diganti daripada memaksa mata membedakan dua belas warna. */
  $bentuk = $p['bentuk'] === 'donat' && count($baris) > 12 ? 'batang' : $p['bentuk'];
  $paksaan = $bentuk !== $p['bentuk'];

  $kalimat = match ($bentuk) {
    'tumpuk' => "Tiap batang satu {$dimNama}, dibelah menurut status. Panjangnya {$ukurNama}.",
    'donat'  => "Tiap potongan satu {$dimNama}. Besarnya {$ukurNama}.",
    'tabel'  => "Satu baris satu {$dimNama}, dengan {$ukurNama} dan sebaran statusnya.",
    default  => "Tiap batang satu {$dimNama}. Panjangnya {$ukurNama}.",
  };

  /* Keterangan melayang. Nilainya sudah tertulis di sebelah batang, jadi yang
     ditambahkan justru yang tidak muat: bagiannya terhadap seluruhnya, dan
     pecahan statusnya. */
  $tunjuk = fn ($nama, $nilai, $bagi, $pecah = []) => 'data-tunjuk data-nama="'.e($nama)
    .'" data-nilai="'.e($nilai).'" data-bagi="'.e($bagi).'" data-pecah="'.e(json_encode(array_map(
      fn ($x) => ['s' => $x['s'], 'n' => PetaData::tulis($U, $x['n']), 'w' => PetaData::WARNA_STATUS[$x['s']]],
      $pecah))).'"';
@endphp

<section @class(['panel', 'lebar' => $p['lebar']])>
  <div class="kepala">
    <h3>{{ $D['nama'] }} <span class="oleh">menurut {{ $ukurNama }}</span></h3>
    <x-info :teks="[$D['ket'], $U['ket']]" />
    <div class="sela"></div>
    <button class="ikonbtn" type="submit" name="aksi" value="lebar:{{ $i }}"
      title="{{ $p['lebar'] ? 'Jadikan setengah lebar' : 'Jadikan selebar halaman' }}">
      <x-ikon :n="$p['lebar'] ? 'ChevronLeft' : 'ChevronRight'" :s="15" />
    </button>
    <button class="ikonbtn" type="submit" name="aksi" value="hapus:{{ $i }}" title="Hapus panel ini">
      <x-ikon n="X" :s="15" />
    </button>
  </div>

  <div class="setelan">
    <label><span class="lbl">Kelompokkan</span>
      <select name="p[{{ $i }}][dim]" data-saring-peta>
        @foreach(PetaData::dimensi() as $k => $d)
          <option value="{{ $k }}" @selected($p['dim'] === $k)>{{ $d['nama'] }}</option>
        @endforeach
      </select>
    </label>
    <label><span class="lbl">Hitung</span>
      <select name="p[{{ $i }}][ukur]" data-saring-peta>
        @foreach(PetaData::ukuran() as $k => $u)
          <option value="{{ $k }}" @selected($p['ukur'] === $k)>{{ $u['nama'] }}</option>
        @endforeach
      </select>
    </label>
    <label><span class="lbl">Bentuk</span>
      <select name="p[{{ $i }}][bentuk]" data-saring-peta>
        @foreach(PetaData::BENTUK_TAMPIL as $k => $n)
          <option value="{{ $k }}" @selected($p['bentuk'] === $k)>{{ $n }}</option>
        @endforeach
      </select>
    </label>
    <label><span class="lbl">Urutan</span>
      <select name="p[{{ $i }}][urut]" data-saring-peta>
        <option value="nilai" @selected($p['urut'] === 'nilai' || (! $data['alami'] && $p['urut'] === 'alami'))>Terbesar dulu</option>
        @if($data['alami'])
          <option value="alami" @selected($p['urut'] === 'alami')>Urutan bakunya</option>
        @endif
        <option value="nama" @selected($p['urut'] === 'nama')>Menurut abjad</option>
      </select>
    </label>
    <input type="hidden" name="p[{{ $i }}][lebar]" value="{{ $p['lebar'] }}">
  </div>

  <div class="ket">
    {{ $kalimat }}
    @if($paksaan) Bentuk donat dilewati: kelompoknya terlalu banyak untuk dibedakan warna. @endif
  </div>

  <div class="isipanel">
    @if(count($baris) === 0)
      <div class="kosong">Tidak ada data pada kelompok ini.</div>

    {{-- Batang yang dibelah menurut status. Tiap potongan dipisah celah dua
         piksel dan ditempeli kodenya sendiri begitu muat — merah dan hijau di
         sini nyaris kembar bagi mata yang buta warna. --}}
    @elseif($bentuk === 'tumpuk')
      <div class="btgset">
        @foreach($baris as $b)
          <div class="btgbaris">
            <span class="nm">{{ $b['nama'] }}</span>
            <span class="jalur tumpuk">
              @foreach($b['pecah'] as $s)
                @php $lebar = $data['maks'] ? $s['n'] / $data['maks'] * 100 : 0; @endphp
                <i style="width:{{ $lebar }}%;background:{{ PetaData::WARNA_STATUS[$s['s']] }}"
                  {!! $tunjuk($b['nama'].' — '.$s['s'].' · '.\App\Enums\StatusTindakLanjut::from($s['s'])->pendek(),
                    PetaData::tulis($U, $s['n']), PetaData::bagian($s['n'], $b['nilai'])) !!}>
                  @if($lebar > 9)<b>{{ $s['s'] }}</b>@endif
                </i>
              @endforeach
            </span>
            <span class="n">{{ PetaData::tulis($U, $b['nilai']) }}</span>
          </div>
        @endforeach
        <div class="legenda">
          @foreach(PetaData::URUT_STATUS as $s)
            <span><i style="background:{{ PetaData::WARNA_STATUS[$s] }}"></i>{{ $s }} · {{ \App\Enums\StatusTindakLanjut::from($s)->pendek() }}</span>
          @endforeach
        </div>
      </div>

    {{-- Donat hanya untuk melihat porsi sekilas, dan hanya sampai delapan
         potongan. Lebih dari itu potongan bersebelahan mulai kembar warnanya,
         jadi sisanya dilipat jadi satu "Lainnya" — bukan diberi warna baru yang
         dikarang. --}}
    @elseif($bentuk === 'donat')
      @php
        $BATAS = 8;
        $pakai = count($baris) > $BATAS
          ? array_merge(array_slice($baris, 0, $BATAS - 1), [[
              'kunci' => '__lain',
              'nama'  => 'Lainnya ('.(count($baris) - $BATAS + 1).' kelompok)',
              'nilai' => array_sum(array_column(array_slice($baris, $BATAS - 1), 'nilai')),
              'pecah' => [],
            ]])
          : $baris;
        $jml = array_sum(array_column($pakai, 'nilai')) ?: 1;
        $R = 62; $T = 22; $K = 2 * M_PI * $R; $jalan = 0;
      @endphp
      <div class="donatset">
        <svg viewBox="0 0 160 160" class="donat" role="img" aria-label="Donat {{ count($baris) }} kelompok">
          <circle cx="80" cy="80" r="{{ $R }}" fill="none" stroke="var(--surface-3)" stroke-width="{{ $T }}" />
          @foreach($pakai as $j => $b)
            @php
              $potong = $b['nilai'] / $jml * $K;
              /* Celah dua piksel antarpotongan: tanpa itu dua warna bertemu
                 langsung dan batasnya hilang saat dicetak hitam putih. */
              $panjang = max(0, $potong - 2);
              $geser = -$jalan;
              $jalan += $potong;
            @endphp
            <circle cx="80" cy="80" r="{{ $R }}" fill="none"
              stroke="{{ PetaData::WARNA_KATEG[$j % count(PetaData::WARNA_KATEG)] }}" stroke-width="{{ $T }}"
              stroke-dasharray="{{ $panjang }} {{ $K - $panjang }}" stroke-dashoffset="{{ $geser }}"
              transform="rotate(-90 80 80)"
              {!! $tunjuk($b['nama'], PetaData::tulis($U, $b['nilai']), PetaData::bagian($b['nilai'], $jml), $b['pecah']) !!} />
          @endforeach
          <text x="80" y="76" text-anchor="middle" class="inti">{{ count($pakai) }}</text>
          <text x="80" y="93" text-anchor="middle" class="intiket">kelompok</text>
        </svg>

        {{-- Tiap potongan wajib berlabel bernilai: satu warnanya kontrasnya
             rendah terhadap latar, dan warna saja tidak boleh jadi satu-satunya
             penanda. --}}
        <div class="donatlegenda">
          @foreach($pakai as $j => $b)
            <span>
              <i style="background:{{ PetaData::WARNA_KATEG[$j % count(PetaData::WARNA_KATEG)] }}"></i>
              <span class="nm">{{ $b['nama'] }}</span>
              <b>{{ PetaData::tulis($U, $b['nilai']) }}</b>
              <span class="bg">{{ PetaData::bagian($b['nilai'], $jml) }}</span>
            </span>
          @endforeach
        </div>
      </div>

    @elseif($bentuk === 'tabel')
      <div class="isi tw" style="padding:0;border:0">
        <table>
          <thead>
            <tr>
              <th>{{ $D['nama'] }}</th>
              <th class="num">{{ $U['nama'] }}</th>
              <th class="num">Bagian</th>
              <th>Sebaran status</th>
            </tr>
          </thead>
          <tbody>
            @foreach($baris as $b)
              <tr>
                <td style="font-size:12.5px">
                  @if($b['warna'])
                    <i style="display:inline-block;width:8px;height:8px;border-radius:999px;background:{{ $b['warna'] }};margin-right:7px;vertical-align:middle"></i>
                  @endif
                  {{ $b['nama'] }}
                </td>
                <td class="num mono" style="font-size:12.5px;font-weight:600">{{ PetaData::tulis($U, $b['nilai']) }}</td>
                <td class="num mono" style="font-size:12px">{{ PetaData::bagian($b['nilai'], $data['total']) }}</td>
                <td>
                  <span class="jalur tumpuk kecil">
                    @foreach($b['pecah'] as $s)
                      <i title="{{ $s['s'] }} · {{ \App\Enums\StatusTindakLanjut::from($s['s'])->pendek() }}: {{ PetaData::tulis($U, $s['n']) }}"
                        style="width:{{ $s['n'] / ($b['nilai'] ?: 1) * 100 }}%;background:{{ PetaData::WARNA_STATUS[$s['s']] }}"></i>
                    @endforeach
                  </span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

    {{-- Batang mendatar, satu warna untuk semua — panjangnya yang membawa
         arti, jadi mewarnai tiap batang berbeda hanya menghabiskan
         satu-satunya saluran yang masih kosong untuk mengulang hal yang
         sama. --}}
    @else
      <div class="btgset">
        @foreach($baris as $b)
          <div class="btgbaris"
            {!! $tunjuk($b['nama'], PetaData::tulis($U, $b['nilai']), PetaData::bagian($b['nilai'], $data['total']), $b['pecah']) !!}>
            <span class="nm">{{ $b['nama'] }}</span>
            <span class="jalur">
              <i style="width:{{ max(2, $b['nilai'] / $data['maks'] * 100) }}%@if($b['warna']);background:{{ $b['warna'] }}@endif"></i>
            </span>
            <span class="n">{{ PetaData::tulis($U, $b['nilai']) }}</span>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <div class="kaki">
    {{ count($baris) }} kelompok · seluruhnya {{ PetaData::tulis($U, $data['sebenarnya']) }}{{ empty($U['uang']) ? ' '.$U['satuan'] : '' }}
    @if($data['total'] > $data['sebenarnya'])
      ·
      <span style="color:var(--warn)">jumlah kelompok {{ PetaData::tulis($U, $data['total']) }}</span>
      <x-info nada="awas" :teks="[
        'Angkanya berbeda karena satu temuan atau rekomendasi bisa masuk ke lebih dari satu kelompok — misalnya satu rekomendasi dipikul dua satuan kerja.',
        'Yang benar untuk dilaporkan adalah '.PetaData::tulis($U, $data['sebenarnya']).'. Menjumlahkan tiap kelompok menghitung hal yang sama lebih dari sekali.',
        'Bagian dalam persen tetap dihitung dari jumlah kelompok, supaya seluruhnya genap seratus persen.',
      ]" />
    @endif
  </div>
</section>
