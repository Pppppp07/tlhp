@extends('rangka')
@section('judul', 'Data master')
@section('isi')

<div class="kartu" style="margin-bottom:14px">
  <div class="judulkartu">
    <span class="ic-kotak"><x-ik nama="petak" ukuran="17" /></span>
    <h3>Kategori internal</h3>
    <span class="n">{{ $kategori->where('aktif', true)->count() }} aktif</span>
    <x-info :teks="[
      'Pengelompokan BPSDM sendiri, di luar kategori resmi dari pemeriksa.',
      'Warnanya dipakai di lencana temuan dan di grafik sebaran Ringkasan, jadi sekali lihat sudah ketahuan perkaranya jenis apa.',
      'Daftar ini boleh bertambah dan berganti nama tanpa mengubah kode.',
    ]" />
  </div>

  <div class="hint" style="margin-bottom:14px">
    Kategori yang sudah dipakai tidak bisa dihapus &mdash; hanya dinonaktifkan.
    Keterangannya tetap terbaca di berkas lama; yang berubah cuma munculnya di
    pilihan saat mencatat laporan baru.
  </div>

  <div class="tw">
    <table>
      <thead><tr>
        <th>Nama kategori</th>
        <th>Warna label</th>
        <th class="num">Dipakai</th>
        <th>Keadaan</th>
      </tr></thead>
      <tbody>
      @foreach($kategori as $k)
        <tr @class(['sepi' => ! $k->aktif])>
          <td style="min-width:220px">
            <form method="post" action="{{ route('master.simpan', $k) }}" class="barisMaster">
              @csrf
              <input type="text" name="nama" value="{{ $k->nama }}" style="width:100%">

              {{-- Warna dikirim bersama namanya dalam satu formulir: keduanya
                   keterangan kategori yang sama, dan dua formulir berdampingan
                   membuat mengganti warna diam-diam membatalkan nama yang baru
                   diketik. --}}
              <div class="pilihwarna">
                @foreach($warna as $w)
                  <label title="{{ $w->nama() }}">
                    <input type="radio" name="warna" value="{{ $w->value }}"
                      @checked($k->warnaLabel() === $w)>
                    <span style="background:{{ $w->padat() }}"></span>
                  </label>
                @endforeach
              </div>

              <button class="btn btn-s" type="submit">Simpan</button>
            </form>
          </td>
          <td>
            <span class="nilaikat" style="margin-left:0">
              <i style="background:{{ $k->warnaLabel()->padat() }}"></i>
              {{ $k->warnaLabel()->nama() }}
            </span>
          </td>
          <td class="num mono" style="font-size:12.5px">{{ $terpakai[$k->id] ?? 0 }}</td>
          <td>
            <form method="post" action="{{ route('master.saklar', $k) }}">
              @csrf
              <button class="btn btn-s {{ $k->aktif ? '' : 'btn-p' }}" type="submit"
                @if($k->aktif && ($terpakai[$k->id] ?? 0) > 0)
                  onclick="return confirm('{{ $terpakai[$k->id] }} temuan masih memakainya. Keterangannya tetap terbaca, tapi kategori ini tidak lagi muncul saat mencatat laporan baru. Nonaktifkan?')"
                @endif>
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
    <input type="text" name="nama" required placeholder="Nama kategori baru"
      style="flex:1;min-width:200px">
    <button class="btn btn-p" type="submit">+ Tambah kategori</button>
  </form>
</div>

{{-- Yang tidak bisa diubah dari sini, berikut sebabnya. Daftar yang hilang
     tanpa keterangan membuat orang mencarinya di tempat lain. --}}
<div class="kartu">
  <div class="judulkartu">
    <span class="ic-kotak abu"><x-ik nama="pilar" ukuran="17" /></span>
    <h3>Yang mengikuti peraturan</h3>
  </div>
  <div class="hint">
    Bentuk tindak lanjut, kategori temuan, dan alasan sah tidak dapat
    ditindaklanjuti menyalin SOP dan peraturan di atasnya. Mengubahnya berarti
    menyimpang dari dasar hukumnya, jadi keduanya tidak bisa disunting dari
    layar ini &mdash; perubahannya menunggu peraturannya sendiri berubah.
  </div>
</div>

@endsection
