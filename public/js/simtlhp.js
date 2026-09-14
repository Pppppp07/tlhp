/* Lapisan kenyamanan. Semuanya penambah, bukan penopang: halaman ini tetap
   berfungsi penuh kalau berkas ini gagal dimuat atau JavaScript dimatikan.
   Itu disengaja — pengguna kantor pemerintahan memakai peramban dan mesin yang
   beragam, dan tidak ada satu pun tugas di sistem ini yang boleh bergantung
   pada skrip.

   Ditulis polos tanpa perkakas build: berkas ini disalin apa adanya, sama
   seperti berkas gayanya. Aplikasi yang butuh langkah build akan tampil tanpa
   gaya sama sekali kalau ada yang memasangnya tanpa menjalankan build lebih
   dulu — dan gagalnya diam-diam. */
(function () {
  'use strict';

  /* ================= keterangan melayang ================= */
  /* Penjelasan yang perlu tersedia tapi tidak perlu dibaca berulang: aturan,
     dasar hukum, alasan sebuah tombol tidak ada. Digelar sebagai kalimat, ia
     memakan tempat selamanya untuk sesuatu yang dibaca sekali. */
  function pasangInfo() {
    var terbuka = null;

    function tutup() {
      if (terbuka) {
        terbuka.removeAttribute('data-buka');
        terbuka = null;
      }
    }

    document.addEventListener('click', function (e) {
      var tombol = e.target.closest('.infoikon > button');

      if (!tombol) {
        if (!e.target.closest('.infoikon')) tutup();
        return;
      }

      e.preventDefault();
      e.stopPropagation();

      var wadah = tombol.parentElement;
      if (terbuka === wadah) { tutup(); return; }
      tutup();

      /* Arah membuka dihitung saat ditekan, bukan dipatok. Ikon di tepi kanan
         atau di dasar layar akan melempar keterangannya ke luar pandangan
         kalau arahnya selalu sama. */
      var r = wadah.getBoundingClientRect();
      wadah.classList.toggle('ke-kiri', window.innerWidth - r.left < 300);
      wadah.classList.toggle('ke-atas', window.innerHeight - r.bottom < 190);

      wadah.setAttribute('data-buka', '');
      terbuka = wadah;
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') tutup();
    });
  }

  /* ================= sorotan saat mendarat dari kabar ================= */
  /* Menekan pemberitahuan mengantar ke bagian yang berubah lewat jangkar
     alamat. Peramban sudah menggulir ke sana sendiri; yang ditambahkan di sini
     hanya bingkai sekejap, supaya mata tahu bagian mana yang dimaksud di
     antara sekian kartu yang bentuknya mirip. */
  function sorotJangkar() {
    if (!location.hash) return;

    var el = document.getElementById(location.hash.slice(1));
    if (!el) return;

    el.classList.add('sorot');
    setTimeout(function () { el.classList.remove('sorot'); }, 4200);
  }

  /* Datang dari kartu angka: `?sorot=N` menyebut blok mana yang dituju.
     Penandaan berkasnya sudah dikerjakan peladen — yang kurang hanya
     menggulirkan halaman ke sana, dan itu memang bukan urusan peladen. */
  function gulirKeBlok() {
    var p = new URLSearchParams(location.search);
    var blok = p.get('sorot');
    if (blok === null) return;

    var el = document.getElementById('blok-' + blok);
    if (!el) return;

    /* Ditunda satu putaran supaya tata letaknya mapan dulu: menghitung tujuan
       sebelum gambar dan huruf selesai dimuat membuat gulirannya melenceng. */
    setTimeout(function () {
      var tandai = el.querySelector('.baris.tandai');
      (tandai || el).scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 120);
  }

  /* ================= kotak kabar ================= */
  /* Satu kabar ditampilkan isinya — pembacanya kerap bisa langsung memutuskan
     perlu dibuka atau tidak. Lebih dari satu cukup jumlahnya: menumpuk lima
     kotak di sudut layar bukan memberi tahu, itu menghalangi. */
  function kotakKabar() {
    var data = document.getElementById('kabar-baru');
    if (!data) return;

    var jumlah = parseInt(data.dataset.jumlah || '0', 10);
    if (!jumlah) return;

    /* Sekali sesi saja. Tanpa penanda ini kotaknya muncul lagi tiap kali
       pengguna berpindah halaman — dan kabar yang muncul terus-menerus
       berhenti dibaca. */
    var kunci = 'kabar-tersembul';
    if (sessionStorage.getItem(kunci) === data.dataset.tanda) return;
    sessionStorage.setItem(kunci, data.dataset.tanda);

    var satu = jumlah === 1;
    var kotak = document.createElement('div');
    kotak.className = 'sembul';
    kotak.setAttribute('role', 'status');

    kotak.innerHTML =
      '<span class="lonceng-ikon">!</span>' +
      '<span class="teks">' +
        (satu
          ? '<span class="apa"><b>' + data.dataset.pelaku + '</b> — ' + data.dataset.aksi + '</span>' +
            '<span class="lbl">' + data.dataset.judul + '</span>'
          : '<span class="apa"><b>' + jumlah + ' kabar baru</b> menunggu dibaca</span>' +
            '<span class="lbl">Terbaru: ' + data.dataset.pelaku + ' — ' + data.dataset.aksi + '</span>') +
        '<a class="taut" href="' + data.dataset.tautan + '">' +
          (satu ? 'Buka' : 'Lihat semua') + ' &rsaquo;</a>' +
      '</span>' +
      '<button class="tutup" type="button" aria-label="Tutup kabar">&times;</button>';

    document.body.appendChild(kotak);

    /* Menutup sendiri, kecuali sedang disentuh — orang yang baru mulai membaca
       tidak boleh kehilangan kalimatnya di tengah jalan. */
    var jam = setTimeout(lepas, 12000);
    kotak.addEventListener('mouseenter', function () { clearTimeout(jam); });
    kotak.addEventListener('focusin', function () { clearTimeout(jam); });
    kotak.addEventListener('mouseleave', function () {
      clearTimeout(jam);
      jam = setTimeout(lepas, 4000);
    });
    kotak.querySelector('.tutup').addEventListener('click', lepas);
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') lepas();
    });

    function lepas() {
      clearTimeout(jam);
      if (kotak.parentNode) kotak.parentNode.removeChild(kotak);
    }
  }

  /* ================= baris tabel yang dibentangkan ================= */
  /* Rincian satu satuan kerja dibuka di dalam barisnya sendiri, bukan di
     halaman lain: yang dibandingkan orang adalah baris-baris ini satu sama
     lain, dan berpindah halaman untuk tiap baris memutus perbandingannya.

     DUA saklar terpisah, seperti prototipe:

       menekan barisnya  -> keterangannya saja
       menekan "Kerjakan" -> keterangan BESERTA formulirnya

     Yang cuma ingin melihat tidak perlu menurunkan formulir sepanjang layar.

     Penambah, bukan penopang. Yang menyembunyikan barisnya skrip ini, sesudah
     halaman siap — jadi kalau berkas ini gagal dimuat, seluruh rinciannya
     tetap terbaca, cuma tergelar semua. */
  function bentangBaris() {
    var rinci = document.querySelectorAll('tr.lebar');
    if (! rinci.length) return;

    rinci.forEach(function (x) { x.hidden = true; });
    document.querySelectorAll('.panelbaris').forEach(function (x) { x.hidden = true; });

    function tutupSemua(tabel) {
      tabel.querySelectorAll('tr.lebar').forEach(function (x) { x.hidden = true; });
      tabel.querySelectorAll('.panelbaris').forEach(function (x) { x.hidden = true; });
      tabel.querySelectorAll('tr.bukaan').forEach(function (x) {
        x.classList.remove('buka');
      });
      tabel.querySelectorAll('[data-aksi]').forEach(function (b) {
        b.setAttribute('aria-expanded', 'false');
        if (b.dataset.labelAsli) b.textContent = b.dataset.labelAsli;
      });
    }

    function buka(tabel, id, denganPanel) {
      var baris = tabel.querySelector('tr.bukaan[data-buka="' + id + '"]');
      var isi = document.getElementById(id);
      if (! isi) return;

      isi.hidden = false;
      if (baris) baris.classList.add('buka');

      var panel = tabel.querySelector('.panelbaris[data-panel="' + id + '"]');
      if (panel && denganPanel) panel.hidden = false;

      var tombol = tabel.querySelector('[data-aksi="' + id + '"]');
      if (tombol && denganPanel) {
        if (! tombol.dataset.labelAsli) {
          tombol.dataset.labelAsli = tombol.textContent.trim();
        }
        tombol.setAttribute('aria-expanded', 'true');
        tombol.textContent = 'Tutup';
      }
    }

    document.addEventListener('click', function (e) {
      /* Tombol Aksi lebih dulu: ia duduk di dalam barisnya, dan tanpa ini
         penekanannya ikut terbaca sebagai penekanan barisnya. */
      var tombol = e.target.closest('[data-aksi]');
      if (tombol) {
        e.preventDefault();
        e.stopPropagation();

        var id = tombol.dataset.aksi;
        var tabel = tombol.closest('table');
        var panel = tabel.querySelector('.panelbaris[data-panel="' + id + '"]');
        var sedangBuka = panel ? ! panel.hidden : ! document.getElementById(id).hidden;

        tutupSemua(tabel);
        if (! sedangBuka) buka(tabel, id, true);
        return;
      }

      var baris = e.target.closest('tr.bukaan[data-buka]');
      if (! baris) return;

      e.preventDefault();

      var id2 = baris.dataset.buka;
      var isi = document.getElementById(id2);
      if (! isi) return;

      /* Satu baris terbuka pada satu waktu. Membuka banyak sekaligus membuat
         tabelnya panjang dan justru sulit dibandingkan — padahal gunanya
         membandingkan. */
      var terbuka = ! isi.hidden;
      var tabel2 = baris.closest('table');
      tutupSemua(tabel2);
      if (! terbuka) buka(tabel2, id2, false);
    });
  }

  function mulai() {
    pasangInfo();
    sorotJangkar();
    gulirKeBlok();
    kotakKabar();
    bentangBaris();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mulai);
  } else {
    mulai();
  }
})();
