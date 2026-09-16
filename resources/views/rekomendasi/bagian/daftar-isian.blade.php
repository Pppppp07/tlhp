{{-- Daftar isian satu baris per butir — padanan DaftarIsian. Selalu ada
     setidaknya satu baris; baris kosong diabaikan saat disimpan. --}}
<div class="daftarisian" data-daftar-isian data-nama="{{ $nama }}">
  @foreach($nilai as $v)
    <div style="display:flex;gap:8px;margin-bottom:6px" data-baris-isian>
      <input type="text" name="{{ $nama }}[]" value="{{ $v }}" placeholder="{{ $placeholder }}">
      <button type="button" class="btn btn-s" aria-label="Hapus baris" data-hapus-isian @if(count($nilai) < 2) hidden @endif><x-ikon n="X" :s="12" /></button>
    </div>
  @endforeach
  <button type="button" class="btn btn-s" data-tambah-isian><x-ikon n="Plus" :s="12" /> {{ $tambah }}</button>
</div>
