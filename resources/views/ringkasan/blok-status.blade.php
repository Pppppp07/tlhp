@php
  use App\Support\PetaData;
  use App\Support\Tampil;

  /* Satu blok status: satu sumbu penilaian, tiap keranjangnya membawa jumlah
     dan rupiahnya sekaligus — padanan `BlokStatus`.

     Bang Kamal: "Sebetulnya cuma ada dua parameter kan? Total rekomendasi sama
     total nilai rekomendasi." Dua angka itu yang dipasang berpasangan di sini —
     di dasbor mereka keduanya memang selalu bersebelahan, jumlah di baris atas,
     rupiah di baris bawah. */
  $sisa = $sisa ?? null;
  $catatan = $catatan ?? null;
  $tugas = $tugas ?? null;
@endphp

<section class="blokstatus">
  <div class="kepalablok">
    <h3>{{ $judul }}</h3>
    <x-info :teks="[
      $ket,
      'Baris atas jumlah rekomendasi, baris bawah rupiahnya. Rupiahnya membagi habis nilai seluruh rekomendasi: yang sudah diakui masuk keranjang pertama, sisanya dipecah menurut keadaannya.',
      'Nilai bisa diakui sebagian — rekomendasi Rp 792 juta yang buktinya baru diterima Rp 192 juta menyisakan Rp 600 juta, walau statusnya belum berubah.',
    ]" />
  </div>

  <div class="deretstatus">
    <div class="selstatus utama">
      <div class="l">Rekomendasi</div>
      <div class="n">{{ $total }}</div>
      <div class="rp mono">{{ Tampil::rupiahSingkat($totalRp) }}</div>
    </div>
    @foreach($kolom as $c)
      <div class="selstatus">
        <div class="l"><i style="background:{{ $c['warna'] }}"></i>{{ $c['nama'] }}</div>
        <div class="n">{{ $c['n'] }}</div>
        <div class="rp mono">{{ Tampil::rupiahSingkat($c['rp']) }}</div>
        <span class="meter">
          <i style="width:{{ $total ? $c['n'] / $total * 100 : 0 }}%;background:{{ $c['warna'] }}"></i>
        </span>
        <div class="bg">{{ PetaData::bagian($c['n'], $total) }}</div>
      </div>
    @endforeach

    {{-- Sisa berdiri terpisah, dengan garis pemisah: ia BUKAN keranjang
         berikutnya, melainkan jumlah dari yang belum diakui.

         Mbak Puspi meminta ini tiga kali — "Dipisah aja antara totalnya sama
         sisanya", "kalau pimpinan buka kan dia langsung melihat sisanya",
         "kadang suka ditanya semuanya berapa, yang udah berapa." Sebelumnya
         angkanya ada tapi terpecah dua keranjang, dan pembacanya harus
         menjumlah sendiri di kepala. --}}
    @isset($sisa)
      <div class="selstatus sisa">
        <div class="l">Sisa nilai</div>
        <div class="n mono">{{ Tampil::rupiahSingkat($sisa) }}</div>
        <div class="rp">belum diakui</div>
        <span class="meter">
          <i style="width:{{ $totalRp ? $sisa / $totalRp * 100 : 0 }}%;background:var(--warn)"></i>
        </span>
        <div class="bg">{{ PetaData::bagian($sisa, $totalRp) }} dari nilainya</div>
      </div>
    @endisset
  </div>

  @if($catatan)
    <div class="hint" style="margin-top:12px">{!! $catatan !!}</div>
  @endif

  {{-- Penyebut kedua, di kakinya sendiri. 123 menghitung rekomendasi, 312
       menghitung penugasan — satu baris untuk tiap pasangan tindak lanjut dan
       satuan kerja. Inilah yang menjawab "rekomendasi ini nyangkut di satker
       mana", dan di lembar mereka angkanya berdiri sebagai "MEMADAI 303 /
       BELUM MEMADAI 9". --}}
  @if($tugas)
    <div class="kakitugas">
      <span class="lbl">
        Per penugasan
        <x-info :teks="[
          'Satu penugasan = satu satuan kerja pada satu bentuk tindak lanjut. Satu rekomendasi yang dipikul empat satuan kerja berisi empat penugasan.',
          'Angkanya lebih besar daripada jumlah rekomendasi, dan memang beda hal — di lembar pemantauan mereka 123 rekomendasi berisi 312 penugasan.',
          'Rekomendasi baru dinilai memadai kalau seluruh penugasannya memadai. Itu sebabnya jumlah rekomendasi yang belum memadai selalu lebih kecil daripada jumlah penugasannya.',
        ]" />
      </span>
      <span class="pita">
        @if($tugas['memadai'] > 0)
          <span style="flex-grow:{{ $tugas['memadai'] }};background:var(--ok)"><b>{{ $tugas['memadai'] }}</b></span>
        @endif
        @if($tugas['total'] - $tugas['memadai'] > 0)
          <span style="flex-grow:{{ $tugas['total'] - $tugas['memadai'] }};background:var(--bad)"><b>{{ $tugas['total'] - $tugas['memadai'] }}</b></span>
        @endif
      </span>
      <span class="ket">
        {{ $tugas['total'] }} penugasan · {{ $tugas['memadai'] }} {{ mb_strtolower($kataM) }}
        · {{ $tugas['total'] - $tugas['memadai'] }} {{ mb_strtolower($kataBM) }}
      </span>
    </div>
  @endif
</section>
