# AL-SYUKROSMART OPS — Rebuild Total (Role, Database, Semua Modul)

Rebuild ini mengganti sistem role lama (9 sub-role generik) dengan **struktur
organisasi PERSIS seperti pada 3 dokumen acuan**: Model 1 (Analisis &
Perancangan), Model 2 (UI/UX & Struktur Database), dan RENCANA KAK Project Work.

## 1. Instalasi
1. Buat database MySQL kosong, lalu import `database/alsyukrosmart1.sql` (skema awal + seed data).
   - File SQL harus berenkode UTF-8; jika membuat dump di Windows PowerShell gunakan `--result-file=` atau `Out-File -Encoding utf8` agar tidak menjadi UTF-16.
2. **PENTING**: Jalankan file `database/migrations.sql` pada database yang sama untuk menerapkan perbaikan struktur dan hak akses terbaru. (Catatan: `migrations.sql` menggunakan sintaks yang kompatibel dengan MariaDB/MySQL versi baru. Jika menggunakan MySQL lawas dan menemui error pada `ADD COLUMN IF NOT EXISTS`, harap hapus klausa `IF NOT EXISTS` atau perbarui versi MySQL).
3. Set kredensial DB via environment variable `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
   (default: localhost / root / '' / alsyukrosmart1) — lihat `config/database.php`.
4. Arahkan document root web server ke folder ini, pastikan folder `uploads/` dan
   `backups/` writable oleh PHP.
5. Buka `index.php`, login dengan salah satu akun demo (password awal semua: `password123`).
   **PENTING**: Segera ganti password demo ini setelah instalasi selesai melalui Kelola Akun atau Reset Password.

## 2. Peran Sistem (20 Peran Operasional)
Administrator Sistem, Manajer GA, SPV Inventaris, Staff Inventaris, SPV Sapras,
Team Leader, Engineering, CSO, Gardener, Manajer HRD, Staff HRD, Manajer MARKOM,
SPV CRM, Staff CRM, Manajer QMS, Staff QMS, TU, Kepala Sekolah, Manajer FA, Staff FA.

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
  - **Skala Penilaian Kinerja (Skor 1 - 5)**: Penilaian tugas non-rutinitas personil lapangan (CSO/Gardener) menggunakan skala skor bulat 1 sampai 5 dengan ketentuan predikat:
    - **5**: Sangat Baik (A)
    - **4**: Baik (B)
    - **3**: Cukup (C)
    - **1 - 2**: Kurang (D)
- **Arsip**: Staff/Manajer QMS unggah & klasifikasi dokumen, atur tingkat akses (umum/terbatas/rahasia), dan backup. Hampir semua peran lain punya akses "simpan & akses dokumen" (bukan pengelola).
- **Divisi Finance & Accounting (`manajer_fa` & `staff_fa`)**: Divisi Finance & Accounting untuk sementara beroperasi tanpa SPV (`spv_fa` / `spvfa` dinonaktifkan sementara secara reversibel, bukan dihapus). **Staff FA** mengajukan kebutuhan operasional (`inventaris`: lihat & ajukan peminjaman, `maintenance`: lihat & ajukan perbaikan fasilitas) serta mengunggah dokumen di Arsip Digital (tidak memiliki akses modul Event). Selama tidak ada akun `spv_fa` yang berstatus `'aktif'`, pengajuan dari Staff FA langsung diarahkan ke **Manajer FA** (Muhamad Fahmi, S.Akun / `manajerfa`) untuk persetujuan atasan (`approve_atasan`) sebelum diteruskan ke pengelola modul teknis terkait (`staff_inventaris`, `team_leader`). Apabila di kemudian hari akun `spv_fa` diaktifkan kembali, `getAtasanId()` secara dinamis akan mengembalikan alur Staff FA → SPV FA tanpa perlu mengubah kode aplikasi. Manajer FA memiliki akses ke modul Dashboard, Inventaris, Maintenance, dan Arsip Digital.
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
  submit form, transisi status, perubahan stok, dsb.) untuk seluruh 20 peran.
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
    pengembalian), Karyawan (data, kegiatan, penilaian, rekapitulasi), Event, Maintenance, Arsip
    (dokumen, riwayat backup), Pengajuan Lintas Divisi, Kelola Akun, Laporan
    (cetak + ekspor rekap seluruh modul), dan Log Aktivitas Sistem.

## 8. Modul Manajemen Karyawan (Penyesuaian KAK a s/d d & Notulen Rapat HRD)

Modul Manajemen Karyawan telah diselaraskan dengan Kerangka Acuan Kerja (KAK) dan Notulen Rapat HRD dengan kontrol akses berbasis hierarki atasan-bawahan (`karyawan.atasan_id`):

### A. Data Karyawan (KAK a) & Kebijakan HRD
- **Cakupan Terbuka**: Pembatasan khusus jabatan CSO/Gardener dihapus. Seluruh karyawan ditampilkan dan disaring berdasarkan cakupan hierarki akses pengguna.
- **Peran HRD (Administratif & Master Data)**: Divisi HRD berperan mengelola administrasi dan rekapitulasi data master karyawan. HRD tidak dibebani fungsi pengawasan teknis/monitoring kinerja maupun penilaian kinerja bawahan unit lain.
- **Pembedaan Hak Akses Staff HRD vs Manajer HRD**:
  - **Staff HRD & Manajer HRD**: Berhak melihat seluruh data karyawan, menambah data karyawan baru, dan mengedit field administratif (Nama, NIP, Jabatan, Tanggal Masuk, Kontak, User ID).
  - **Khusus Manajer HRD & Administrator**: Memiliki aksi izin `kelola_struktur` (mengubah unit/divisi dan atasan langsung karyawan yang sudah ada) serta `ubah_status` (mengaktifkan/menonaktifkan karyawan). Staff HRD tidak memiliki izin ini dan diblokir ketat di sisi server (`api/karyawan.php`).
- **Jabatan Dinamis**: Isian jabatan bebas dengan saran (`<datalist>`) dari jabatan-jabatan yang sudah terdaftar di sistem.
- **Filter & Pencarian**: Tersedia filter jabatan, filter unit/divisi, dan pencarian cepat NIP/nama karyawan.
- **Kolom Baru**:
  - `tanggal_masuk DATE NULL`: Tanggal bergabung karyawan.
  - `kontak VARCHAR(30) NULL`: Nomor HP/WhatsApp/kontak karyawan.
- **Validasi Hierarki Atasan**: Validasi mencegah penetapan atasan ke diri sendiri atau membentuk siklus hierarki (lingkaran atasan-bawahan) menggunakan fungsi rekursif `wouldCreateKaryawanCycle()`.
- **Audit Perubahan Nilai Sebelum & Sesudah**: Penambahan, pembaruan, dan perubahan status karyawan mencatat perubahan nilai field secara terperinci ke dalam audit trail (contoh: `jabatan: Staff -> SPV`, `status: aktif -> nonaktif`).

### B. Kegiatan Karyawan (KAK b)
- **Penugasan oleh Atasan Langsung**: Penugasan kegiatan operasional hanya dapat diberikan oleh **atasan langsung** dari karyawan yang bersangkutan (`atasan_id`), atau oleh Administrator Sistem. HRD tidak berwenang memberikan tugas teknis kepada karyawan. Karyawan pelaksana dapat melihat dan memperbarui status kegiatan miliknya.
- **Jenis Kegiatan**: Pilihan **Rutin** (pekerjaan berkala/SOP) dan **Non-Rutin** (insidental/tugas khusus) dengan badge visual di tabel dan filter jenis.
- **Jadwal & Waktu**:
  - `tanggal DATE`: Tanggal mulai/pelaksanaan kegiatan.
  - `tenggat DATE NULL`: Batas akhir (deadline) penyelesaian tugas.
  - `jam TIME NULL`: Waktu spesifik pelaksanaan.
  - `selesai_pada DATETIME NULL`: Timestamp otomatis saat status tugas berubah menjadi `selesai` (dan dikosongkan jika status dikembalikan).
- **Laporan & Bukti Foto**: Fitur laporan teks progres pekerjaan serta upload bukti foto (JPG, PNG, WebP maks 5MB) dipertahankan dan terintegrasi aman.
- **Keamanan Otorisasi Ketat**: Endpoint `update_kegiatan` dan `hapus_kegiatan` memvalidasi ketat hak akses: hanya pelaksana tugas, pemberi tugas, atasan dalam garis hierarki, atau Admin yang dapat memperbarui/menghapus tugas (termasuk sebelum proses unggah foto).

### C. Monitoring & Penilaian Kinerja (KAK c)
- **Monitoring Berbasis Hierarki Atasan**: Pengawasan dan evaluasi kinerja bawahan hanya dilakukan oleh atasan dalam garis hierarki (`karyawan.atasan_id`), bukan oleh HRD.
- **Pembatasan Ketat Manajer GA**:
  - Izin `karyawan:monitoring` dicabut dari peran Manajer GA (baik di seed maupun migrasi database), sehingga `canViewAllKaryawan` kini eksklusif hanya untuk Administrator Sistem.
  - Manajer GA hanya berhak memantau data karyawan, penugasan, kegiatan, dan rekapitulasi untuk personil di bawah garis komando divisi GA sendiri (SPV Inventaris, SPV Sapras, Staff Inventaris, Team Leader, Engineering, CSO, dan Gardener).
  - Manajer GA tidak dapat melihat maupun mengakses karyawan lintas divisi (HRD, MARKOM, CRM, QMS, FA, TU, Kepala Sekolah) baik pada antarmuka web maupun pemanggilan langsung ke API.
- **Penetapan Atasan Staff Inventaris & Dampak Persetujuan**:
  - Akun Staff Inventaris terhubung ke atasan langsung SPV Inventaris (`atasan_id` menunjuk ke SPV Inventaris).
  - **Dampak Perilaku**: Karena `getAtasanId()` memprioritaskan relasi atasan pada tabel `karyawan`, pengajuan perbaikan fasilitas (Maintenance) yang dibuat oleh Staff Inventaris kini secara konsisten masuk ke status `menunggu_approval_atasan` untuk diverifikasi/disetujui oleh SPV Inventaris (`spv_inventaris` memegang izin `maintenance:approve_atasan`) sebelum diteruskan ke Team Leader / SPV Sapras.
- **Tab Rekap Karyawan**: Endpoint `rekap_karyawan` menyajikan ringkasan per karyawan:
  - Jumlah total tugas, status ditugaskan, dikerjakan, dan selesai.
  - Jumlah tugas **terlambat** (tugas selesai setelah tenggat, atau belum selesai dan telah melewati tenggat).
  - Rata-rata nilai evaluasi dan skor penilaian terakhir.
  - Filter periode bulan dan unit/divisi.
  - Tombol ekspor Cetak, PDF, dan Excel (`renderExportToolbar`).
- **Penilaian Kinerja Fleksibel**:
  - Penilai berhak: Admin, peran dengan izin `penilaian` (`spv_sapras`), atau **atasan langsung** karyawan yang dinilai (`karyawan.atasan_id` menunjuk akun penilai). HRD tidak berhak memberikan penilaian kinerja. Manajer GA memantau penilaian bawahannya (hanya lihat) dan tidak otomatis menilai.
  - Syarat tugas berstatus `selesai` tetap berlaku.
  - Skala skor penilaian 1 sampai 5 bulat dengan predikat: Sangat Baik (A = 5), Baik (B = 4), Cukup (C = 3), Kurang (D = 1-2).
  - Notifikasi evaluasi menggunakan nama penilai yang memberikan skor.

### D. Notifikasi & Pengingat Otomatis (KAK d)
- **Notifikasi Penyelesaian**: Saat tugas selesai, notifikasi dikirimkan ke atasan langsung karyawan dan pemberi tugas (`assigned_by`), serta tetap diteruskan ke `spv_sapras` untuk tugas personil GA.
- **Reminder Otomatis (Tanpa Cron)**:
  - Fungsi `triggerReminderKegiatan()` dipanggil saat polling notifikasi (`api/notifikasi.php?action=list`) dengan pembatas frekuensi maksimal 1 kali per jam per sesi untuk efisiensi kueri.
  - Kueri terindeks `(status, tenggat, tanggal)` memeriksa tugas belum selesai:
    - **H-1**: Tugas jatuh tempo besok (notifikasi ke pelaksana).
    - **Hari-H**: Tugas jatuh tempo hari ini (notifikasi ke pelaksana).
    - **Terlambat**: Tugas telah melewati tenggat (notifikasi ke pelaksana dan atasan langsung).
  - Tabel `reminder_kegiatan` dengan `UNIQUE KEY (kegiatan_id, jenis, tanggal)` menjamin reminder tidak pernah terkirim berulang/duplikat pada hari yang sama.

## 9. Modul Log Aktivitas & Jejak Audit (Audit Trail)

Untuk mendukung standar keamanan data dan akuntabilitas sistem, modul **Log Aktivitas** diperkenalkan dengan spesifikasi:

### A. Pemisahan Modul Log Karyawan & Kinerja Karyawan
Log aktivitas untuk domain karyawan dipisahkan secara tegas menjadi 2 modul:
1. **Modul `karyawan` (Administratif)**:
   - Mencatat penambahan data karyawan baru, pembaruan biodata administratif karyawan, dan perubahan status aktif/nonaktif karyawan.
2. **Modul `karyawan_kinerja` (Kinerja & Penugasan)**:
   - Mencatat penugasan/pemberian kegiatan, pembaruan status tugas, penghapusan penugasan, dan penginputan evaluasi/penilaian kinerja (seluruhnya dicatat tanpa mencantumkan skor nilai, predikat, maupun catatan evaluasi).
   - Telah dimigrasikan secara idempotent dari data historis menggunakan skrip migrasi `database/migrations.sql`.

### B. Aturan Hak Akses Modul Log di Sisi Server (`api/log.php`)
Hak akses aksi `lihat` pada modul `log_aktivitas` dibatasi dengan aturan daftar putih (*whitelist*) ketat di tingkat server:
- **Administrator Sistem (`admin`)**:
  - Berwenang melihat seluruh log dari seluruh modul sistem tanpa batasan pengguna maupun modul.
- **Divisi HRD (`manajer_hrd`, `staff_hrd`)**:
  - Dibatasi secara mutlak hanya pada daftar putih **`modul = 'karyawan'`** (data administratif).
  - **Sama sekali tidak dapat melihat log `karyawan_kinerja`**, baik melalui parameter filter API, total penghitungan record, maupun kartu statistik ringkasan di antarmuka log.
  - Saringan teks berbasis *keyword* (`aktivitas NOT LIKE '%penilaian%'`) telah dieliminasi dan digantikan sepenuhnya oleh mekanisme pemisahan modul yang terjamin aman.
- **Manajer GA (`manajer_ga`)**:
  - Dibatasi secara mutlak hanya pada daftar putih **`modul = 'karyawan_kinerja'`**.
  - Hanya dapat melihat aktivitas yang dilakukan oleh dirinya sendiri atau bawahannya dalam garis hierarki organisasi GA (`user_id IN (user_id Manajer GA + user_id bawahan GA dari getSubordinateKaryawanIds)`).
  - Tidak memiliki akses ke log administratif modul `karyawan` maupun modul sistem lainnya.
- **Peran Lain**:
  - Seluruh peran lain yang tidak memiliki izin `log_aktivitas:lihat` langsung ditolak di sisi server (HTTP 403 Forbidden).

### C. Fitur & Tampilan (`pages/log_aktivitas.php`)
- **Tabel Jejak Audit**: Menampilkan kolom Waktu, Pengguna (Nama & Peran), Modul (dilengkapi badge khusus seperti "Kinerja Karyawan" untuk `karyawan_kinerja`), Aktivitas, dan Alamat IP.
- **Statistik Tersegmentasi**: Kartu ringkasan statistik (Total Log, Log Hari Ini, Pengguna Aktif) dihitung di sisi server dengan menghormati batasan akses per peran secara konsisten.
- **Penyaringan Terpadu**:
  - Filter Rentang Tanggal Mulai dan Selesai.
  - Filter Pengguna (disesuaikan dengan daftar pengguna yang dapat diakses oleh peran terkait).
  - Filter Modul (hanya tersedia bagi Administrator).
  - Pencarian cepat kata kunci (mencari pada aktivitas, nama pengguna, atau alamat IP).
- **Pagination & Row Selector**: Pengaturan jumlah baris per halaman (15, 25, 50, 100, 250) dan navigasi halaman yang responsif.
- **Ekspor & Cetak**: Dilengkapi tombol Cetak, Ekspor Excel, dan Ekspor PDF terintegrasi (`renderExportToolbar`).

### D. Log Tetap Tersimpan Saat Akun Dihapus
- **Integritas Database**: Relasi foreign key `log_aktivitas.user_id` ke tabel `users(id)` dikonfigurasi menjadi `ON DELETE SET NULL` (dengan kolom `user_id` bersifat NULL-able).
- **Pembersihan Log Dihapus**: Baris penghapusan paksa log aktivitas (`DELETE FROM log_aktivitas WHERE user_id=$id`) pada `api/akun.php` telah dihilangkan sepenuhnya.
- **Snapshot Pengguna & IP**: Fungsi `logAktivitas()` di `config/auth.php` secara otomatis merekam snapshot:
  - `nama_user VARCHAR(100)`: Nama lengkap pengguna saat melakukan aksi.
  - `role_user VARCHAR(40)`: Kode peran pengguna saat melakukan aksi.
  - `ip_address VARCHAR(45)`: Alamat IP pengguna melalui `getClientIp()`.
- **Fallback Tampilan**: Seluruh kueri pembacaan log menggunakan `LEFT JOIN users` dan menampilkan data cadangan `nama_user` (disertai penanda status akun dihapus) jika `user_id` bernilai `NULL`.
- **Batasan Log Dashboard**: Pada `pages/dashboard_home.php`, pengguna non-admin hanya dapat melihat aktivitas terbaru milik dirinya sendiri, sedangkan Administrator tetap dapat memantau aktivitas sistem secara keseluruhan.

## 10. Penanganan Ketidakhadiran Pegawai & Alur Pengganti Berjenjang

Untuk memastikan operasional sekolah dan unit kerja tidak terhambat saat staf pemohon atau pejabat *approver* (atasan langsung) berhalangan hadir (sakit, cuti, dinas luar), sistem dilengkapi tiga mekanisme terintegrasi pada modul **Inventaris**, **Maintenance**, dan **Event**:

### A. Status Divisi Finance & Accounting (FA) Sementara Tanpa SPV
- Role `spv_fa` dan akun `spvfa` (Rista Yuniarti, S.Ak) tetap tersimpan di database namun berstatus **`nonaktif`** pada tabel `users` dan `karyawan` (reversibel sewaktu-waktu melalui menu Kelola Akun / Data Karyawan).
- Fungsi `getAtasanId()` memeriksa ketersediaan akun `spv_fa` aktif secara dinamis: selama tidak ada `spv_fa` aktif, atasan langsung **Staff FA** otomatis diarahkan ke **Manajer FA** (Muhamad Fahmi, S.Akun, username `manajerfa`). Jika `spv_fa` diaktifkan kembali, alur otomatis kembali melewati SPV FA.
- Selain itu, pencarian atasan berbasis `karyawan.atasan_id` pada `getAtasanId()` hanya mengembalikan akun atasan yang berstatus `'aktif'`.

### B. Solusi A — "Ajukan Sendiri" oleh Atasan/Pimpinan Unit (Saat Staf Tidak Masuk)
- Ketika staf pembuat pengajuan (misalnya TU di unit sekolah atau staf divisi) tidak hadir, pimpinan unit / atasan langsung dapat membuat pengajuan sendiri pada modul yang relevan:
  - **Inventaris (`ajukan`)**: `kepala_sekolah`, `manajer_hrd`, `manajer_qms`, `spv_crm`, `spv_sapras`, `manajer_fa` (serta `spv_fa` bila aktif).
  - **Maintenance (`ajukan`)**: `kepala_sekolah`, `manajer_hrd`, `manajer_qms`, `spv_crm`, `spv_inventaris`, `manajer_fa` (serta `spv_fa` bila aktif).
  - **Event (`ajukan`)**: `kepala_sekolah`, `manajer_hrd`. *(Catatan: `spv_crm` adalah verifikator modul Event sehingga tidak mengajukan event melalui jalur ini).*
- Pengajuan yang dibuat oleh peran dalam daftar `getPengajuSetaraAtasan($modul)` **otomatis melewati tahap `menunggu_approval_atasan`** dan langsung berstatus **`diajukan`** (diteruskan ke `staff_inventaris` / `team_leader` / `spv_crm`), meskipun `getAtasanId()` dari pejabat tersebut memiliki atasan di struktur karyawan (contoh: `spv_sapras` memiliki atasan `manajer_ga`, atau `spv_crm` memiliki atasan `manajer_markom`).
- Dicatat di `log_aktivitas` dengan keterangan eksplisit `(jalur pengganti: diajukan langsung oleh pimpinan unit)`.
- **Pencegahan Konflik Kepentingan (*Self-Approval*)**: Di semua modul (`inventaris`, `maintenance`, `event`), endpoint `verifikasi` dan `approve` menolak (HTTP 403) upaya pengguna memverifikasi atau menyetujui pengajuan yang dibuat oleh dirinya sendiri.

### C. Solusi B — "Setujui/Tolak sebagai Pengganti" (Saat Approver/Atasan Tidak Masuk)
- Ketika pengajuan staf berstatus `menunggu_approval_atasan` dan atasan langsungnya berhalangan hadir, pejabat pengganti yang berwenang dapat memproses persetujuan/penolakan melalui aksi `approve_atasan` dengan parameter `pengganti=1` dan **`alasan` wajib (10–255 karakter)**.
- **Matriks Pejabat Pengganti (`getPeranPenggantiApprover()`)**:
  | Approver Asli yang Berhalangan | Peran Pengganti yang Diizinkan | Catatan Batasan |
  | :--- | :--- | :--- |
  | **Kepala Sekolah** (`kepala_sekolah` TK/SD/SMP/SMA) | `admin` | Hanya Administrator Sistem |
  | **SPV Inventaris** (`spv_inventaris`) | `manajer_ga` | Hanya Manajer GA (**Admin tidak boleh menggantikan SPV**) |
  | **SPV Sapras** (`spv_sapras`) | `manajer_ga` | Hanya Manajer GA (**Admin tidak boleh menggantikan SPV**) |
  | **SPV CRM** (`spv_crm`) | `manajer_markom` | Hanya Manajer MARKOM (**Admin tidak boleh menggantikan SPV**) |
  | **SPV FA** (`spv_fa`, bila aktif kembali) | `manajer_fa` | Hanya Manajer FA (**Admin tidak boleh menggantikan SPV**) |
  | **Manajer HRD** (`manajer_hrd`) | `admin` | Hanya Administrator Sistem |
  | **Manajer QMS** (`manajer_qms`) | `admin` | Hanya Administrator Sistem |
  | **Manajer FA** (`manajer_fa`, selama tanpa SPV FA) | `admin` | Hanya Administrator Sistem |
  | **Team Leader** (`team_leader`) | *(Tidak ada)* | Belum ditetapkan pengganti |
- **Aturan Ketat**:
  - Pemohon tidak pernah dapat menyetujui pengajuannya sendiri.
  - Approver asli tidak menggunakan jalur pengganti (menggunakan tombol persetujuan atasan normal).
  - Keputusan disimpan pada kolom `atasan_id`, `catatan_atasan`, serta kolom audit khusus: `approval_pengganti_oleh`, `approval_pengganti_alasan` (UTF-8 / `utf8mb4`), dan `approval_pengganti_pada`.
  - Mengirimkan notifikasi otomatis ke **approver asli** (agar mengetahui pengajuan bawahannya telah diputuskan oleh pengganti beserta alasannya), ke **pemohon**, dan (bila disetujui) ke **pengelola tahap berikutnya**.

### D. Solusi C — Sinkronisasi Riwayat Pengajuan Atasan–Staf Se-Unit
- Agar staf mengetahui pengajuan yang telah dibuat langsung oleh atasannya saat staf tidak masuk (dan tidak membuat pengajuan ganda saat kembali bekerja), daftar pengajuan pada modul **Inventaris**, **Maintenance**, dan **Event** menampilkan:
  1. Pengajuan milik pengguna sendiri, **dan**
  2. Pengajuan milik **atasan langsungnya (`getAtasanId($user['id'])`) yang berada di unit yang sama (`unit_id` sama dan tidak `NULL`)**.
- Rekan setara dalam satu unit (misalnya sesama CSO) tetap tidak melihat pengajuan rekan setaranya, dan pimpinan unit (misalnya Kepala Sekolah SD) tidak melihat pengajuan dari unit sekolah lain (misalnya TU SMP).
- Pada modul **Inventaris**, staf juga dapat melihat dan mengonfirmasi pengembalian (`kembalikan`) untuk barang berstatus `disetujui` yang dipinjam oleh dirinya sendiri maupun oleh atasan langsungnya yang se-unit.



