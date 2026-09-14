{{-- Meminta dokumen tambahan dari SATU satuan kerja. Tidak memindahkan berkas
     dan tidak mengubah status: ini permintaan kelengkapan, bukan keputusan.

     Butuh $s (Sasaran) dan $bentuk (nama bentuk tindak lanjut, boleh null). --}}
<details style="margin-top:14px;padding-top:12px;border-top:1px solid var(--rule-2)">
  <summary class="lbl" style="cursor:pointer">
    Minta dokumen tambahan dari {{ $s->satker?->namaPendek() ?? 'satuan kerja ini' }}
  </summary>
  <form method="post" action="{{ route('sasaran.minta', $s) }}" style="margin-top:11px">
    @csrf
    <div class="lbl" style="margin-bottom:6px">Dokumen yang diminta</div>
    @foreach(App\Support\UsulDokumen::untuk($bentuk) as $usul)
      <input type="text" name="item[]" value="{{ $usul }}"
        style="width:100%;margin-bottom:6px" placeholder="Nama dokumen">
    @endforeach
    <input type="text" name="item[]" value="" style="width:100%;margin-bottom:6px"
      placeholder="Tambahan lain — kosongkan bila tidak perlu">
    <div class="hint" style="margin-bottom:11px">
      Terisi usulan baku menurut bentuk tindak lanjutnya. Boleh diubah, dihapus, atau ditambah.
    </div>
    <label class="f">
      <span class="lbl">Alasan meminta &mdash; wajib diisi</span>
      <textarea name="alasan" required minlength="6" style="min-height:52px"
        placeholder="Sebutkan apa yang kurang dari berkas yang sudah masuk."></textarea>
    </label>
    <button class="btn" type="submit">Kirim permintaan</button>
    <div class="hint" style="margin-top:8px">
      Status dan posisi berkas tidak berubah. Permintaan tanpa alasan memaksa satuan kerja
      menebak apa yang kurang, dan tebakan yang salah berarti satu putaran bolak-balik lagi.
    </div>
  </form>
</details>
