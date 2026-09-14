@props(['no', 'judul', 'teks' => null])

{{-- Halaman ini panjang dan isinya bermacam-macam. Tiga babak bernomor
     memberinya alur baca: apa perkaranya, sudah sampai mana, lalu apa yang
     bisa dikerjakan. Tanpa itu pembacanya menyusuri belasan kartu tanpa tahu
     mana yang menjawab pertanyaannya. --}}
<div class="babak">
  <span class="no">{{ $no }}</span>
  <b>{{ $judul }}</b>
  @if($teks)<x-info :teks="$teks" />@endif
</div>
