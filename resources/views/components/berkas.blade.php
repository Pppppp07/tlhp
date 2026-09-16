@props(['b' => null, 'nama' => null, 'jenis' => null, 'oleh' => null, 'tanggal' => null, 'tautan' => null, 'penuh' => false])

@php
  /* Berkas dari model Lampiran, atau dari isian lepas (nama + tautan). */
  if ($b) {
      $nama ??= $b->nama_asli ?: $b->tautan;
      $jenis ??= $b->jenis();
      $oleh ??= $b->label_oleh;
      $tanggal ??= $b->diunggah_pada;
      $tautan ??= $b->tautan;
  }
  $data = [
      'nama' => $nama, 'jenis' => $jenis, 'oleh' => $oleh,
      'tanggal' => $tanggal ? \App\Support\Tampil::tgl($tanggal) : '', 'tautan' => $tautan,
  ];
@endphp

{{-- Berkasnya tidak disimpan di server — yang disimpan tautannya. Menekannya
     membuka pratinjau (skrip); tanpa skrip ia langsung membuka rute berkas
     yang terotorisasi, atau tautannya. --}}
@if($nama)
  <a class="berkas" @if($penuh) style="width:100%" @endif
    href="{{ $b ? route('berkas.show', $b) : ($tautan ?: '#') }}"
    data-pratinjau='@json($data)'
    title="{{ $tautan ? 'Buka tautan '.$nama : 'Buka '.$nama }}">
    <x-ikon :n="$tautan ? 'ExternalLink' : 'Paperclip'" :s="13" class="ic" />
    <span class="nm">{{ $nama }}</span>
    <span style="flex:1"></span>
    <x-ikon n="Eye" :s="13" class="ic" />
  </a>
@endif
