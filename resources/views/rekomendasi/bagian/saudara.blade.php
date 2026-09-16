@php $u = auth()->user(); @endphp
{{-- Rekomendasi satu temuan saling berkaitan, dan pembacanya kerap perlu
     menyeberang ke sebelah. --}}
<span style="display:grid">
  @foreach($saudara as $s)
    <a class="tautrek" href="{{ route('rekomendasi.show', $s) }}">
      <span class="isi">
        {{ $s->uraian }}
        <span class="ket">
          <x-cap :s="$s->status" :jenis="$jenis" :rek="$s" />
          <span class="lbl" style="margin:0">{{ $s->sebutSatker($u->peran, $u->satker_id) }} · {{ $s->posisiRek()->label() }}</span>
        </span>
      </span>
      <x-ikon n="ChevronRight" :s="14" />
    </a>
  @endforeach
</span>
