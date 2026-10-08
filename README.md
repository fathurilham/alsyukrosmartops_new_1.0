# AL-SYUKROSMART OPS — Rebuild Total (Role, Database, Semua Modul)

Rebuild ini mengganti sistem role lama (9 sub-role generik) dengan **struktur
organisasi PERSIS seperti pada 3 dokumen acuan**: Model 1 (Analisis &
Perancangan), Model 2 (UI/UX & Struktur Database), dan RENCANA KAK Project Work.

## 1. Instalasi
1. Buat database MySQL kosong, lalu import `database/alsyukrosmart1.sql` (skema awal + seed data).
2. **PENTING**: Jalankan file `database/migrations.sql` pada database yang sama untuk menerapkan perbaikan struktur dan hak akses terbaru. (Catatan: `migrations.sql` menggunakan sintaks yang kompatibel dengan MariaDB/MySQL versi baru. Jika menggunakan MySQL lawas dan menemui error pada `ADD COLUMN IF NOT EXISTS`, harap hapus klausa `IF NOT EXISTS` atau perbarui versi MySQL).
3. Set kredensial DB via environment variable `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
   (default: localhost / root / '' / alsyukrosmart1) — lihat `config/database.php`.
4. Arahkan document root web server ke folder ini, pastikan folder `uploads/` dan
   `backups/` writable oleh PHP.
5. Buka `index.php`, login dengan salah satu akun demo (password awal semua: `password123`).
   **PENTING**: Segera ganti password demo ini setelah instalasi selesai melalui Kelola Akun atau Reset Password.

## 2. 19 Peran (persis sesuai struktur organisasi & hak akses di `alur_user.pdf`)
Administrator Sistem, Manajer GA, SPV Inventaris, Staff Inventaris, SPV Sapras,
Team Leader, Engineering, CSO, Gardener, Manajer HRD, Staff HRD, Manajer MARKOM,
SPV CRM, Staff CRM, Manajer QMS, Staff QMS, TU, Kepala Sekolah, Pengguna Divisi/Unit.

Hak akses tiap peran disimpan sebagai matriks di tabel `hak_akses` (role × modul ×
aksi) — bukan hard-code di PHP — sehingga bisa diaudit/diubah langsung dari database
tanpa mengubah kode (lihat `config/auth.php` fungsi `hasAccess()`).

## 3. Struktur Database (mengikuti Model 2 — 7 kelompok data)
`roles`, `struktur_unit`, `users`, `hak_akses`, `log_aktivitas` — `karyawan`,
`kegiatan_karyawan`, `penilaian_kinerja` — `barang`, `transaksi_barang`,
`peminjaman`, `pengembalian` — `fasilitas`, `pengajuan_maintenance`,
`proses_maintenance` — `event`, `jadwal_event`, `dokumentasi_event` —
`kategori_arsip`, `arsip`, `backup_arsip` — `laporan`, `notifikasi` — plus
`pengajuan_unit` sebagai **jembatan pengajuan lintas divisi**.

## 4. Alur Persetujuan per Modul (sudah diuji end-to-end)
- **Inventaris**: Ajukan → Verifikasi (Staff Inventaris) → Approve (SPV Inventaris) → Kembalikan.
- **Maintenance**: Ajukan → Verifikasi (Team Leader) → Approve (SPV Sapras) → Kerjakan (Engineering).
- **Event**: Ajukan → Persetujuan Atasan Unit Pengaju (jika ada) → Verifikasi (SPV CRM) → Approve (Manajer MARKOM). Sistem otomatis menolak jadwal yang bentrok (lokasi & jam sama).
- **Karyawan**: SPV Sapras & Team Leader memberi kegiatan ke CSO/Gardener → mereka input hasil → SPV Sapras memberi penilaian kinerja. Manajer/Staff HRD mengelola data administratif (tanpa wewenang menilai).
- **Arsip**: Staff/Manajer QMS unggah & klasifikasi dokumen, atur tingkat akses (umum/terbatas/rahasia), dan backup. Hampir semua peran lain punya akses "simpan & akses dokumen" (bukan pengelola).
- **Pengajuan Lintas Divisi** (modul `pengajuan_unit`, menggantikan alur lama yang cuma untuk TU): setiap divisi yang bukan pengelola langsung sebuah modul mengajukan kebutuhan Inventaris/Maintenance/Event lewat sini, lalu **atasan langsungnya** meng-approve sebelum diteruskan otomatis ke pengelola modul terkait:
  - TU → **Kepala Sekolah** (Inventaris, Maintenance, Event — khusus unit pendidikan)
  - Staff HRD → **Manajer HRD** (Inventaris, Maintenance, Event)
  - Staff QMS → **Manajer QMS** (Inventaris, Maintenance)
  - SPV CRM → **Manajer MARKOM** (Inventaris, Maintenance)
  - Team Leader → **SPV Sapras** (Inventaris)
  - Staff Inventaris → **SPV Inventaris** (Maintenance)

  Peta ini didefinisikan di `config/auth.php` (`getPengajuanApproverRole()` /
  `getPengajuanAllowedModules()`) sehingga mudah diaudit/diubah tanpa menyentuh logika API.

## 5. Kelola Akun
Hanya **Administrator Sistem** yang dapat membuat/menonaktifkan akun & reset
password (menu "Kelola Akun"), menggantikan halaman registrasi publik lama —
sesuai kebutuhan bahwa setiap akun harus terikat pada peran organisasi yang sah.

## 6. Tampilan (UI)
Desain visual dirombak untuk mencerminkan identitas institusi:
- **Palet**: hijau tua institusi (`#145c3f`) + aksen emas (`#a97c22`) di atas latar kertas hangat (`#f6f4ee`), menggantikan skema hijau generik sebelumnya.
- **Tipografi**: judul & elemen brand memakai *Fraunces* (serif berkarakter), teks/tabel/form tetap *Inter* untuk keterbacaan data.
- **Logo**: ditampilkan dalam badge putih membulat dengan bayangan halus — baik di sidebar maupun panel login — supaya logo (berlatar putih) tampil rapi di atas latar hijau tua, bukan sebagai kotak putih polos.
- **Panel login**: motif geometris bintang delapan (ornamen khas Islam) sebagai aksen dekoratif halus, headline serif, dan ringkasan angka (modul, peran, unit) di bagian bawah.
- Warna ikon statistik, badge status, dan komponen lain diselaraskan ke satu sistem token warna (`css/app.css`) agar konsisten di seluruh modul.
- **Perbaikan teknis**: `js/main.js` sebelumnya berisi sisa kode sistem lama (auto-refresh 3 detik yang menimpa tabel dengan HTML kosong) — sudah ditulis ulang bersih sehingga data di setiap modul tidak lagi tertimpa/hilang setelah beberapa detik.

- Semua alur di atas sudah divalidasi dengan pengujian HTTP langsung (login,
  submit form, transisi status, perubahan stok, dsb.) untuk seluruh 17 peran.
- Fitur ekspor PDF/Excel, print, dan halaman registrasi publik pada sistem lama
  sengaja belum dibawa ke rebuild ini — silakan minta tambahan jika dibutuhkan.

## 7. Update: Bootstrap 5 + Ekspor PDF/Excel/Print

Rebuild tampilan (tanpa mengubah alur/logic aplikasi sedikit pun):

- **Framework**: seluruh halaman kini memuat **Bootstrap 5.3** via CDN (`css/app.css` ditulis
  ulang di atas variabel/token Bootstrap — warna, radius, shadow — sambil mempertahankan
  100% nama class lama seperti `.sidebar`, `.card`, `.dtable`, `.modal-overlay`, `.fld`, dst.,
  sehingga seluruh interaksi JS di setiap modul tetap identik). Komponen tabel, tombol,
  form, dan badge memakai class asli Bootstrap (`table`, `btn btn-primary`, `form-control`,
  `badge`, dst.) beriringan dengan class lama.
- **Tidak ada perubahan pada**: query database, `config/auth.php`, `config/database.php`,
  seluruh `api/*.php`, hak akses per peran, alur persetujuan antar modul, ID elemen, nama
  field form, maupun fungsi JS (`apiFetch`, `openModal`, `closeModal`, `toast`, dll).
- **Fitur baru — Ekspor & Cetak** (`js/export.js`, dimuat secara lazy dari CDN saat dipakai):
  - **Cetak**: tombol "Cetak" pada setiap tabel modul membuka jendela cetak berisi tabel
    dengan kop nama aplikasi + waktu cetak (juga tersedia `@media print` agar sidebar/topbar/
    tombol otomatis tersembunyi bila memakai Ctrl+P langsung).
  - **Ekspor Excel**: tombol "Excel" mengekspor tabel yang sedang tampil ke `.xlsx`
    (memakai SheetJS, murni di sisi browser).
  - **Ekspor PDF**: tombol "PDF" mengekspor tabel ke `.pdf` dengan kop aplikasi
    (memakai jsPDF + AutoTable, murni di sisi browser).
  - Tersedia di semua modul: Dashboard (aktivitas terbaru), Inventaris (barang, peminjaman,
    pengembalian), Karyawan (data, kegiatan, penilaian), Event, Maintenance, Arsip
    (dokumen, riwayat backup), Pengajuan Lintas Divisi, Kelola Akun, dan Laporan
    (cetak + ekspor rekap seluruh modul).
