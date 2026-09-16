@extends('rangka')
@section('judul', 'Data master')
@section('isi')
@php
  use App\Enums\SumberLaporan;

  /* Data master — padanan `LayarMaster`. Dua kolom isian saja pada kategori
     internal: nama dan warna, ditambah tombol aktif/nonaktif. Tanpa keterangan
     panjang: yang dimintanya memang sesedikit itu, dan kolom yang tidak dipakai
     cuma jadi kolom kosong.

     Kategori tidak dihapus, hanya dinonaktifkan. Menghapusnya membuat temuan
     lama menunjuk ke kategori yang sudah tidak ada. */
@endphp

<div class="body">
  <div class="card" style="margin-bottom:14px">
    <div class="judulkartu">
      <span class="ic-kotak biru"><x-ikon n="ListChecks" :s="17" /></span>
      <h3>Kategori internal</h3>
      <span class="n">{{ $kategori->where('aktif', true)->count() }} aktif</span>
    </div>
    <div class="hint" style="margin-bottom:14px">
      Pengelompokan BPSDM sendiri. Warnanya dipakai di lencana temuan dan di grafik
      ringkasan, jadi sekali lihat sudah ketahuan perkaranya jenis apa.
    </div>

    <div style="overflow-x:auto;border:1px solid var(--rule);border-radius:6px">
      <table>
        <thead>
          <tr><th>Nama kategori</th><th>Warna label</th><th class="num">Dipakai</th><th>Keadaan</th></tr>
        </thead>
        <tbody>
          @foreach($kategori as $k)
            @php $dipakai = $terpakai[$k->id] ?? 0; @endphp
            <tr>
              <td style="min-width:220px">
                {{-- Nama dan warna satu formulir: keduanya keterangan kategori
                     yang sama, dan dua formulir berdampingan membuat mengganti
                     warna diam-diam membatalkan nama yang baru diketik. --}}
                <form method="post" action="{{ route('master.simpan', $k) }}" id="kat{{ $k->id }}"
                  style="display:flex;gap:8px;align-items:center">
                  @csrf
                  <input type="text" name="nama" value="{{ $k->nama }}" style="flex:1;min-width:0" data-kirim>
                  <button class="btn btn-s" type="submit" data-tanpa-js>Simpan</button>
                </form>
              </td>
              <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap">
                  @foreach($warna as $w)
                    <label title="{{ $w->nama() }}" class="pilihwarna"
                      style="width:24px;height:24px;border-radius:6px;cursor:pointer;display:block;
                        background:{{ $w->padat() }};
                        border:{{ $k->warna === $w ? '2px solid var(--ink)' : '1px solid var(--line)' }}">
                      <input type="radio" form="kat{{ $k->id }}" name="warna" value="{{ $w->value }}"
                        @checked($k->warna === $w) data-kirim
                        style="position:absolute;opacity:0;width:0;height:0">
                    </label>
                  @endforeach
                </div>
              </td>
              <td class="num mono" style="font-size:12.5px">{{ $dipakai }}</td>
              <td>
                <form method="post" action="{{ route('master.saklar', $k) }}">
                  @csrf
                  @php
                    $pastikan = ['judul' => 'Nonaktifkan kategori ini?',
                      'ket' => $dipakai.' temuan masih memakainya. Keterangannya tetap terbaca, tapi kategori ini tidak lagi muncul saat mencatat laporan baru.',
                      'tombol' => 'Ya, nonaktifkan', 'nada' => 'jingga'];
                  @endphp
                  <button class="btn btn-s{{ $k->aktif ? '' : ' btn-p' }}" type="submit"
                    @if($k->aktif && $dipakai > 0) data-pastikan='@json($pastikan)' @endif>
                    {{ $k->aktif ? 'Aktif' : 'Nonaktif' }}
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <form method="post" action="{{ route('master.tambah') }}"
      style="display:flex;gap:9px;margin-top:14px;flex-wrap:wrap">
      @csrf
      <input type="text" name="nama" required placeholder="Nama kategori baru" style="flex:1;min-width:200px">
      <button class="btn btn-p" type="submit"><x-ikon n="Plus" :s="14" /> Tambah kategori</button>
    </form>
  </div>

  {{-- Kategori temuan per sumber laporan. Tanpa warna — yang dibutuhkan cuma
       nama dan saklar aktif. Tidak dihapus, hanya dinonaktifkan: temuan lama
       yang memakainya tetap menunjuk ke kategori yang ada. --}}
  <div class="card" style="margin-bottom:14px">
    <div class="judulkartu">
      <span class="ic-kotak biru"><x-ikon n="ListChecks" :s="17" /></span>
      <h3>Kategori temuan</h3>
      <span class="n">{{ $kategoriTemuan->flatten()->where('aktif', true)->count() }} aktif</span>
    </div>
    <div class="hint" style="margin-bottom:14px">
      Pilihan kategori saat mencatat temuan, dipisah menurut sumber laporannya.
      Yang dinonaktifkan tidak lagi ditawarkan, tapi temuan lama tetap memakainya.
    </div>
    <div class="duo">
      @foreach(['LHP', 'LHA'] as $jenis)
        <div>
          <div class="lbl" style="margin-bottom:8px">{{ SumberLaporan::from($jenis)->nama() }}</div>
          <div style="overflow-x:auto;border:1px solid var(--rule);border-radius:6px">
            <table>
              <thead><tr><th>Nama kategori</th><th class="num">Dipakai</th><th>Keadaan</th></tr></thead>
              <tbody>
                @foreach($kategoriTemuan[$jenis] ?? [] as $k)
                  @php $dipakai = $terpakaiTemuan[$k->id] ?? 0; @endphp
                  <tr>
                    <td style="min-width:180px">
                      {{-- Nama yang sudah dipakai temuan tidak diubah di sini:
                           yang dibaca orang pada berkas lama adalah nama itu. --}}
                      @if($dipakai)
                        <span>{{ $k->nama }}</span>
                      @else
                        <form method="post" action="{{ route('master.temuan.simpan', $k) }}"
                          style="display:flex;gap:8px;align-items:center">
                          @csrf
                          <input type="text" name="nama" value="{{ $k->nama }}" style="flex:1;min-width:0" data-kirim>
                          <button class="btn btn-s" type="submit" data-tanpa-js>Simpan</button>
                        </form>
                      @endif
                    </td>
                    <td class="num mono" style="font-size:12.5px">{{ $dipakai }}</td>
                    <td>
                      <form method="post" action="{{ route('master.temuan.saklar', $k) }}">
                        @csrf
                        <button class="btn btn-s{{ $k->aktif ? '' : ' btn-p' }}" type="submit">
                          {{ $k->aktif ? 'Aktif' : 'Nonaktif' }}
                        </button>
                      </form>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <form method="post" action="{{ route('master.temuan.tambah') }}"
            style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
            @csrf
            <input type="hidden" name="sumber" value="{{ $jenis }}">
            <input type="text" name="nama" required placeholder="Kategori {{ $jenis }} baru" style="flex:1;min-width:160px">
            <button class="btn btn-p" type="submit"><x-ikon n="Plus" :s="14" /> Tambah</button>
          </form>
        </div>
      @endforeach
    </div>
  </div>
</div>
@endsection
