@if($b->ditarik())
  <span class="berkas tarik">berkas ditarik &mdash; isi dan nama dihapus</span>
@else
  {{-- Ikon tautan, bukan ikon klip: sejak rapat 30 Agustus buktinya memang
       tautan ke arsip satuan kerja sendiri, bukan salinan yang diunggah ke
       sini. Menampilkannya seperti berkas terunggah membuat orang mengira
       salinannya ada di sistem ini. --}}
  <a class="berkas @if($b->berupaTautan()) tautan @endif"
    href="{{ route('berkas.show', $b) }}"
    title="{{ $b->berupaTautan() ? 'Tautan ke arsip satuan kerja' : 'Berkas terunggah' }}">
    {{ $b->nama_asli }}
  </a>
@endif
