@php
  use App\Support\PetaData;
  use App\Support\Tampil;

  $d = $p['data'];
  $D = $d['D'];
  $U = $d['U'];
  $i = $p['i'];

  $tulis = fn ($v) => $U['uang'] ? Tampil::rupiahSingkat((int) $v) : (string) $v;

  $kalimat = match ($p['bentuk']) {
    'tumpuk' => 'Tiap batang satu ' . mb_strtolower($D['nama']) . ', dibelah menurut status. Panjangnya ' . mb_strtolower($U['nama']) . '.',
    'tabel'  => 'Satu baris satu ' . mb_strtolower($D['nama']) . ', dengan ' . mb_strtolower($U['nama']) . ' dan sebaran statusnya.',
    default  => 'Tiap batang satu ' . mb_strtolower($D['nama']) . '. Panjangnya ' . mb_strtolower($U['nama']) . '.',
  };
@endphp

<div class="kartu" style="padding:15px 17px 13px">
  <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:11px">
    <h3 style="margin:0;font-size:14px;font-weight:600">
      {{ $D['nama'] }}
      <span style="font-weight:400;color:var(--ink-3)">menurut {{ mb_strtolower($U['nama']) }}</span>
    </h3>
  </div>

  {{-- Pilihannya dikirim lewat GET. Panel lain ikut sebagai isian tersembunyi
       supaya susunannya tidak balik ke bawaan tiap kali satu panel diubah. --}}
  <form method="get" action="{{ route('ringkasan') }}"
    style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;padding-bottom:11px;
      margin-bottom:11px;border-bottom:1px solid var(--rule-2)">
    @foreach($semua as $lain)
      @if($lain['i'] !== $i)
        <input type="hidden" name="p{{ $lain['i'] }}dim" value="{{ $lain['dim'] }}">
        <input type="hidden" name="p{{ $lain['i'] }}ukur" value="{{ $lain['ukur'] }}">
        <input type="hidden" name="p{{ $lain['i'] }}bentuk" value="{{ $lain['bentuk'] }}">
        <input type="hidden" name="p{{ $lain['i'] }}urut" value="{{ $lain['urut'] }}">
      @endif
    @endforeach

    <label class="f" style="flex:1 1 128px;margin:0">
      <span class="lbl">Kelompokkan</span>
      <select name="p{{ $i }}dim">
        @foreach(PetaData::dimensi() as $k => $v)
          <option value="{{ $k }}" @selected($p['dim'] === $k)>{{ $v['nama'] }}</option>
        @endforeach
      </select>
    </label>
    <label class="f" style="flex:1 1 128px;margin:0">
      <span class="lbl">Hitung</span>
      <select name="p{{ $i }}ukur">
        @foreach(PetaData::ukuran() as $k => $v)
          <option value="{{ $k }}" @selected($p['ukur'] === $k)>{{ $v['nama'] }}</option>
        @endforeach
      </select>
    </label>
    <label class="f" style="flex:1 1 118px;margin:0">
      <span class="lbl">Bentuk</span>
      <select name="p{{ $i }}bentuk">
        @foreach(PetaData::bentukTampil() as $k => $v)
          <option value="{{ $k }}" @selected($p['bentuk'] === $k)>{{ $v }}</option>
        @endforeach
      </select>
    </label>
    <label class="f" style="flex:1 1 118px;margin:0">
      <span class="lbl">Urutan</span>
      <select name="p{{ $i }}urut">
        @foreach(PetaData::urutTampil() as $k => $v)
          <option value="{{ $k }}" @selected($p['urut'] === $k)>{{ $v }}</option>
        @endforeach
      </select>
    </label>
    <button class="btn btn-s" type="submit">Terapkan</button>
  </form>

  <div class="lbl" style="line-height:1.5;margin-bottom:12px">{{ $kalimat }}</div>

  @if($d['baris']->isEmpty())
    <div style="font-size:13px;color:var(--ink-3)">Tidak ada data pada kelompok ini.</div>

  @elseif($p['bentuk'] === 'tabel')
    <table>
      <thead><tr><th>{{ $D['nama'] }}</th><th style="text-align:right">{{ $U['nama'] }}</th><th style="text-align:right">Bagian</th></tr></thead>
      <tbody>
        @foreach($d['baris'] as $b)
          <tr>
            <td style="font-size:12.5px">{{ $b['nama'] }}</td>
            <td class="mono" style="font-size:12.5px;text-align:right;font-weight:600">{{ $tulis($b['nilai']) }}</td>
            <td class="mono" style="font-size:12px;text-align:right">{{ PetaData::bagian($b['nilai'], $d['total']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

  @else
    <div style="display:grid;gap:9px">
      @foreach($d['baris'] as $b)
        <div style="display:flex;align-items:center;gap:11px">
          <span style="flex:none;width:clamp(96px,30%,190px);font-size:11.5px;color:var(--ink-2);line-height:1.35"
            title="{{ $b['nama'] }}">{{ $b['nama'] }}</span>
          <span style="flex:1;min-width:0;height:14px;background:var(--rule-2);border-radius:4px;overflow:hidden;display:flex">
            @if($p['bentuk'] === 'tumpuk')
              {{-- Tiap potongan dipisah celah dua piksel dan ditempeli kodenya
                   begitu muat: warna saja tidak cukup membedakan status di sini. --}}
              @foreach($b['pecah'] as $s)
                @php $lebar = $d['maks'] > 0 ? $s['n'] / $d['maks'] * 100 : 0; @endphp
                <i title="{{ $s['s'] }}: {{ $tulis($s['n']) }}"
                  style="display:grid;place-items:center;height:100%;width:{{ $lebar }}%;
                    background:{{ $warna[$s['s']] }};box-shadow:2px 0 0 0 var(--surface)">
                  @if($lebar > 9)<b style="font-size:9px;font-weight:700;color:#fff;letter-spacing:.04em">{{ $s['s'] }}</b>@endif
                </i>
              @endforeach
            @else
              <i style="display:block;height:100%;background:var(--ink);border-radius:0 4px 4px 0;
                width:{{ max(2, $d['maks'] > 0 ? $b['nilai'] / $d['maks'] * 100 : 0) }}%"></i>
            @endif
          </span>
          <span class="mono" style="flex:none;min-width:62px;text-align:right;font-size:12px;font-weight:600">{{ $tulis($b['nilai']) }}</span>
        </div>
      @endforeach
    </div>

    @if($p['bentuk'] === 'tumpuk')
      <div style="display:flex;gap:13px;flex-wrap:wrap;margin-top:13px;padding-top:10px;border-top:1px solid var(--rule-2)">
        @foreach(['BT','BS','SS','TD'] as $s)
          <span class="lbl" style="display:inline-flex;align-items:center;gap:6px">
            <i style="width:9px;height:9px;border-radius:2px;background:{{ $warna[$s] }};display:block"></i>{{ $s }}
          </span>
        @endforeach
      </div>
    @endif
  @endif

  <div class="lbl" style="margin-top:12px;padding-top:10px;border-top:1px solid var(--rule-2);line-height:1.5">
    {{ $d['baris']->count() }} kelompok &middot; seluruhnya {{ $tulis($d['sebenarnya']) }}
    @if(! $U['uang']) {{ $U['satuan'] }} @endif

    @if($d['total'] > $d['sebenarnya'])
      {{-- Ukuran berbasis temuan dihitung sekali per temuan. Begitu
           dikelompokkan menurut satuan kerja, temuan yang rekomendasinya jatuh
           ke dua satuan kerja masuk ke dua kelompok — dan menjumlahkan
           kelompoknya mengembalikan lagi pelipatan yang tadi ditutup. --}}
      <span style="display:block;margin-top:5px;color:var(--brass)">
        Jumlah tiap kelompok bila ditambahkan: {{ $tulis($d['total']) }}. Angkanya lebih besar
        karena satu temuan bisa masuk ke lebih dari satu kelompok. Yang benar untuk dilaporkan
        adalah {{ $tulis($d['sebenarnya']) }}.
      </span>
    @endif
  </div>
</div>
