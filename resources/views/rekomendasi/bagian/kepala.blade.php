@php
  use App\Support\Tampil;
  use App\Enums\HasilTelaah;

  /* Satuan kerja melihat tanggal yang mengikat dirinya — yang paling awal di
     antara tindak lanjut yang membebaninya. */
  $tgh = $balai ? $r->renaksiUntuk($u->satker_id) : $r->tenggat_jawab;
  $telatIni = $tgh && $tgh->copy()->startOfDay()->lt(now()->startOfDay()) && ! $r->tanpaTenggat();
  $nilai = $balai ? $baris->sum('nilai') : (int) $r->nilai_pulih;
  $dana = $r->progresDana($balai ? $u->satker_id : null);
  $drafSaya = $baris->map->draf->filter();
  $angsur = $r->rencanaAngsur();
  $bentuk = $baris->map(fn ($x) => $x->tindakan?->namaBentuk())->filter()->unique()->values();
  $keadaan = $r->keadaanUnor();

  $fakta = [
    ['l' => 'Status verifikasi', 'ket' => [
        'Keadaan rekomendasi ini menurut BPSDM: memadai kalau seluruh satuan kerja di dalamnya sudah memadai.',
        'Nomor surat CHV-nya ada di riwayat verifikasi di bawah. Suratnya rujukan dokumennya, bukan yang menentukan keadaannya — banyak rekomendasi lama yang sudah beres tapi nomor suratnya tidak pernah tercatat.',
        'Sampai Inspektorat hanya ada dua putusan: memadai dan belum memadai. Kode BS, BT, dan SS milik BPK, dan baru muncul sesudah berkasnya dinilai di SIPTL.',
        'Belum memadai adalah keadaan bakunya — termasuk selama suratnya belum terbit. Yang menjawab sudah diperiksa siapa adalah Posisi berkas.',
        'Satu satuan kerja belum beres membuat seluruh rekomendasi belum memadai, walau satuan kerja lain sudah.',
      ], 'v' => e($keadaan->nama($jenis)), 'c' => $keadaan === HasilTelaah::M ? 'var(--ok)' : 'var(--bad)'],
    ['l' => $balai ? 'Yang harus saya lakukan' : 'Bentuk tindak lanjut',
      'v' => e($bentuk->isEmpty() ? '—' : ($bentuk->count() === 1 ? $bentuk->first() : $bentuk->count().' tindak lanjut berbeda'))],
    ['l' => $balai ? 'Satuan kerja saya' : 'Satuan kerja dituju', 'v' => e($r->sebutSatker($peran, $u->satker_id))],
    ['l' => 'Rencana aksi', 'v' => Tampil::tgl($tgh), 'mono' => true, 'c' => $telatIni ? 'var(--verm)' : null,
      'k' => $telatIni ? Tampil::lamaTelat(\App\Models\Rekomendasi::selisih($tgh)) : null],
    ['l' => 'Target penyelesaian', 'mono' => true, 'v' => $r->target_selesai ? Tampil::tgl($r->target_selesai) : 'tidak ditetapkan'],
    ['l' => $balai ? 'Nilai yang harus saya pulihkan' : 'Nilai yang dipulihkan',
      'v' => $nilai ? Tampil::rupiah($nilai) : '—', 'mono' => true, 'c' => $nilai ? 'var(--bad)' : null],
    $dana ? ['l' => 'Sudah dipulihkan', 'v' => $dana['masuk'] ? Tampil::rupiah($dana['masuk']) : 'Rp 0', 'mono' => true,
      'c' => $dana['masuk'] ? 'var(--ok)' : 'var(--ink-3)'] : null,
    $dana ? ['l' => 'Sisa', 'v' => Tampil::rupiahSisa($dana['sisa']), 'mono' => true,
      'c' => $dana['sisa'] > 0 ? 'var(--bad)' : 'var(--ok)'] : null,
    $angsur ? ['l' => 'Rencana angsuran', 'v' => $angsur['sudah'].' dari '.$angsur['rencana'].' kali', 'k' => $angsur['kunci'] ? 'dikunci' : null] : null,
    $drafSaya->isNotEmpty() ? [
      'l' => $drafSaya->count() > 1 ? $drafSaya->count().' draf belum dikirim' : 'Draf belum dikirim',
      'v' => 'Tersimpan '.Tampil::tgl($drafSaya->map->terakhir->sort()->last()),
      'k' => $drafSaya->count() > 1 ? 'satu untuk tiap bentuk tindak lanjut' : 'belum berpindah dari satuan kerja'] : null,
  ];

  /* Catatan Setba milik TIAP tindak lanjut; satuan kerja cuma membaca catatan
     tindak lanjut yang dipikulnya. */
  $catatanTl = $r->tindakan->filter(fn ($tk) => trim((string) $tk->catatan) !== ''
    && (! $balai || $baris->contains('tindakan_id', $tk->id)));
@endphp

<div id="r-kepala" class="kepalarek">
  <div class="tanda">
    <span class="pil biru">Ref LHP <span class="asli">{{ $r->refLhp() ?: $r->kode }}</span></span>
    <x-sumber :j="$jenis" />
    <x-kode ket="Ref IDT" :isi="$r->refIdt()" />
  </div>

  <h2>{{ $r->uraian }}</h2>

  <div class="tanda kecil">
    <span class="pil">Temuan {{ $tem->nomor_pada_surat }}</span>
    <x-kode ket="Kode" :isi="$tem->kode" />
    <span class="jdltem">{{ $tem->judul }}</span>
  </div>

  <x-fakta :isi="$fakta" />

  @if($catatanTl->isNotEmpty())
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--rule-2)">
      <div class="lbl" style="margin-bottom:4px">Catatan Setba untuk satuan kerja</div>
      @foreach($catatanTl as $tk)
        <div style="font-size:13px;margin-bottom:4px">
          @if($r->tindakan->count() > 1)<b>{{ $tk->namaBentuk() }}: </b>@endif{{ $tk->catatan }}
        </div>
      @endforeach
    </div>
  @endif

  @if($r->alasanTd)
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--rule-2)">
      <div class="lbl" style="margin-bottom:4px">Alasan sah tidak dapat ditindaklanjuti</div>
      <div style="font-size:13px">{{ $r->alasanTd->nama }}</div>
      @if($r->catatan_td)<div style="font-size:13px;color:var(--ink-2);margin-top:4px">{{ $r->catatan_td }}</div>@endif
    </div>
  @endif
</div>
