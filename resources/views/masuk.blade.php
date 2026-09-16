@extends('rangka')
@section('judul', 'Masuk')
@section('isi')
<div class="masuk">
  <div class="kotak">
    <div class="brand" style="display:flex;align-items:center;gap:11px;margin-bottom:18px">
      <span class="lambang" style="width:38px;height:38px;border-radius:50%;background:var(--aksen-kuat);color:#fff;display:grid;place-items:center;font-size:12px;font-weight:800">SB</span>
      <span>
        <b style="display:block;font-size:19px;font-weight:800;letter-spacing:-.02em;line-height:1.1">SETBA</b>
        <span style="display:block;font-size:10px;line-height:1.35;color:var(--ink-3)">Sistem Tindak Lanjut Hasil Pemeriksaan</span>
      </span>
    </div>
    <h1>Masuk</h1>
    <p class="sub">Pemantauan tindak lanjut LHP BPK dan LHA Inspektorat &middot; Sekretariat Badan</p>
    @if($errors->any())<div class="pesan bad"><span>{{ $errors->first() }}</span></div>@endif
    <form method="post" action="{{ route('masuk') }}">
      @csrf
      <label class="fld">
        <span>Surel</span>
        <input type="text" name="email" id="surel" value="{{ old('email') }}" required autofocus>
      </label>
      <label class="fld">
        <span>Kata sandi</span>
        <input type="password" name="password" id="sandi" required>
      </label>
      <button class="btn btn-p" type="submit" style="width:100%">Masuk</button>
    </form>
    <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--line)">
      <div class="lbl">Akun contoh &middot; klik untuk mengisi</div>
      {{-- Padanan pemilih "Masuk sebagai" di prototipe. Bagian ini hanya ada
           pada pemasangan contoh dan dihapus sebelum dipakai sungguhan. --}}
      <div class="hint" style="margin-bottom:8px">
        Semuanya bersandi <span class="mono">rahasia123</span>.
      </div>
      <div class="akun">
        @foreach($akun as $a)
          <button type="button" data-surel="{{ $a->email }}">
            <span>{{ $a->peran === \App\Enums\PeranPengguna::SATKER ? $a->satker?->namaPendek() : \App\Support\Rangka::pengguna($a)['peran'] }}</span>
            <span class="lbl">{{ $a->peran === \App\Enums\PeranPengguna::SATKER ? 'Satuan kerja' : $a->email }}</span>
          </button>
        @endforeach
      </div>
    </div>
  </div>
</div>
<script>
  document.querySelectorAll('.akun button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('surel').value = b.dataset.surel;
      document.getElementById('sandi').value = 'rahasia123';
      b.closest('.kotak').querySelector('form').requestSubmit();
    });
  });
</script>
@endsection
