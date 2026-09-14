@php
  use App\Support\Tampil;
  use App\Support\Tugas;

  $peran = auth()->user()->peran;
  $lap = $r->temuan->laporan;

  /* Yang tampil paling besar adalah tindakannya, bukan bunyi rekomendasinya.
     Daftar yang menampilkan uraian memaksa pembacanya menerjemahkan sendiri
     tiap baris jadi "apa yang harus saya lakukan". */
  /* Satuan kerja membaca dari BARISNYA sendiri: rekomendasi yang dipikul
     tiga satker berada di tiga tahap sekaligus, dan menyebut satu tahap untuk
     semuanya pasti salah bagi dua di antaranya. */
  $baris = $peran === \App\Enums\PeranPengguna::SATKER
    ? $r->daftarSasaran()->firstWhere('satker_id', auth()->user()->satker_id)
    : null;

  $aksi = Tugas::label($r, $peran, $baris);
  $urgensi = Tugas::urgensi($r);
  $kurang = Tugas::kurang($r, $baris);

  $namaSatker = $r->daftarSasaran()->map(fn ($x) => $x->satker?->namaPendek())->filter();
  $konteks = collect([
    $r->temuan->judul,
    ($tampilSatker ?? true)
      ? ($namaSatker->count() > 2 ? $namaSatker->count() . ' satuan kerja' : $namaSatker->join(', '))
      : null,
  ])->filter()->join(' · ');

  /* Yang bukan giliran kita mengecil dan meredup, tapi tidak dihilangkan —
     memantau tetap perlu, cuma tidak boleh menenggelamkan pekerjaan hari ini. */
  $kelas = ['tugas'];
  if (! $aksi['giliranSaya']) {
      $kelas[] = 'sepi';
  } elseif ($utama ?? false) {
      $kelas[] = 'utama';
  }
  if ($aksi['giliranSaya'] && $urgensi) {
      $kelas[] = $urgensi['nada'];
  }
  if ($ditandai ?? false) {
      $kelas[] = 'tandai';
      $kelas[] = $nada ?? 'aksen';
  }

  $nadaPil = ! $aksi['giliranSaya'] ? ''
    : ($urgensi ? ($urgensi['nada'] === 'genting' ? 'merah' : 'kuning') : 'biru');

  $isiPil = ! $aksi['giliranSaya']
    ? (($baris?->posisi ?? $r->posisiTampil())?->sebutanPemegang() ?? 'Belum ditugaskan')
    : ($urgensi['teks'] ?? 'perlu dikerjakan');
@endphp

<a href="{{ route('rekomendasi.show', $r) }}" @class($kelas)>
  <span class="pil {{ $nadaPil }}">{{ $isiPil }}</span>

  <span class="inti">
    <b>{{ $aksi['teks'] }}</b>
    <span>{{ $konteks }}</span>
  </span>

  <span class="sisi">
    <b @if($urgensi && $aksi['giliranSaya']) style="color:var(--bad)" @endif>
      {{ Tampil::tgl($r->tenggat_jawab) }}
    </b>
    {{-- Kalau ada yang kurang, itu yang disebut; kalau tidak, kodenya. --}}
    <span class="mono">{{ $kurang ?: $r->kode }}</span>
  </span>

  <span class="buka">Buka <x-ik nama="panah-kanan" ukuran="14" /></span>
</a>
