@extends('rangka')
@section('judul', 'Masuk')
@section('isi')
<div class="masuk">
  <div class="kotak">
    <h1>Monitoring Tindak Lanjut</h1>
    <p class="sub">Sistem pemantauan tindak lanjut hasil pemeriksaan &middot; Sekretariat Badan</p>

    @if($errors->any())<div class="pesan bad">{{ $errors->first() }}</div>@endif

    <form method="post" action="{{ route('masuk') }}">
      @csrf
      <label class="f">
        <span class="lbl">Surel</span>
        <input type="email" name="email" id="surel" value="{{ old('email') }}" required autofocus>
      </label>
      <label class="f">
        <span class="lbl">Kata sandi</span>
        <input type="password" name="password" id="sandi" required>
      </label>
      <button class="btn btn-p" type="submit" style="width:100%;justify-content:center">Masuk</button>
    </form>

    <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--rule)">
      <div class="lbl">Akun contoh &middot; klik untuk mengisi</div>
      <div class="hint" style="margin-bottom:8px">
        Semuanya bersandi <span class="mono">rahasia123</span>. Bagian ini hanya ada pada
        pemasangan contoh dan dihapus sebelum dipakai sungguhan.
      </div>
      <div class="akun">
        @foreach($akun as $a)
          <button type="button" data-surel="{{ $a->email }}">
            <span>{{ $a->name }}</span>
            <span class="lbl">{{ $a->peran->value }}</span>
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
    });
  });
</script>
@endsection
