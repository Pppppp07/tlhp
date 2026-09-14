@php
  use App\Support\Kabar;
  use App\Support\Tampil;

  $r = $k->rekomendasi;
  $tujuan = Kabar::BAGIAN[$k->blok] ?? null;
@endphp

{{-- Seluruh barisnya satu tombol: yang dibaca orang adalah kalimatnya, jadi
     kalimatnya yang harus bisa ditekan — bukan tautan kecil di ujungnya. --}}
<form method="post" action="{{ route('kabar.buka', $k) }}" style="margin-bottom:10px">
  @csrf
  <button type="submit" class="kartu" style="display:block;width:100%;text-align:left;cursor:pointer;
    border-left:3px solid {{ $redup ? 'var(--rule)' : 'var(--brass)' }};
    @if($redup) opacity:.62 @endif">
    <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:6px">
      @if($r)
        <span class="sumber sumber-{{ $r->temuan->laporan->sumber->value }}">{{ $r->temuan->laporan->sumber->value }}</span>
        <span class="mono" style="font-size:11px;color:var(--ink-3)">{{ $r->kode }}</span>
      @endif
      <div style="flex:1"></div>
      <span class="lbl">{{ $k->waktu->translatedFormat('d M Y H:i') }}</span>
    </div>

    <div style="font-size:13px;margin-bottom:5px">
      <b style="font-weight:600">{{ $k->label_pelaku }}</b> &mdash; {{ $k->aksi }}
    </div>

    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      <span class="lbl">
        @if($r){{ $r->temuan->judul }} &middot; {{ $r->daftarSasaran()->map(fn ($x) => $x->satker?->namaPendek())->filter()->join(', ') }}@endif
      </span>
      <div style="flex:1"></div>
      {{-- Menyebut tujuannya: tombol yang tidak menyebut ke mana ia mengantar
           membuat orang ragu menekannya, apalagi kabar yang selama ini cuma
           terbaca sebagai catatan. --}}
      @if($tujuan)
        <span class="lbl" style="color:var(--brass);font-weight:600">Buka {{ $tujuan }} &rsaquo;</span>
      @endif
    </div>
  </button>
</form>
