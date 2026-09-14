@props(['rekomendasi', 'sumber'])

@php
  use App\Support\Tampil;

  $r = $rekomendasi;
  $tahap = Tampil::tahap($sumber);
@endphp

{{-- Rel tahap: sederet kotak, bukan sederet garis tipis. Yang dicari orang di
     sini bukan "sudah berapa persen", melainkan "sekarang di tahap mana, dan
     tahap itu artinya apa" — karena itu tiap tahap menyebut namanya sendiri
     berikut keadaannya. --}}
<div class="langkah">
  @foreach($tahap as $i => $nama)
    @php
      /* Rel tahap dibaca dari posisi yang tampil. Untuk rekomendasi yang
         dipikul beberapa satuan kerja, yang disebut adalah yang paling
         tertinggal — itu yang menentukan seberapa jauh berjalannya. */
      $posisi = $r->posisiTampil();
      $k = $posisi?->keadaanTahap($i + 1) ?? 'belum';
      $akhir = $i + 1 === count($tahap);

      /* Yang sudah dilewati cukup disebut begitu. Yang sedang berjalan dan yang
         menunggu dioper memakai kalimat posisinya sendiri — di situlah bedanya
         terbaca. */
      $ket = match (true) {
        $k === 'selesai' && $posisi === \App\Enums\PosisiBerkas::SELESAI && $akhir
          => $posisi->label(),
        $k === 'selesai' => 'Sudah dilewati',
        $k === 'belum'   => 'Belum dimulai',
        default          => $posisi?->label() ?? 'belum ditugaskan',
      };
    @endphp
    <div class="{{ $k }}">
      <span class="bul">{{ $k === 'selesai' ? '✓' : $i + 1 }}</span>
      <span>
        <span class="nm">{{ $nama }}</span>
        <span class="ket">{{ $ket }}</span>
      </span>
    </div>
  @endforeach
</div>
