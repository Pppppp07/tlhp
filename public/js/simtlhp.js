/* Lapisan kenyamanan, padanan perilaku React di prototipe. Semuanya penambah,
   bukan penopang: tiap tugas tetap bisa dikerjakan kalau berkas ini gagal
   dimuat — saringan berupa formulir biasa, baris berupa tautan, berkas
   membuka rutenya sendiri.

   Ditulis polos tanpa perkakas build: berkas ini disalin apa adanya, sama
   seperti berkas gayanya. */
(function () {
  'use strict';

  function $(s, akar) { return (akar || document).querySelector(s); }
  function $$(s, akar) { return Array.prototype.slice.call((akar || document).querySelectorAll(s)); }
  function esc(t) {
    return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* Ikon yang dipakai kotak yang dibangun skrip. Jalurnya sama persis dengan
     komponen `x-ikon`, jadi kotak buatan skrip tidak berbeda wajah dari yang
     digambar peladen. */
  var IKON = {
    awas: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    cek: '<path d="M20 6 9 17l-5-5"/>',
    silang: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
  };
  function ikon(nama, ukuran) {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + ukuran + '" height="' + ukuran
      + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
      + ' stroke-linejoin="round" aria-hidden="true">' + (IKON[nama] || '') + '</svg>';
  }

  /* ================= keterangan (Info) ================= */
  /* Arah membuka dihitung saat ditekan, bukan dipatok: ikon di tepi layar
     akan melempar keterangannya ke luar pandangan kalau arahnya selalu sama. */
  function pasangInfo() {
    var terbuka = null;
    function tutup() {
      if (!terbuka) return;
      $('.isi', terbuka).hidden = true;
      $('button', terbuka).setAttribute('aria-expanded', 'false');
      terbuka = null;
    }
    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('.info > button');
      if (!tombol) {
        if (terbuka && !e.target.closest('.info')) tutup();
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      var wadah = tombol.parentElement;
      if (terbuka === wadah) { tutup(); return; }
      tutup();
      var isi = $('.isi', wadah);
      var r = wadah.getBoundingClientRect();
      isi.classList.toggle('atas', window.innerHeight - r.bottom < 190);
      isi.classList.toggle('kiri', window.innerWidth - r.left < 300);
      isi.hidden = false;
      tombol.setAttribute('aria-expanded', 'true');
      terbuka = wadah;
    }, true);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutup(); });
  }

  /* ================= batang atas yang menempel ================= */
  function pasangLekat() {
    var bilah = $('.top');
    if (!bilah) return;
    function ukur() {
      var atas = parseFloat(getComputedStyle(bilah).top) || 0;
      var satu = Math.round(atas + bilah.offsetHeight);
      document.body.style.setProperty('--lekat1', satu + 'px');
      document.body.style.setProperty('--lekat2', (satu + 44) + 'px');
    }
    ukur();
    if (window.ResizeObserver) new ResizeObserver(ukur).observe(bilah);
    window.addEventListener('resize', ukur);
  }

  function jarakLekat(tambah) {
    var v = parseFloat(getComputedStyle(document.body).getPropertyValue('--lekat1'));
    return (isFinite(v) ? v : 72) + (tambah == null ? 14 : tambah);
  }

  /* ================= laci navigasi di layar sempit ================= */
  function pasangLaci() {
    var nav = $('.nav'), tirai = $('.tirai-nav');
    if (!nav) return;
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-buka-nav]')) { nav.classList.add('buka'); tirai.classList.add('tampil'); }
      if (e.target.closest('[data-tutup-nav]')) { nav.classList.remove('buka'); tirai.classList.remove('tampil'); }
    });
  }

  /* ================= kabar yang disembulkan ================= */
  /* Menutup sendiri, kecuali sedang disentuh — orang yang baru mulai membaca
     tidak boleh kehilangan kalimatnya di tengah jalan. */
  function pasangSembul() {
    var kotak = $('[data-sembul]');
    if (!kotak) return;
    function lepas() { clearTimeout(jam); if (kotak.parentNode) kotak.parentNode.removeChild(kotak); }
    var jam = setTimeout(lepas, 12000);
    kotak.addEventListener('mouseenter', function () { clearTimeout(jam); });
    kotak.addEventListener('focusin', function () { clearTimeout(jam); });
    kotak.addEventListener('mouseleave', function () { clearTimeout(jam); jam = setTimeout(lepas, 4000); });
    $('[data-tutup-sembul]', kotak).addEventListener('click', lepas);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') lepas(); });
  }

  /* ================= blok yang dilipat ================= */
  /* Kepala blok (mis. "Sudah dibaca") membuka dan menutup isinya sendiri. */
  function pasangBlokLipat() {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-buka-blok]');
      if (!b) return;
      var isi = document.getElementById(b.getAttribute('aria-controls'));
      if (!isi) return;
      var buka = isi.hidden;
      isi.hidden = !buka;
      b.setAttribute('aria-expanded', buka ? 'true' : 'false');
      var panah = $('.panah', b);
      if (panah) panah.classList.toggle('buka', buka);
    });
  }

  /* ================= baris tabel yang membuka halaman ================= */
  function pasangBarisTautan() {
    document.addEventListener('click', function (e) {
      var tr = e.target.closest('tr[data-href]');
      if (!tr || e.target.closest('a, button, input, select, textarea, label, .info')) return;
      if (e.ctrlKey || e.metaKey) { window.open(tr.dataset.href, '_blank'); return; }
      location.href = tr.dataset.href;
    });
  }

  /* ================= saringan ================= */
  /* Pilihan terkirim begitu diganti. Kotak cari menyaring baris yang sudah ada
     sambil diketik — seperti prototipe — dan Enter mengirimnya ke peladen. */
  function pasangSaring() {
    document.addEventListener('change', function (e) {
      if (e.target.matches('[data-kirim]') && e.target.form) e.target.form.submit();
    });
    $$('[data-saring-langsung]').forEach(function (kotak) {
      var tabel = document.querySelector('.tw table');
      if (!tabel) return;
      kotak.addEventListener('input', function () {
        var kata = kotak.value.trim().toLowerCase();
        var n = 0, perlu = 0;
        $$('tbody tr[data-cari]', tabel).forEach(function (tr) {
          var cocok = !kata || tr.dataset.cari.indexOf(kata) >= 0;
          tr.hidden = !cocok;
          if (cocok) {
            n++;
            if (tr.dataset.perlu === '1') perlu++;
            var no = $('[data-no]', tr);
            if (no) no.textContent = n;
          }
        });
        var hint = $('[data-hitung-tampil]');
        if (hint) {
          $('[data-n-tampil]', hint).textContent = n;
          var b = $('[data-perlu]', hint);
          if (b) { b.hidden = !perlu; $('[data-n-perlu]', b).textContent = perlu; }
        }
        var kosong = $('tr[data-kosong]', tabel);
        if (kosong) kosong.hidden = n > 0;
      });
    });
  }

  /* ================= pencarian di batang atas ================= */
  function pasangCari() {
    var wadah = $('[data-cari-glob]');
    if (!wadah) return;
    var kotak = $('input', wadah), panel = $('.panel', wadah);
    var bersih = $('.bersih', wadah), kbd = $('kbd', wadah);
    var hasil = [], pilih = 0, jeda = null, nomor = 0;

    function tutup(kosongkan) {
      panel.hidden = true;
      if (kosongkan) { kotak.value = ''; kotak.blur(); segarkanTombol(); }
    }
    function segarkanTombol() { bersih.hidden = !kotak.value; kbd.hidden = !!kotak.value; }
    function gambar(data) {
      var kata = kotak.value.trim();
      hasil = [];
      if (kata.length < 2) { panel.hidden = true; return; }
      var h = '';
      if (!data.rek.length && !data.lap.length) {
        h = '<div class="kosong">Tidak ada yang cocok dengan "' + esc(kata) + '".</div>';
      } else {
        if (data.rek.length) h += '<div class="kel">Rekomendasi</div>';
        data.rek.forEach(function (x) {
          h += '<a class="item" href="' + esc(x.tautan) + '" data-i="' + hasil.length + '">' +
            '<span class="atas"><span class="mono kd">' + esc(x.kode) + '</span>' + x.cap + '</span>' +
            '<span class="jdl">' + esc(x.uraian) + '</span>' +
            '<span class="bwh">' + esc(x.satker) + ' &middot; Temuan ' + esc(x.nomorTemuan) + '</span></a>';
          hasil.push(x.tautan);
        });
        if (data.lap.length) h += '<div class="kel">Laporan</div>';
        data.lap.forEach(function (l) {
          h += '<a class="item" href="' + esc(l.tautan) + '" data-i="' + hasil.length + '">' +
            '<span class="atas"><span class="mono kd">' + esc(l.nomor) + '</span>' + l.sumber + '</span>' +
            '<span class="jdl">' + l.satker + '</span>' +
            '<span class="bwh">' + l.temuan + ' temuan &middot; diterima ' + esc(l.diterima) + '</span></a>';
          hasil.push(l.tautan);
        });
        if (data.jumlah > hasil.length) {
          h += '<div class="kosong">' + (data.jumlah - hasil.length) + ' hasil lain tidak ditampilkan. Persempit kata pencarinya.</div>';
        }
      }
      panel.innerHTML = h;
      pilih = 0;
      tandai();
      panel.hidden = false;
    }
    function tandai() {
      $$('.item', panel).forEach(function (a, i) { a.classList.toggle('pilih', i === pilih); });
    }
    function muat() {
      var kata = kotak.value.trim();
      segarkanTombol();
      if (kata.length < 2) { panel.hidden = true; return; }
      var ke = ++nomor;
      fetch(wadah.dataset.sumber + '?q=' + encodeURIComponent(kata), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (ke === nomor) gambar(d); });
    }
    kotak.addEventListener('input', function () { clearTimeout(jeda); jeda = setTimeout(muat, 150); });
    kotak.addEventListener('focus', function () { if (kotak.value.trim().length >= 2) muat(); });
    kotak.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { tutup(true); return; }
      if (!hasil.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); pilih = (pilih + 1) % hasil.length; tandai(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); pilih = (pilih - 1 + hasil.length) % hasil.length; tandai(); }
      if (e.key === 'Enter') { e.preventDefault(); location.href = hasil[pilih]; }
    });
    panel.addEventListener('mousemove', function (e) {
      var a = e.target.closest('.item');
      if (a) { pilih = parseInt(a.dataset.i, 10); tandai(); }
    });
    bersih.addEventListener('click', function () { tutup(true); });
    document.addEventListener('mousedown', function (e) { if (!wadah.contains(e.target)) tutup(false); });
    /* Pintasan papan tik: "/" di luar isian, atau Ctrl+K. */
    document.addEventListener('keydown', function (e) {
      var diKetikan = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName || '') || e.target.isContentEditable;
      if ((e.key === '/' && !diKetikan) || ((e.key === 'k' || e.key === 'K') && (e.ctrlKey || e.metaKey))) {
        e.preventDefault();
        kotak.focus();
      }
    });
    segarkanTombol();
  }

  /* ================= pratinjau berkas ================= */
  function pasangPratinjau() {
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-pratinjau]');
      if (!a || e.ctrlKey || e.metaKey) return;
      e.preventDefault();
      e.stopPropagation();
      var b = JSON.parse(a.dataset.pratinjau);
      var ext = ((b.nama || '').split('.').pop() || '').toLowerCase();
      var gambarIni = ['jpg', 'jpeg', 'png'].indexOf(ext) >= 0;
      var lembarKerja = ['xlsx', 'xls', 'csv'].indexOf(ext) >= 0;
      var garis = lembarKerja
        ? '<div class="garis p8"></div><div class="garis"></div><div class="garis p6"></div><div class="garis"></div><div class="garis p8"></div><div class="garis p4"></div>'
        : '<div class="garis p8"></div><div class="garis"></div><div class="garis p6"></div><div class="kotak"></div><div class="garis"></div><div class="garis p8"></div><div class="garis p4"></div>';
      var tirai = document.createElement('div');
      tirai.className = 'tirai';
      tirai.setAttribute('role', 'dialog');
      tirai.setAttribute('aria-modal', 'true');
      tirai.innerHTML =
        '<div class="lembar">' +
          '<div class="kep">' +
            '<div style="min-width:0;flex:1"><div class="mono" style="font-size:12.5px;word-break:break-all">' + esc(b.nama) + '</div>' +
            '<div class="lbl" style="margin-top:2px">' + esc(b.jenis || '') + (b.oleh ? ' · diunggah ' + esc(b.oleh) : '') + (b.tanggal ? ' · ' + esc(b.tanggal) : '') + '</div></div>' +
            '<button class="btn btn-s" type="button" data-tutup>Tutup</button>' +
          '</div>' +
          '<div class="bdn"><div class="halaman"><div class="cap-air"><span>CONTOH<br>BUKAN DOKUMEN ASLI</span></div>' +
            '<div class="lbl" style="margin-bottom:14px">' + (gambarIni ? 'Pindaian' : lembarKerja ? 'Lembar kerja' : 'Dokumen') + ' · ' + esc(ext.toUpperCase()) + '</div>' +
            '<div style="font-size:13px;font-weight:600;margin-bottom:16px">' + esc(b.jenis || '') + '</div>' + garis +
          '</div><div class="lbl" style="text-align:center;margin-top:14px;line-height:1.6">halaman 1 dari 1</div></div>' +
          '<div class="kak"><div class="lbl" style="flex:1;min-width:120px;display:flex;align-items:center;gap:7px;flex-wrap:wrap">Pratinjau berkas' +
            (b.tautan ? '<span class="mono" style="font-size:11px;color:var(--ink-3);word-break:break-all">' + esc(b.tautan) + '</span>' : '') + '</div>' +
            (b.tautan ? '<a class="btn btn-s" href="' + esc(b.tautan) + '" target="_blank" rel="noreferrer">Buka tautan</a>'
                      : '<a class="btn btn-s" href="' + esc(a.getAttribute('href')) + '">Buka berkas</a>') +
          '</div>' +
        '</div>';
      function tutup() { if (tirai.parentNode) tirai.parentNode.removeChild(tirai); document.removeEventListener('keydown', escTutup); }
      function escTutup(ev) { if (ev.key === 'Escape') tutup(); }
      tirai.addEventListener('click', function (ev) {
        if (ev.target === tirai || ev.target.closest('[data-tutup]')) tutup();
      });
      document.addEventListener('keydown', escTutup);
      document.body.appendChild(tirai);
    });
  }

  /* ================= penegasan sebelum tindakan yang tidak bisa ditarik ================= */
  /* Tombol kirim bertanda data-pastikan menampilkan lembar tanya lebih dulu.
     Tanpa skrip formulirnya langsung terkirim — penjaganya tetap di peladen. */
  function pasangPastikan() {
    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('[data-pastikan]');
      if (!tombol || tombol.dataset.sudah === '1') return;
      var form = tombol.form || tombol.closest('form');
      if (!form) return;
      if (!form.reportValidity()) return;
      e.preventDefault();
      var isi = JSON.parse(tombol.dataset.pastikan);
      var nada = isi.nada || 'biru';
      var kelas = nada === 'merah' ? 'merah' : nada === 'hijau' ? 'hijau' : nada === 'kuning' ? 'kuning' : '';
      var tirai = document.createElement('div');
      tirai.className = 'tirai';
      tirai.innerHTML =
        '<div class="lembar tanya" role="dialog" aria-modal="true">' +
          '<div class="kep"><span class="ic-kotak besar ' + kelas + '">' + ikon('awas', 20) + '</span><b>' + esc(isi.judul) + '</b></div>' +
          '<div class="bdn"><p class="apa">' + esc(isi.ket) + '</p>' + (isi.rincian || '') +
            (isi.balik === false ? '' : '<div class="pesan warn" style="margin-top:16px;margin-bottom:0"><span>Setelah ditekan, tindakan ini tidak bisa ditarik sendiri.</span></div>') +
          '</div>' +
          '<div class="kak">' +
            '<button type="button" class="btn ' + (nada === 'hijau' ? 'btn-ok' : 'btn-p') + '" data-ya>' + ikon('cek', 15) + ' ' + esc(isi.tombol || 'Ya, lanjutkan') + '</button>' +
            '<button type="button" class="btn" data-batal>' + ikon('silang', 15) + ' Batal, periksa lagi</button>' +
          '</div>' +
        '</div>';
      function tutup() { if (tirai.parentNode) tirai.parentNode.removeChild(tirai); }
      tirai.addEventListener('click', function (ev) {
        if (ev.target === tirai || ev.target.closest('[data-batal]')) tutup();
        if (ev.target.closest('[data-ya]')) {
          tutup();
          tombol.dataset.sudah = '1';
          if (form.requestSubmit) form.requestSubmit(tombol); else tombol.click();
        }
      });
      document.body.appendChild(tirai);
    });
  }

  /* ================= peta data (Ringkasan) ================= */
  /* Panelnya digambar peladen; yang dikerjakan di sini cuma menggantinya tanpa
     memuat ulang seluruh halaman — mengganti satu pilihan di panel kedelapan
     tidak boleh melemparkan pembacanya kembali ke puncak halaman.

     Tanpa berkas ini panelnya tetap bisa dipakai: formulirnya formulir biasa,
     dan tombol "Terapkan pilihan panel" mengirimnya. */
  function pasangPeta() {
    var form = $('form[data-peta]');
    if (!form) return;
    $$('[data-tanpa-js]', form).forEach(function (x) { x.hidden = true; });

    function kirim(tombol) {
      var q = new URLSearchParams();
      new FormData(form).forEach(function (v, k) { q.append(k, v); });
      if (tombol && tombol.name) q.append(tombol.name, tombol.value);
      var alamat = form.action + '?' + q.toString();
      form.setAttribute('aria-busy', 'true');
      fetch(alamat, { credentials: 'same-origin' })
        .then(function (r) { return r.text().then(function (t) { return { alamat: r.url, teks: t }; }); })
        .then(function (hasil) {
          var baru = new DOMParser().parseFromString(hasil.teks, 'text/html')
            .querySelector('form[data-peta]');
          if (!baru) { location.href = alamat; return; }
          form.innerHTML = baru.innerHTML;
          $$('[data-tanpa-js]', form).forEach(function (x) { x.hidden = true; });
          history.replaceState(null, '', hasil.alamat);
        })
        .catch(function () { location.href = alamat; })
        .then(function () { form.removeAttribute('aria-busy'); });
    }

    form.addEventListener('change', function (e) {
      if (e.target.matches('select')) kirim(null);
    });
    /* Saklar lingkup sengaja dibiarkan mengirim seperti biasa: angka di menu
       kiri ikut berubah karenanya, dan itu di luar panel ini. */
    form.addEventListener('click', function (e) {
      var b = e.target.closest('button[name="aksi"]');
      if (!b) return;
      e.preventDefault();
      kirim(b);
    });
  }

  /* Keterangan melayang di atas batang dan potongan donat. Nilainya sudah
     tertulis di sebelah batang, jadi yang ditambahkan justru yang tidak muat:
     bagiannya terhadap seluruhnya, dan pecahan statusnya. */
  function pasangPetunjuk() {
    var kotak = null;
    function tutup() {
      if (kotak && kotak.parentNode) kotak.parentNode.removeChild(kotak);
      kotak = null;
    }
    document.addEventListener('mousemove', function (e) {
      var el = e.target.closest ? e.target.closest('[data-tunjuk]') : null;
      if (!el) { tutup(); return; }
      if (!kotak) {
        kotak = document.createElement('div');
        kotak.className = 'petunjuk';
        document.body.appendChild(kotak);
      }
      var pecah = [];
      try { pecah = JSON.parse(el.dataset.pecah || '[]'); } catch (x) { pecah = []; }
      var isi = '<b>' + esc(el.dataset.nama) + '</b><span>' + esc(el.dataset.nilai) + '</span>';
      if (el.dataset.bagi) isi += '<span class="bagi">' + esc(el.dataset.bagi) + ' dari seluruhnya</span>';
      if (pecah.length > 1) {
        isi += '<span class="pecah">' + pecah.map(function (p) {
          return '<span><i style="background:' + esc(p.w) + '"></i>' + esc(p.s) + ' ' + esc(p.n) + '</span>';
        }).join('') + '</span>';
      }
      kotak.innerHTML = isi;
      kotak.style.left = e.clientX + 'px';
      kotak.style.top = e.clientY + 'px';
    });
    document.addEventListener('mouseleave', tutup);
    window.addEventListener('scroll', tutup, true);
  }

  /* ================= formulir Catat laporan baru ================= */
  /* Melipat kelompok isian, membuka satu rekomendasi pada satu waktu, dan
     menyalakan baris nilai begitu satuan kerjanya dicentang. Semuanya
     kenyamanan: tanpa berkas ini isiannya tetap terkirim — yang tertutup pun
     ikut, karena disembunyikan, bukan dibuang. */
  function pasangFormBaru() {
    /* Kelompok isian yang bisa dilipat (Grup pada prototipe). */
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-buka-grup]');
      if (!b) return;
      var grup = b.closest('[data-grup]');
      var isi = $('.isi', grup);
      var buka = !grup.classList.contains('buka');
      grup.classList.toggle('buka', buka);
      b.setAttribute('aria-expanded', buka ? 'true' : 'false');
      var panah = $('.panah', b);
      if (panah) panah.classList.toggle('buka', buka);
      if (isi) isi.hidden = !buka;
    });

    /* Satu rekomendasi terbuka pada satu waktu: satu temuan bisa punya banyak
       rekomendasi dan tiap satunya belasan isian. */
    document.addEventListener('click', function (e) {
      if (e.target.closest('button[name="aksi"]')) return;
      var kep = e.target.closest('[data-buka-rek]');
      if (!kep) return;
      var blok = kep.closest('[data-blok-rek]');
      var buka = !blok.classList.contains('buka');
      $$('[data-blok-rek]').forEach(function (lain) {
        if (lain !== blok) aturRek(lain, false);
      });
      aturRek(blok, buka);
    });

    function aturRek(blok, buka) {
      blok.classList.toggle('buka', buka);
      var kep = $('[data-buka-rek]', blok);
      kep.setAttribute('aria-expanded', buka ? 'true' : 'false');
      $('.panah', kep).classList.toggle('buka', buka);
      var ringkas = $('.ringkas', kep);
      if (ringkas) ringkas.hidden = buka;
      $('.isi', blok).hidden = !buka;
    }

    /* Baris nilai muncul untuk satuan kerja yang dicentang saja, dan hanya
       kalau rekomendasinya memang menuntut uang. */
    function aturNilai(tindak) {
      var kotak = $('[data-nilai-tindak]', tindak);
      if (!kotak) return;
      var dipilih = 0;
      $$('[data-pilih-satker]', tindak).forEach(function (c) {
        var baris = $('[data-nilai-satker="' + c.value + '"]', kotak);
        if (baris) baris.hidden = !c.checked;
        if (c.checked) dipilih++;
      });
      var sifat = $('[data-sifat]', tindak.closest('[data-blok-rek]'));
      var uang = !sifat || sifat.value !== sifat.dataset.administratif;
      kotak.hidden = !dipilih || !uang;
    }

    document.addEventListener('change', function (e) {
      if (e.target.matches('[data-pilih-satker]')) {
        var tindak = e.target.closest('[data-tindak]');
        if (tindak) aturNilai(tindak);
      }
      if (e.target.matches('[data-sifat]')) {
        $$('[data-tindak]', e.target.closest('[data-blok-rek]')).forEach(aturNilai);
      }
    });

    /* Pemilih satuan kerja yang panjang: daftar sisanya di balik pencarian. */
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-buka-daftar-satker]');
      if (!b) return;
      var daftar = $('.daftarpilih', b.closest('[data-pilih-cari]'));
      daftar.hidden = !daftar.hidden;
      b.textContent = daftar.hidden ? b.dataset.buka : b.dataset.tutup;
    });

    document.addEventListener('input', function (e) {
      if (!e.target.matches('[data-cari-satker]')) return;
      var wadah = e.target.closest('[data-pilih-cari]');
      var daftar = $('.daftarpilih', wadah);
      var kata = e.target.value.trim().toLowerCase();
      var n = 0;
      $$('.kepingpilih', daftar).forEach(function (k) {
        var cocok = !kata || (k.dataset.nama || '').indexOf(kata) >= 0;
        k.hidden = !cocok;
        if (cocok) n++;
      });
      $('[data-tak-cocok]', daftar).hidden = n > 0;
      if (kata) {
        daftar.hidden = false;
        var b = $('[data-buka-daftar-satker]', wadah);
        b.textContent = b.dataset.tutup;
      }
    });
  }

  /* ================= penanda saat mendarat ================= */
  /* `?sorot=blok` atau jangkar #blok: bagian yang dituju digulir ke bawah
     batang atas dan dibingkai sekejap, supaya mata tahu bagian mana yang
     dimaksud di antara sekian kartu yang bentuknya mirip. */
  function pasangSorot() {
    var p = new URLSearchParams(location.search);
    var id = p.get('sorot') || (location.hash ? location.hash.slice(1) : '');
    if (!id) return;
    var el = document.getElementById(id);
    if (!el) return;
    /* Bagian di balik tab dibukakan tabnya dulu. */
    if (el.dataset.isiTab) aktifkanTab(el.dataset.isiTab);
    var nada = p.get('nada') || '';
    var kartu = p.get('kartu') ? document.getElementById(p.get('kartu')) : null;
    var sasaran = kartu || el;
    setTimeout(function () {
      var tujuan = Math.max(0, window.scrollY + sasaran.getBoundingClientRect().top - jarakLekat(sasaran === el ? 16 : 78));
      window.scrollTo({ top: tujuan, behavior: 'smooth' });
    }, 260);
    sasaran.classList.add('sorot');
    if (nada) sasaran.classList.add(nada);
    setTimeout(function () { sasaran.classList.remove('sorot'); if (nada) sasaran.classList.remove(nada); }, 4000);
  }

  /* ================= halaman rincian ================= */

  function gulirDanSorot(el, nada) {
    if (!el) return;
    var tujuan = Math.max(0, window.scrollY + el.getBoundingClientRect().top - jarakLekat(16));
    window.scrollTo({ top: tujuan, behavior: 'smooth' });
    el.classList.add('sorot');
    if (nada) el.classList.add(nada);
    setTimeout(function () { el.classList.remove('sorot'); if (nada) el.classList.remove(nada); }, 4200);
  }

  /* Tiket tiap bentuk tindak lanjut: satu terbuka pada satu waktu, dan tidak
     ada yang terbuka sejak awal. */
  function pasangTiket() {
    var semua = $$('[data-tiket]');
    function atur(t, buka) {
      var tombol = $('[data-buka-tiket]', t);
      $('[data-isi-tiket]', t).hidden = !buka;
      tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
      t.classList.toggle('tiket-terbuka', buka);
      $('[data-ajak-buka]', t).hidden = buka;
      $('[data-ajak-tutup]', t).hidden = !buka;
      if (buka) {
        var tabel = $('[data-tabel-tl]', t);
        if (tabel && tabel.dataset.satu === '1') {
          var baris = $('tr[data-baris-tl]', tabel);
          if (baris && !baris.classList.contains('buka')) bukaBarisTl(baris, 'bukti');
        }
      }
    }
    semua.forEach(function (t) { atur(t, false); });
    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('[data-buka-tiket]');
      if (!tombol) return;
      var t = tombol.closest('[data-tiket]');
      var buka = tombol.getAttribute('aria-expanded') !== 'true';
      semua.forEach(function (lain) { if (lain !== t) atur(lain, false); });
      atur(t, buka);
    });
  }

  function detailTl(baris) { return document.querySelector('tr[data-rinci-tl="' + baris.dataset.barisTl + '"]'); }

  function aturTombolTl(baris, buka, tab) {
    var adaAksi = baris.dataset.adaAksi === '1';
    var tutupKah = buka && (!adaAksi || tab === 'kerja');
    var k = $('[data-kerjakan]', baris);
    $('[data-label-tutup]', k).hidden = !tutupKah;
    $('[data-label-buka]', k).hidden = tutupKah;
    k.setAttribute('aria-expanded', buka ? 'true' : 'false');
    $('[data-buka-baris]', baris).setAttribute('aria-expanded', buka ? 'true' : 'false');
  }

  function gantiTabTl(baris, tab) {
    var d = detailTl(baris);
    baris.dataset.tab = tab;
    $$('[data-tab-tl]', d).forEach(function (b) { b.setAttribute('aria-selected', b.dataset.tabTl === tab ? 'true' : 'false'); });
    $$('[data-panel-tl]', d).forEach(function (p) { p.hidden = p.dataset.panelTl !== tab; });
    var perbaikan = $('[data-perbaikan]', d);
    if (perbaikan) perbaikan.hidden = tab === 'kerja';
    aturTombolTl(baris, true, tab);
  }

  function tutupBarisTl(baris) {
    baris.classList.remove('buka');
    detailTl(baris).hidden = true;
    aturTombolTl(baris, false, baris.dataset.tab || 'bukti');
  }

  function bukaBarisTl(baris, tab) {
    var tabel = baris.closest('table');
    $$('tr[data-baris-tl]', tabel).forEach(function (lain) { if (lain !== baris && lain.classList.contains('buka')) tutupBarisTl(lain); });
    baris.classList.add('buka');
    detailTl(baris).hidden = false;
    gantiTabTl(baris, tab);
  }

  function pasangTabelTl() {
    $$('tr[data-baris-tl]').forEach(function (baris) { tutupBarisTl(baris); });
    document.addEventListener('click', function (e) {
      var tab = e.target.closest('[data-tab-tl]');
      if (tab) {
        var d = tab.closest('tr[data-rinci-tl]');
        var baris = document.querySelector('tr[data-baris-tl="' + d.dataset.rinciTl + '"]');
        gantiTabTl(baris, tab.dataset.tabTl);
        return;
      }
      var kerjakan = e.target.closest('[data-kerjakan]');
      if (kerjakan) {
        var b = kerjakan.closest('tr[data-baris-tl]');
        var adaAksi = b.dataset.adaAksi === '1';
        var terbuka = b.classList.contains('buka');
        if (terbuka && (!adaAksi || b.dataset.tab === 'kerja')) tutupBarisTl(b);
        else bukaBarisTl(b, adaAksi ? 'kerja' : 'bukti');
        return;
      }
      var baris2 = e.target.closest('tr[data-baris-tl]');
      if (baris2 && (e.target.closest('[data-buka-baris]') || !e.target.closest('a, button, input, select, textarea, label'))) {
        if (baris2.classList.contains('buka')) tutupBarisTl(baris2); else bukaBarisTl(baris2, 'bukti');
        return;
      }
      var riwayat = e.target.closest('[data-lihat-riwayat]');
      if (riwayat) {
        var kartu = pilihRiwayat(riwayat.dataset.lihatRiwayat);
        gulirDanSorot(kartu, '');
        return;
      }
      var tunjuk = e.target.closest('[data-tunjuk]');
      if (tunjuk) {
        e.preventDefault();
        gulirDanSorot(document.getElementById(tunjuk.dataset.tunjuk), '');
      }
    });
  }

  /* Kartu riwayat menyorot satu pasangan tindak lanjut dan satuan kerja. */
  function pilihRiwayat(kunci) {
    var kartu = $$('[data-riwayat-lingkup]');
    var tampil = null;
    kartu.forEach(function (k, i) {
      var cocok = k.dataset.riwayatLingkup === kunci;
      k.hidden = !cocok;
      k.id = cocok ? 'r-riwayat' : 'r-riwayat-' + i;
      if (cocok) tampil = k;
    });
    if (!tampil && kartu.length) { kartu[0].hidden = false; kartu[0].id = 'r-riwayat'; tampil = kartu[0]; }
    return tampil;
  }

  function pasangRiwayat() {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-pilih-riwayat]');
      if (b) pilihRiwayat(b.dataset.pilihRiwayat);
      var potong = e.target.closest('[data-catatan-potong]');
      if (potong) potong.classList.toggle('potong');
    });
  }

  function aktifkanTab(nama) {
    $$('[data-tab-rincian] [data-tab]').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.tab === nama ? 'true' : 'false'); });
    $$('[data-isi-tab]').forEach(function (p) { p.hidden = p.dataset.isiTab !== nama; });
  }

  function pasangTabRincian() {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-tab-rincian] [data-tab]');
      if (b) aktifkanTab(b.dataset.tab);
    });
  }

  /* Isian yang boleh dikosongkan tidak ikut memenuhi panel sejak awal. */
  function pasangOpsional() {
    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('[data-opsional] > button');
      if (!tombol) return;
      var wadah = tombol.parentElement, isi = $('.isi', wadah);
      var buka = isi.hidden;
      isi.hidden = !buka;
      $('[data-opsional-ikon-buka]', wadah).hidden = buka;
      $('[data-opsional-ikon-tutup]', wadah).hidden = !buka;
      var ket = $('[data-opsional-ket]', wadah);
      if (ket) {
        var terisi = $$('input, textarea', isi).some(function (i) { return i.value.trim(); });
        ket.hidden = buka;
        if (!buka && terisi) { ket.textContent = 'sudah terisi'; ket.className = 'lbl'; }
      }
    });
  }

  /* Daftar isian satu baris per butir. */
  function pasangDaftarIsian() {
    function atur(d) {
      var baris = $$('[data-baris-isian]', d);
      baris.forEach(function (b) { $('[data-hapus-isian]', b).hidden = baris.length < 2; });
    }
    document.addEventListener('click', function (e) {
      var tambah = e.target.closest('[data-tambah-isian]');
      if (tambah) {
        var d = tambah.closest('[data-daftar-isian]');
        var contoh = $('[data-baris-isian]', d);
        var baru = contoh.cloneNode(true);
        $('input', baru).value = '';
        d.insertBefore(baru, tambah);
        atur(d);
        $('input', baru).focus();
        d.dispatchEvent(new Event('input', { bubbles: true }));
        return;
      }
      var hapus = e.target.closest('[data-hapus-isian]');
      if (hapus) {
        var d2 = hapus.closest('[data-daftar-isian]');
        hapus.closest('[data-baris-isian]').remove();
        atur(d2);
        d2.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  }

  /* Menu aksi satu baris: letaknya dihitung dari tombolnya lalu dipasang
     `position: fixed`, supaya tidak terpotong wadah tabel yang bergulir. */
  function pasangMenuAksi() {
    var terbuka = null;
    function tutup() {
      if (!terbuka) return;
      $('.daftaraksi', terbuka).hidden = true;
      $('button', terbuka).setAttribute('aria-expanded', 'false');
      terbuka = null;
    }
    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('[data-menu-aksi] > button');
      if (tombol) {
        var wadah = tombol.parentElement;
        if (terbuka === wadah) { tutup(); return; }
        tutup();
        var daftar = $('.daftaraksi', wadah);
        var r = tombol.getBoundingClientRect();
        var tinggi = $$('button', daftar).length * 38 + 12;
        daftar.style.left = Math.max(8, Math.min(r.right - 215, window.innerWidth - 223)) + 'px';
        daftar.style.top = (window.innerHeight - r.bottom < tinggi + 16 ? Math.max(8, r.top - tinggi - 6) : r.bottom + 4) + 'px';
        daftar.hidden = false;
        tombol.setAttribute('aria-expanded', 'true');
        terbuka = wadah;
        return;
      }
      if (terbuka && !e.target.closest('[data-menu-aksi]')) tutup();
      if (e.target.closest('.daftaraksi > button')) tutup();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') tutup(); });
    window.addEventListener('scroll', tutup, true);
  }

  function hitungHuruf(el) {
    var n = el.parentElement.querySelector('[data-jumlah-huruf]');
    if (n) n.textContent = el.value.length + '/500';
  }

  /* Urusan SIPTL: satu baris dikerjakan pada satu waktu. */
  function pasangSuntingSiptl() {
    function tutupSemua() { $$('[data-sunting-siptl]').forEach(function (t) { t.hidden = true; }); }
    function aturSiptl(f) {
      var hari = f.dataset.hari, naik = f.dataset.siapNaik === '1';
      var tombol = $('[data-simpan-siptl]', f), ket = $('[data-ket-siptl]', f);
      var satker = f.dataset.satker, siap, pesan = '';
      if (naik) {
        var tg = $('[data-tanggal-unggah]', f).value;
        siap = !!tg && tg <= hari;
        pesan = !tg ? 'Tanggal unggah harus terisi' : (tg > hari ? 'Tanggal unggah tidak boleh melewati hari ini' : '');
        tombol.dataset.pastikan = JSON.stringify({
          judul: 'Catat unggahan ' + satker + ' ke SIPTL tanggal ' + tglId(tg) + '?',
          ket: 'Tanggal ini dikunci begitu dicatat dan tidak bisa diubah lagi — pastikan sama dengan tanggal unggah di SIPTL. Statusnya otomatis Belum Ditindaklanjuti sampai BPK memutus.',
          tombol: 'Ya, catat unggahannya', nada: 'hijau' });
      } else {
        var pilih = $('[data-status-pilih]', f).value;
        var catatan = $('textarea[name="catatan"]', f).value.trim();
        $('[data-bila-pilih]', f).hidden = !pilih;
        $('[data-akibat-bs]', f).hidden = pilih !== 'BS';
        $('[data-wajib-catatan]', f).textContent = pilih === 'BS' ? '— wajib diisi' : '— boleh dikosongkan';
        $$('[data-kartu-status]', f).forEach(function (k) { k.setAttribute('aria-pressed', k.dataset.kartuStatus === pilih ? 'true' : 'false'); });
        siap = !!pilih && !(pilih === 'BS' && !catatan);
        pesan = !pilih ? 'Pilih hasil pemantauannya. Kalau BPK belum memutus, tekan Batal.' : (pilih === 'BS' && !catatan ? 'Catatan BPK harus terisi' : '');
        tombol.dataset.pastikan = JSON.stringify({
          judul: 'Catat status ' + pilih + ' untuk ' + satker + '?',
          ket: pilih === 'SS'
            ? 'Tindak lanjut satuan kerja ini berhenti dipantau. Rekomendasinya baru tertutup kalau seluruh satuan kerjanya sudah begitu.'
            : 'Status SIPTL tercatat untuk satuan kerja ini. Yang lain tidak ikut berubah.',
          tombol: 'Ya, catat ' + pilih, nada: pilih === 'SS' ? 'hijau' : 'jingga' });
      }
      tombol.disabled = !siap;
      ket.textContent = pesan;
    }
    document.addEventListener('click', function (e) {
      var buka = e.target.closest('[data-buka-sunting]');
      if (buka) {
        tutupSemua();
        var tr = document.getElementById(buka.dataset.bukaSunting);
        tr.hidden = false;
        $$('[data-mode-sunting]', tr).forEach(function (f) {
          var cocok = f.dataset.modeSunting === buka.dataset.mode;
          f.hidden = !cocok;
          if (cocok) { f.reset(); $$('[data-hitung-huruf]', f).forEach(hitungHuruf); if (f.matches('[data-form-siptl]')) aturSiptl(f); if (f.matches('[data-form-ulang-bpk]')) aturUlangBpk(f); }
        });
        return;
      }
      if (e.target.closest('[data-tutup-sunting]')) { tutupSemua(); return; }
      var kartu = e.target.closest('[data-kartu-status]');
      if (kartu) {
        var f = kartu.closest('form'), input = $('[data-status-pilih]', f);
        input.value = input.value === kartu.dataset.kartuStatus ? '' : kartu.dataset.kartuStatus;
        aturSiptl(f);
      }
    });
    function aturUlangBpk(f) {
      var kosong = !$('[data-alasan-bpk]', f).value.trim();
      $('[data-kirim-ulang-bpk]', f).disabled = kosong;
      $('[data-alasan-kosong]', f).hidden = !kosong;
    }
    document.addEventListener('input', function (e) {
      if (e.target.matches('[data-hitung-huruf]')) hitungHuruf(e.target);
      var f = e.target.closest('[data-form-siptl]');
      if (f) aturSiptl(f);
      var g = e.target.closest('[data-form-ulang-bpk]');
      if (g) aturUlangBpk(g);
    });
  }

  var BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  function tglId(s) {
    if (!s) return '—';
    var p = s.split('-');
    return p[2] + ' ' + BULAN[parseInt(p[1], 10) - 1] + ' ' + p[0];
  }
  function angka(v) { return parseInt(String(v == null ? '' : v).replace(/\D/g, ''), 10) || 0; }
  function rp(n) { n = angka(n); return n ? 'Rp ' + n.toLocaleString('id-ID') : '—'; }

  /* Putusan UKI atau Inspektorat. */
  function pasangPanelPeriksa() {
    function atur(f) {
      var mode = $('[data-mode]', f).value, uki = f.dataset.uki === '1';
      var kataM = f.dataset.kataM, kataBM = f.dataset.kataBm, lainBelum = parseInt(f.dataset.lainBelum, 10);
      $('[data-mode-awal]', f).hidden = !!mode;
      $('[data-mode-isi]', f).hidden = !mode;
      if (!mode) return;
      var tolak = mode === 'BM', itjenTolak = !uki && tolak;
      $('[data-hint-m]', f).hidden = tolak;
      $('[data-hint-bm]', f).hidden = !tolak;
      $$('[data-hanya-m]', f).forEach(function (x) { x.hidden = tolak; });
      $$('[data-hanya-bm]', f).forEach(function (x) { x.hidden = !tolak; });
      $$('[data-wajib-surat]', f).forEach(function (x) { x.textContent = tolak ? '— boleh dikosongkan' : '— wajib diisi'; });
      $('[data-ket-catatan]', f).textContent = tolak ? '— wajib diisi' : '— boleh dikosongkan';
      var catatanEl = $('[data-f="catatan"]', f);
      catatanEl.placeholder = tolak ? catatanEl.dataset.contohBm : catatanEl.dataset.contohM;

      var tanda = $('[data-tanda-hasil]', f).value;
      $$('[data-tanda]', f).forEach(function (b) {
        b.className = 'btn btn-s' + (tanda === b.dataset.tanda ? (b.dataset.tanda === 'M' ? ' btn-ok' : ' btn-bad') : '');
      });
      $('[data-tanda-catatan]', f).placeholder = tanda === 'BM' ? 'Apa yang masih kurang dari satuan kerja ini' : 'Catatan untuk satuan kerja ini — boleh dikosongkan';
      var beda = $('[data-beda-usul]', f);
      beda.hidden = !(tanda && tanda !== mode);
      if (!beda.hidden) {
        beda.textContent = 'Tanda di atas mengarah ke ' + (tanda === 'M' ? kataM : kataBM).toLowerCase() + ', sedangkan yang dipilih '
          + (mode === 'M' ? kataM : kataBM).toLowerCase() + '. Boleh diteruskan — sebaiknya alasannya ditulis di catatan.';
      }

      var nomor = $('[data-f="nomor"]', f).value.trim(), tgl = $('[data-f="tgl_surat"]', f).value;
      var catatan = catatanEl.value.trim();
      var batasEl = $('[data-f="batas_waktu"]', f), batas = batasEl ? batasEl.value : '';
      var siap = mode === 'M' ? (nomor.length > 0 && !!tgl) : (catatan.length >= 6 && (!itjenTolak || (!!batas && batas >= f.dataset.hari)));
      var sebutSurat = uki ? 'surat hasil validasi' : 'CHV';
      var tombol = $('[data-tetapkan]', f);
      tombol.className = 'btn ' + (mode === 'M' ? 'btn-ok' : 'btn-bad');
      tombol.disabled = !siap;
      var kata = (mode === 'M' ? kataM : kataBM).toLowerCase();
      $('[data-teks-tetapkan]', f).textContent = 'Tetapkan ' + kata;
      $('[data-belum-siap]', f).hidden = siap;
      $('[data-belum-siap]', f).textContent = mode === 'M'
        ? 'Nomor dan tanggal ' + sebutSurat + ' harus terisi — putusan memadai berdiri di atas suratnya.'
        : (itjenTolak && catatan.length >= 6 ? 'Batas waktu perbaikan harus diisi, dan tidak boleh sebelum hari ini.'
          : 'Catatan harus diisi supaya satuan kerja tahu apa yang perlu diperbaiki.');
      var dok = $$('[data-daftar-isian] input', f).map(function (i) { return i.value.trim(); }).filter(Boolean);
      tombol.dataset.pastikan = JSON.stringify({
        judul: 'Tetapkan ' + kata + '?',
        ket: mode === 'M'
          ? (uki ? 'Berkas kembali ke Setba untuk diteruskan ke Inspektorat.'
            : lainBelum ? 'Berkas satuan kerja ini keluar dari meja Inspektorat. Surat CHV-nya tercatat ' + kataBM.toLowerCase() + ', karena ' + lainBelum + ' satuan kerja lain belum.'
              : 'Berkas keluar dari meja Inspektorat dan rekomendasi dinyatakan selesai diverifikasi.')
          : 'Berkas kembali ke Setba bersama alasannya' + (dok.length ? ' dan ' + dok.length + ' dokumen yang diminta' : '')
            + '. Setba memeriksanya, lalu mengirimkannya ulang ke satuan kerja untuk pemberkasan ulang.',
        tombol: 'Ya, ' + kata,
        nada: mode === 'M' ? 'hijau' : 'merah' });
    }
    document.addEventListener('click', function (e) {
      var f = e.target.closest('[data-panel-periksa]');
      if (!f) return;
      var pilih = e.target.closest('[data-pilih-mode]');
      if (pilih) { $('[data-mode]', f).value = pilih.dataset.pilihMode; atur(f); return; }
      if (e.target.closest('[data-batal-mode]')) { f.reset(); $('[data-mode]', f).value = ''; $('[data-tanda-hasil]', f).value = ''; atur(f); return; }
      var tanda = e.target.closest('[data-tanda]');
      if (tanda) {
        var t = $('[data-tanda-hasil]', f);
        t.value = t.value === tanda.dataset.tanda ? '' : tanda.dataset.tanda;
        atur(f);
      }
    });
    document.addEventListener('input', function (e) {
      var f = e.target.closest('[data-panel-periksa]');
      if (f) atur(f);
    });
    $$('[data-panel-periksa]').forEach(atur);
  }

  /* Surat pengantar Setba: nomor, tanggal, dan perihal wajib. */
  function pasangFormSurat() {
    function atur(f) {
      var sah = ['nomor', 'tanggal', 'perihal'].every(function (k) { return $('[data-surat="' + k + '"]', f).value.trim(); });
      $('[data-butuh-surat]', f).disabled = !sah;
      $('[data-surat-kurang]', f).hidden = sah;
      var terakhir = $('[data-surat-terakhir]', f);
      if (terakhir) terakhir.hidden = !!$('[data-surat="nomor"]', f).value.trim();
    }
    document.addEventListener('click', function (e) {
      var pakai = e.target.closest('[data-pakai-surat]');
      if (!pakai) return;
      var f = pakai.closest('[data-form-surat]');
      var s = JSON.parse(pakai.closest('[data-surat-terakhir]').dataset.suratTerakhir);
      ['nomor', 'tanggal', 'perihal', 'tautan', 'catatan'].forEach(function (k) {
        var el = $('[data-surat="' + k + '"]', f);
        if (el && s[k]) el.value = s[k];
      });
      atur(f);
    });
    document.addEventListener('input', function (e) {
      var f = e.target.closest('[data-form-surat]');
      if (f) atur(f);
    });
    $$('[data-form-surat]').forEach(atur);
  }

  /* Meja pemberkasan ulang: kalimat penegasan menyebut dokumen yang diminta. */
  function pasangKirimUlang() {
    function atur(f) {
      var dok = $$('[data-daftar-isian] input', f).map(function (i) { return i.value.trim(); }).filter(Boolean);
      var ket = $('textarea[name="keterangan"]', f).value.trim();
      var tombol = $('[data-pastikan]', f);
      var isi = JSON.parse(tombol.dataset.pastikan);
      isi.ket = 'Berkas kembali ke meja ' + f.dataset.satker + ' untuk pemberkasan ulang, bersama alasan penolakan ' + f.dataset.dari
        + (f.dataset.batas ? ' dan batas waktunya (' + f.dataset.batas + ')' : '')
        + (ket ? ', ditambah keterangan Setba.' : '.')
        + (dok.length ? ' Satuan kerja wajib melampirkan ' + dok.length + ' dokumen sebelum bisa mengirim lagi.' : ' Tidak ada dokumen tertentu yang diminta.')
        + ' Satuan kerja lain pada rekomendasi ini tidak ikut.';
      isi.rincian = dok.length ? '<ul class="ceklis">' + dok.map(function (d) { return '<li><span>' + esc(d) + '</span></li>'; }).join('') + '</ul>' : '';
      tombol.dataset.pastikan = JSON.stringify(isi);
    }
    document.addEventListener('input', function (e) {
      var f = e.target.closest('[data-form-kirim-ulang]');
      if (f) atur(f);
    });
    $$('[data-form-kirim-ulang]').forEach(atur);
  }

  /* Isian satuan kerja — padanan hitungan PanelBalai. Urutan kalimat bilah
     sama persis dengan urutan syarat tombol kirim. */
  function pasangPanelBalai() {
    function ntpnSah(v) { return /^[0-9A-Za-z]{16}$/.test(v || ''); }
    function nilaiSetor(el) {
      var o = {};
      $$('[data-f]', el).forEach(function (i) { o[i.dataset.f] = i.value; });
      return o;
    }
    function setorSah(x) {
      return !!x.tanggal && angka(x.nilai) > 0 && !!(x.berkas || '').trim() && !!(x.tautan || '').trim()
        && (x.jenis === 'perbaikan' ? !!(x.noBa || '').trim() : ntpnSah(x.ntpn));
    }
    function atur(f) {
      var target = angka(f.dataset.target), masuk = angka(f.dataset.masuk);
      var uraian = $('[data-uraian]', f).value.trim();
      /* Dokumen yang diminta: butir terpenuhi kalau tautannya lengkap. */
      var butir = $$('[data-butir]', f), dipenuhi = 0, buktiKurang = 0, namaBukti = [];
      butir.forEach(function (li) {
        var isi = $('[data-isi-butir]', li), ada = !isi.hidden;
        var nama = $('[data-bukti-nama]', li).value.trim(), tautan = $('[data-bukti-tautan]', li).value.trim();
        var sah = ada && nama && tautan;
        if (ada && !sah) buktiKurang++;
        if (sah) dipenuhi++;
        if (ada) namaBukti.push(nama);
        $('[data-ikon-ada]', li).hidden = !ada;
        $('[data-ikon-belum]', li).hidden = ada;
        $('[data-nama-butir]', li).classList.toggle('sudah', ada);
      });
      $$('[data-lepas]', f).forEach(function (b) {
        var nama = $('[data-bukti-nama]', b).value.trim(), tautan = $('[data-bukti-tautan]', b).value.trim();
        if (!(nama && tautan)) buktiKurang++;
        namaBukti.push(nama);
      });
      var sisaDok = butir.length - dipenuhi;
      var tandaDok = $('[data-sisa-dok]', f);
      if (tandaDok) tandaDok.textContent = sisaDok + ' dari ' + butir.length + ' belum diunggah';
      var kurangBukti = $('[data-bukti-kurang]', f);
      kurangBukti.hidden = !buktiKurang;
      kurangBukti.textContent = buktiKurang + ' tautan belum lengkap — judul dan alamatnya harus terisi dua-duanya sebelum berkas bisa dikirim.';

      /* Pemulihan nilai. */
      var baris = $$('[data-setor]', f), sahSetor = 0, belumSah = 0;
      var lama = angka(f.dataset.setoranLama), rencana = angka(f.dataset.angsurRencana);
      baris.forEach(function (el, i) {
        var x = nilaiSetor(el), tunai = x.jenis !== 'perbaikan';
        var sah = setorSah(x);
        if (sah) sahSetor += angka(x.nilai); else belumSah++;
        $('[data-judul-setor]', el).textContent = (rencana ? 'Angsuran' : 'Pemulihan') + ' ke-' + (lama + i + 1);
        $('[data-setor-belum]', el).hidden = sah;
        $('[data-hint-nilai]', el).innerHTML = x.nilai ? rp(x.nilai) : '&nbsp;';
        $('[data-label-tanggal]', el).textContent = tunai ? 'Tanggal setor' : 'Tanggal perbaikan selesai';
        $('[data-label-bukti]', el).textContent = tunai ? 'Bukti setor (SSBP)' : 'Berita acara perbaikan';
        $('[data-hanya-tunai]', el).hidden = !tunai;
        $('[data-hanya-perbaikan]', el).hidden = tunai;
        var h = $('[data-hint-ntpn]', el);
        h.textContent = x.ntpn ? (ntpnSah(x.ntpn) ? 'Format sesuai' : x.ntpn.length + ' dari 16 karakter') : '16 karakter';
        h.style.color = x.ntpn && !ntpnSah(x.ntpn) ? 'var(--verm)' : '';
      });
      var bakal = masuk + sahSetor, lebih = target ? bakal > target : false;
      var lunas = !target || bakal >= target;
      var bar = $('[data-bar-pulih]', f);
      if (bar) {
        bar.style.width = Math.min(100, target ? bakal / target * 100 : 0) + '%';
        bar.style.background = lebih ? 'var(--verm)' : '';
        $('[data-bakal]', f).textContent = rp(bakal) === '—' ? 'Rp 0' : rp(bakal);
        var l = $('[data-lebih]', f);
        l.hidden = !lebih;
        l.textContent = 'Melebihi kewajiban ' + rp(bakal - target);
        var totalAngsur = lama + baris.length;
        var kunci = f.dataset.angsurKunci === '1';
        var tanda = $('[data-tanda-angsur]', f);
        if (tanda) tanda.textContent = 'angsuran ke-' + (Math.min(totalAngsur, rencana) || 1) + ' dari rencana ' + rencana + (kunci ? ' · dikunci' : '');
        var habis = rencana && kunci && totalAngsur >= rencana;
        $('[data-tambah-setor]', f).disabled = !!habis;
        $('[data-kuota-habis]', f).hidden = !habis;
        $('[data-lewat-rencana]', f).hidden = !(rencana && !kunci && totalAngsur > rencana);
        var sk = $('[data-setor-kurang]', f);
        sk.hidden = !belumSah;
        sk.textContent = belumSah + ' baris belum lengkap — lengkapi atau hapus sebelum mengirim. Draf tetap menyimpannya.';
      }

      var bolehSimpan = !!uraian && !lebih;
      var bolehKirim = sisaDok === 0 && !lebih && buktiKurang === 0 && belumSah === 0 && bolehSimpan;
      $('[data-simpan-draf]', f).disabled = !bolehSimpan;
      var kirim = $('[data-kirim-setba]', f);
      kirim.disabled = !bolehKirim;
      $('[data-ket-bilah]', f).textContent = lebih ? 'Nilai pemulihan melebihi kewajiban — periksa angkanya dulu'
        : sisaDok > 0 ? sisaDok + ' dokumen masih kurang, berkas belum bisa dikirim'
        : buktiKurang > 0 ? buktiKurang + ' tautan belum lengkap, berkas belum bisa dikirim'
        : belumSah > 0 ? belumSah + ' baris pemulihan belum lengkap — lengkapi atau hapus dulu'
        : !bolehSimpan ? 'Uraian dan tanggal pelaporan harus terisi dulu'
        : !lunas ? 'Sisa nilai ' + rp(target - bakal) + ' — berkas tetap bisa dikirim, sisanya menyusul'
        : 'Kewajiban tuntas — berkas siap dikirim ke Setba';

      /* Hitungan mundur kiriman otomatis, hanya saat benar-benar berjalan. */
      var endap = JSON.parse(f.dataset.endap || 'null'), pesan = $('[data-pesan-endap]', f);
      pesan.hidden = !(endap && bolehKirim);
      if (endap) {
        pesan.className = 'pesan' + (endap.sisa <= 2 ? ' warn' : '');
        pesan.style.cssText = 'margin-top:12px;margin-bottom:0';
        pesan.hidden = !(endap && bolehKirim);
        $('[data-teks-endap]', f).textContent = endap.jatuh
          ? 'Draf mengendap lebih dari 7 hari — akan terkirim sendiri ke Setba.'
          : 'Bila tidak dikirim sendiri, draf akan terkirim otomatis ke Setba dalam ' + endap.sisa + ' hari.';
      }

      var lamaBerkas = JSON.parse(f.dataset.berkasLama || '[]');
      var semuaNama = lamaBerkas.concat(namaBukti).filter(Boolean);
      var rincian = '<div class="lbl" style="margin-bottom:8px">Yang ikut terkirim</div><ul class="ceklis">'
        + '<li><span>Catatan tindak lanjut yang baru ditulis</span></li>'
        + '<li><span>' + semuaNama.length + ' berkas bukti<span class="lbl" style="display:block;margin-top:5px;line-height:1.7">' + esc(semuaNama.join(' · ')) + '</span></span></li>'
        + (target ? '<li><span>Pemulihan nilai ' + (bakal > 0 ? rp(bakal) : 'Rp 0') + ' dari ' + rp(target) + (lunas ? ' — lunas' : ' — sisa ' + rp(target - bakal) + ' menyusul') + '</span></li>' : '')
        + (butir.length ? '<li><span>Seluruh dokumen yang diminta sudah dipenuhi</span></li>' : '')
        + '</ul><div class="pesan warn" style="margin-top:14px;margin-bottom:0"><span>Periksa nama berkasnya — pastikan tidak ada berkas pribadi yang ikut terunggah.</span></div>';
      kirim.dataset.pastikan = JSON.stringify({
        judul: 'Kirim tindak lanjut ini ke Setba?',
        ket: f.dataset.bentuk
          ? 'Yang berpindah hanya tindak lanjut “' + f.dataset.bentuk + '”, dan sesudah ini satuan kerja tidak bisa lagi mengubahnya sendiri.'
            + (f.dataset.bentukLain === '1' ? ' Bentuk tindak lanjut lain yang Anda pikul pada rekomendasi ini tetap di sini, dan dikirim sendiri.' : '')
          : 'Berkas berpindah ke meja Setba dan satuan kerja tidak bisa lagi mengubahnya sendiri.',
        rincian: rincian, tombol: 'Ya, kirim ke Setba', balik: false });
    }
    var nomor = 1000;
    function klon(templat, wadah) {
      var html = templat.innerHTML.replace(/__i__/g, String(nomor++));
      var sementara = document.createElement('div');
      sementara.innerHTML = html;
      var el = sementara.firstElementChild;
      wadah.appendChild(el);
      return el;
    }
    document.addEventListener('click', function (e) {
      var f = e.target.closest('[data-panel-balai]');
      if (!f) return;
      var li = e.target.closest('[data-butir]');
      if (e.target.closest('[data-tambah-butir]') && li) {
        var isi = $('[data-isi-butir]', li);
        isi.hidden = false;
        $$('input', isi).forEach(function (i) { i.disabled = false; });
        $('[data-tambah-butir]', li).hidden = true;
        $('[data-hapus-butir]', li).hidden = false;
        $('[data-bukti-nama]', li).focus();
      } else if (e.target.closest('[data-hapus-butir]') && li) {
        var isi2 = $('[data-isi-butir]', li);
        isi2.hidden = true;
        $$('input', isi2).forEach(function (i) { i.disabled = true; if (i.type !== 'hidden') i.value = ''; });
        $('[data-tambah-butir]', li).hidden = false;
        $('[data-hapus-butir]', li).hidden = true;
      } else if (e.target.closest('[data-tambah-lepas]')) {
        var el = klon($('[data-templat-lepas]', f), $('[data-berkas-lepas]', f));
        $('[data-bukti-nama]', el).focus();
      } else if (e.target.closest('[data-hapus-lepas]')) {
        e.target.closest('[data-lepas]').remove();
      } else if (e.target.closest('[data-buka-pulih]')) {
        e.target.closest('[data-buka-pulih]').hidden = true;
        $('[data-isi-pulih]', f).hidden = false;
      } else if (e.target.closest('[data-tambah-setor]')) {
        klon($('[data-templat-setor]', f), $('[data-daftar-setor]', f));
      } else if (e.target.closest('[data-hapus-setor]')) {
        e.target.closest('[data-setor]').remove();
      } else if (e.target.closest('[data-tambah-bukti-setor]')) {
        var s = e.target.closest('[data-setor]');
        $('[data-isi-bukti-setor]', s).hidden = false;
        e.target.closest('[data-tambah-bukti-setor]').hidden = true;
      } else if (e.target.closest('[data-hapus-bukti-setor]')) {
        var s2 = e.target.closest('[data-setor]');
        $('[data-isi-bukti-setor]', s2).hidden = true;
        $$('[data-isi-bukti-setor] input', s2).forEach(function (i) { i.value = ''; });
        $('[data-tambah-bukti-setor]', s2).hidden = false;
      } else {
        return;
      }
      atur(f);
    });
    document.addEventListener('input', function (e) {
      var f = e.target.closest('[data-panel-balai]');
      if (f) atur(f);
    });
    document.addEventListener('change', function (e) {
      var f = e.target.closest('[data-panel-balai]');
      if (f) atur(f);
    });
    $$('[data-panel-balai]').forEach(atur);
  }

  /* ================= halaman laporan ================= */
  /* Blok temuan tertutup saat pertama tampil. Baris rekomendasi di dalamnya
     tidak membuka apa-apa di tempat — ia tautan ke halaman rinciannya, diurus
     `pasangBarisTautan`. */
  function pasangTemuan() {
    function aturTemuan(blok, buka) {
      $('[data-isi-temuan]', blok).hidden = !buka;
      $('[data-buka-temuan]', blok).setAttribute('aria-expanded', buka ? 'true' : 'false');
      $('.panah', blok).classList.toggle('buka', buka);
    }
    $$('[data-temblok]').forEach(function (b) { aturTemuan(b, false); });
    document.addEventListener('click', function (e) {
      var kep = e.target.closest('[data-buka-temuan]');
      if (kep) {
        var blok = kep.closest('[data-temblok]');
        aturTemuan(blok, kep.getAttribute('aria-expanded') !== 'true');
      }
    });
  }

  function mulai() {
    /* Tombol yang hanya berguna kalau skrip ini mati — mis. "Terapkan" pada
       formulir yang di sini terkirim sendiri begitu pilihannya diganti. */
    $$('[data-tanpa-js]').forEach(function (x) { x.hidden = true; });
    pasangTemuan();
    pasangInfo();
    pasangLekat();
    pasangLaci();
    pasangSembul();
    pasangBlokLipat();
    pasangBarisTautan();
    pasangSaring();
    pasangCari();
    pasangPratinjau();
    pasangPastikan();
    pasangTiket();
    pasangTabelTl();
    pasangRiwayat();
    pasangTabRincian();
    pasangOpsional();
    pasangDaftarIsian();
    pasangMenuAksi();
    pasangSuntingSiptl();
    pasangPanelPeriksa();
    pasangFormSurat();
    pasangKirimUlang();
    pasangPanelBalai();
    pasangFormBaru();
    pasangPeta();
    pasangPetunjuk();
    pasangSorot();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mulai);
  else mulai();
})();
