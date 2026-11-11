<?php
$currentUser = getCurrentUser();
$canLihat = hasAccess($role, 'karyawan', 'lihat');
$canTambah = hasAccess($role, 'karyawan', 'tambah');
$canKelola = hasAccess($role, 'karyawan', 'kelola');
$canMonitoring = hasAccess($role, 'karyawan', 'monitoring');
$canPenilaian = hasAccess($role, 'karyawan', 'penilaian');
$canInput = hasAccess($role, 'karyawan', 'input');
$canKelolaStruktur = hasAccess($role, 'karyawan', 'kelola_struktur');
$canUbahStatus = hasAccess($role, 'karyawan', 'ubah_status');
$canManageMaster = hasAccess($role, 'karyawan', 'kelola_master');
$canHapusKaryawan = hasAccess($role, 'karyawan', 'hapus');

$isHRD = isHrdAdministratifRole($currentUser);
$isPelaksanaOnly = in_array($role, ['cso', 'gardener'], true);
$isSpvSapras = ($role === 'spv_sapras');
$isTeamLeader = ($role === 'team_leader');
$canTugasRutinSendiri = hasAccess($role, 'karyawan', 'tugas_rutin_sendiri') || $isPelaksanaOnly;

// Statistik kartu sesuai cakupan akses pengguna & proteksi unit Pimpinan/Direksi
$accessibleIds = getAccessibleKaryawanIds($currentUser);
$pimSqlNoAlias = getPimpinanExclusionSql($currentUser, '');
$pimSqlK = getPimpinanExclusionSql($currentUser, 'k');

// Cek profil karyawan login & jam pulang jika CSO/Gardener
$myKar = getKaryawanByUserId($currentUser['id']);
$myJabatan = $myKar ? strtolower(trim($myKar['jabatan'])) : '';
$jamPulangSaya = getJamPulangPelaksana($myJabatan);

// Cek apakah user berhak menugaskan (pemegang monitoring, atau Atasan Langsung yang memiliki bawahan aktif CSO/Gardener)
// Penilaian kinerja HANYA boleh diberikan oleh pemegang izin 'penilaian' (SPV Sapras)
$hasBawahan = false;
if ($myKar) {
    $cekBawahan = $conn->query("SELECT COUNT(*) n FROM karyawan WHERE status='aktif' AND LOWER(TRIM(jabatan)) IN ('cso', 'gardener') AND atasan_id = " . (int)$myKar['id'])->fetch_assoc();
    $hasBawahan = ($cekBawahan['n'] ?? 0) > 0;
}
$canBeriTugas = ($canMonitoring || ($canKelola && $hasBawahan)) && !$isHRD && !$isPelaksanaOnly;
$canBeriPenilaian = $canPenilaian && !$isHRD;
$hasPelaksanaScope = ($isPelaksanaOnly || $canMonitoring || $canPenilaian || $hasBawahan) && !$isHRD;

if ($isHRD) {
    // HRD: Statistik administratif saja (tanpa data tugas/kegiatan & penilaian, serta sesuai hak lihat_pimpinan)
    $totalKaryawan = $conn->query("SELECT COUNT(*) n FROM karyawan WHERE status='aktif' AND ($pimSqlNoAlias)")->fetch_assoc()['n'] ?? 0;
    $totalNonaktif = $conn->query("SELECT COUNT(*) n FROM karyawan WHERE status='nonaktif' AND ($pimSqlNoAlias)")->fetch_assoc()['n'] ?? 0;
    $totalUnit = $conn->query("SELECT COUNT(DISTINCT divisi_id) n FROM karyawan WHERE status='aktif' AND divisi_id IS NOT NULL AND ($pimSqlNoAlias)")->fetch_assoc()['n'] ?? 0;
    $totalJabatan = $conn->query("SELECT COUNT(DISTINCT jabatan) n FROM karyawan WHERE status='aktif' AND jabatan IS NOT NULL AND TRIM(jabatan) != '' AND ($pimSqlNoAlias)")->fetch_assoc()['n'] ?? 0;
} elseif ($accessibleIds !== null && !$canPenilaian) {
    if (empty($accessibleIds)) {
        $totalKaryawan = 0;
        $totalKegiatanProses = 0;
        $totalKegiatanSelesai = 0;
        $totalPenilaian = 0;
    } else {
        $idsStr = implode(',', array_map('intval', $accessibleIds));
        $totalKaryawan = $conn->query("SELECT COUNT(*) n FROM karyawan k WHERE k.status='aktif' AND k.id IN ($idsStr) AND ($pimSqlK)")->fetch_assoc()['n'] ?? 0;
        $totalKegiatanProses = $conn->query("SELECT COUNT(*) n FROM kegiatan_karyawan kk JOIN karyawan k ON k.id = kk.karyawan_id WHERE kk.status != 'selesai' AND kk.karyawan_id IN ($idsStr) AND LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener')")->fetch_assoc()['n'] ?? 0;
        $totalKegiatanSelesai = $conn->query("SELECT COUNT(*) n FROM kegiatan_karyawan kk JOIN karyawan k ON k.id = kk.karyawan_id WHERE kk.status = 'selesai' AND kk.karyawan_id IN ($idsStr) AND LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener')")->fetch_assoc()['n'] ?? 0;
        $totalPenilaian = $conn->query("SELECT COUNT(*) n FROM penilaian_kinerja pk JOIN karyawan k ON k.id = pk.karyawan_id WHERE pk.karyawan_id IN ($idsStr) AND LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener')")->fetch_assoc()['n'] ?? 0;
    }
} else {
    $totalKaryawan = $conn->query("SELECT COUNT(*) n FROM karyawan k WHERE k.status='aktif' AND ($pimSqlK)")->fetch_assoc()['n'] ?? 0;
    $totalKegiatanProses = $conn->query("SELECT COUNT(*) n FROM kegiatan_karyawan kk JOIN karyawan k ON k.id = kk.karyawan_id WHERE kk.status != 'selesai' AND LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener')")->fetch_assoc()['n'] ?? 0;
    $totalKegiatanSelesai = $conn->query("SELECT COUNT(*) n FROM kegiatan_karyawan kk JOIN karyawan k ON k.id = kk.karyawan_id WHERE kk.status = 'selesai' AND LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener')")->fetch_assoc()['n'] ?? 0;
    $totalPenilaian = $conn->query("SELECT COUNT(*) n FROM penilaian_kinerja pk JOIN karyawan k ON k.id = pk.karyawan_id WHERE LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener')")->fetch_assoc()['n'] ?? 0;
}
?>

<!-- ── STATISTIK UTAMA ── -->
<div class="stat-grid">
    <?php if (!$isPelaksanaOnly): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background:#145c3f"><i data-lucide="users"></i></div>
        <h3 id="statTotalAktif"><?= $totalKaryawan ?></h3>
        <p>Total Karyawan Aktif</p>
    </div>
    <?php endif; ?>
    <?php if ($isHRD): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background:#4b5563"><i data-lucide="user-x"></i></div>
        <h3 id="statTotalNonaktif"><?= $totalNonaktif ?></h3>
        <p>Karyawan Nonaktif</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#1d4ed8"><i data-lucide="building"></i></div>
        <h3 id="statTotalUnit"><?= $totalUnit ?></h3>
        <p>Unit / Divisi</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#0f766e"><i data-lucide="briefcase"></i></div>
        <h3 id="statTotalJabatan"><?= $totalJabatan ?></h3>
        <p>Jenis Jabatan</p>
    </div>
    <?php elseif ($hasPelaksanaScope): ?>
    <div class="stat-card">
        <div class="stat-icon" style="background:#a97c22"><i data-lucide="loader"></i></div>
        <h3><?= $totalKegiatanProses ?></h3>
        <p>Tugas Berjalan</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#145c3f"><i data-lucide="check-circle"></i></div>
        <h3><?= $totalKegiatanSelesai ?></h3>
        <p>Tugas Selesai</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#6a4fa0"><i data-lucide="award"></i></div>
        <h3><?= $totalPenilaian ?></h3>
        <p>Penilaian Kinerja</p>
    </div>
    <?php endif; ?>
</div>

<!-- ── CARD UTAMA & TABS ── -->
<div class="card">
    <div class="card-head">
        <div>
            <h2><?= $isPelaksanaOnly ? 'Kegiatan & Penilaian Kinerja' : 'Manajemen & Kegiatan Karyawan' ?></h2>
            <p><?= $isHRD ? 'Administrasi dan rekapitulasi data seluruh karyawan.' : ($isPelaksanaOnly ? 'Daftar penugasan kegiatan harian dan hasil penilaian kinerja Anda dari SPV Sapras.' : 'Data karyawan, penugasan kegiatan rutin & non-rutin, monitoring rekap kinerja, dan evaluasi hasil kerja.') ?></p>
        </div>
        <div class="hgroup">
            <?php if (!$isPelaksanaOnly): ?>
            <div id="toolbarKaryawan"></div>
            <?php endif; ?>
            <?php if ($canManageMaster): ?>
            <button class="btn btn-primary" onclick="bukaModalKaryawan()"><i data-lucide="user-plus"></i> Tambah Karyawan</button>
            <?php endif; ?>
            <?php if ($isPelaksanaOnly): ?>
            <button class="btn btn-primary" id="btnTambahTugasRutinHeader" onclick="bukaModalTugasRutinSaya()"><i data-lucide="plus-circle"></i> Tambah Tugas Rutin</button>
            <?php elseif ($canBeriTugas): ?>
            <button class="btn btn-primary" id="btnBeriTugasHeader" style="display:none" onclick="bukaModalKegiatan()"><i data-lucide="clipboard-list"></i> Beri Tugas Non-Rutin</button>
            <?php endif; ?>
            <?php if ($canBeriPenilaian): ?>
            <button class="btn btn-outline" onclick="bukaModalPenilaian()"><i data-lucide="award"></i> Beri Penilaian Non-Rutin</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <?php if (!$isPelaksanaOnly): ?>
        <button class="tab-btn active" id="tabBtnKaryawan" onclick="switchKaryawanTab('tabKaryawan')"><i data-lucide="users" style="width:15px;height:15px;vertical-align:-2px;margin-right:4px"></i> Data Karyawan</button>
        <?php endif; ?>
        <?php if ($hasPelaksanaScope): ?>
        <button class="tab-btn <?= $isPelaksanaOnly ? 'active' : '' ?>" id="tabBtnKegiatan" onclick="switchKaryawanTab('tabKegiatan')"><i data-lucide="clipboard-check" style="width:15px;height:15px;vertical-align:-2px;margin-right:4px"></i> <?= $isPelaksanaOnly ? 'Tugas Rutin Harian Saya' : 'Kegiatan & Monitoring Karyawan' ?></button>
        <?php endif; ?>
        <?php if (!$isPelaksanaOnly && ($isHRD || $hasPelaksanaScope)): ?>
        <button class="tab-btn" id="tabBtnRekap" onclick="switchKaryawanTab('tabRekap')"><i data-lucide="bar-chart-2" style="width:15px;height:15px;vertical-align:-2px;margin-right:4px"></i> Rekap Karyawan</button>
        <?php endif; ?>
        <?php if ($hasPelaksanaScope): ?>
        <button class="tab-btn" id="tabBtnPenilaian" onclick="switchKaryawanTab('tabPenilaian')"><i data-lucide="star" style="width:15px;height:15px;vertical-align:-2px;margin-right:4px"></i> Penilaian Kinerja</button>
        <?php endif; ?>
    </div>

    <?php if (!$isPelaksanaOnly): ?>
    <!-- ── TAB 1: DATA KARYAWAN ── -->
    <div id="tabKaryawan" class="tab-content">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div class="search-bar">
                <i data-lucide="search"></i>
                <input type="text" id="cariKaryawan" placeholder="Cari NIP, nama karyawan, atau jabatan..." oninput="filterDataKaryawan()">
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <select id="filterJabatan" class="form-select form-select-sm" style="width:auto;min-width:170px;font-size:12.5px" onchange="filterDataKaryawan()">
                    <option value="">-- Semua Jabatan --</option>
                </select>
                <select id="filterDivisi" class="form-select form-select-sm" style="width:auto;min-width:170px;font-size:12.5px" onchange="filterDataKaryawan()">
                    <option value="">-- Semua Unit / Divisi --</option>
                </select>
            </div>
        </div>
        <div class="table-wrap">
            <table class="dtable table table-hover align-middle mb-0" id="tblKaryawan">
                <thead>
                    <tr>
                        <th style="width:100px">NIP</th>
                        <th>Nama Karyawan</th>
                        <th>Jabatan</th>
                        <th>Unit / Divisi</th>
                        <th>Atasan Langsung</th>
                        <th>Tgl Masuk</th>
                        <th>Kontak</th>
                        <th style="text-align:center">Status</th>
                        <?php if ($canManageMaster): ?>
                        <th class="no-print" style="text-align:center;width:135px;min-width:135px;white-space:nowrap">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="karyawanBody">
                    <tr><td colspan="<?= $canManageMaster ? 9 : 8 ?>" class="empty-row">Memuat data karyawan...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$isHRD): ?>
    <!-- ── TAB 2: KEGIATAN KARYAWAN ── -->
    <div id="tabKegiatan" class="tab-content" style="display:<?= $isPelaksanaOnly ? 'block' : 'none' ?>">

    <?php if ($isPelaksanaOnly): ?>
        <!-- ── VIEW KHUSUS CSO & GARDENER (TUGAS RUTIN MANDIRI) ── -->
        <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
                <div>
                    <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;font-weight:600">Pelaksana Operasional Mandiri</div>
                    <h3 style="margin:2px 0;font-size:17px;color:var(--primary);font-weight:700"><?= esc($myKar['nama'] ?? $currentUser['name']) ?> — <span class="badge b-gray" style="font-size:12px;font-weight:600"><?= esc($myKar['jabatan'] ?? strtoupper($role)) ?></span></h3>
                    <div style="font-size:12.5px;color:var(--text);margin-top:4px;display:flex;gap:16px;flex-wrap:wrap">
                        <span><i data-lucide="calendar" style="width:13px;height:13px;vertical-align:-2px;color:var(--primary)"></i> Tanggal: <strong><?= date('d M Y') ?></strong></span>
                        <span><i data-lucide="clock" style="width:13px;height:13px;vertical-align:-2px;color:var(--gold)"></i> Pengisian Buka: <strong>04:30 WIB</strong></span>
                        <span><i data-lucide="log-out" style="width:13px;height:13px;vertical-align:-2px;color:var(--danger)"></i> Jam Pulang: <strong><?= $jamPulangSaya ?> WIB</strong></span>
                    </div>
                </div>
                <div>
                    <button class="btn btn-primary" id="btnTambahRutin" onclick="bukaModalTugasRutinSaya()"><i data-lucide="plus-circle"></i> Tambah Tugas Rutin Hari Ini</button>
                </div>
            </div>

            <!-- Banner status kunci hari ini (diisi via JS) -->
            <div id="pelaksanaLockBanner" style="display:none;margin-top:12px"></div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div class="search-bar">
                <i data-lucide="search"></i>
                <input type="text" id="cariKegiatan" placeholder="Cari deskripsi tugas rutin..." oninput="filterDataKegiatan()">
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <select id="filterStatusKegiatan" class="form-select form-select-sm" style="width:auto;min-width:140px;font-size:12.5px" onchange="filterDataKegiatan()">
                    <option value="">-- Semua Status --</option>
                    <option value="ditugaskan">Ditugaskan</option>
                    <option value="dikerjakan">Dikerjakan</option>
                    <option value="selesai">Selesai</option>
                </select>
                <select id="filterWaktuKegiatan" class="form-select form-select-sm" style="width:auto;min-width:140px;font-size:12.5px" onchange="filterDataKegiatan()">
                    <option value="hari_ini" selected>Tugas Hari Ini</option>
                    <option value="semua">Semua Riwayat</option>
                </select>
            </div>
        </div>

        <div class="table-wrap">
            <table class="dtable table table-hover align-middle mb-0" id="tblKegiatan">
                <thead>
                    <tr>
                        <th style="width:115px">Jadwal & Jam</th>
                        <th style="text-align:center;width:80px">Jenis</th>
                        <th>Deskripsi Tugas Rutin</th>
                        <th style="text-align:center;width:140px">Foto Bukti</th>
                        <th style="text-align:center;width:110px">Status</th>
                        <th style="text-align:center;width:140px">Status Nilai Harian</th>
                        <th class="no-print" style="text-align:center;width:115px">Aksi</th>
                    </tr>
                </thead>
                <tbody id="kegiatanBody">
                    <tr><td colspan="7" class="empty-row">Memuat tugas rutin harian Anda...</td></tr>
                </tbody>
            </table>
        </div>

    <?php else: ?>
        <!-- ── VIEW SUPERVISOR / TEAM LEADER: MONITORING HARIAN ── -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;display:block;margin-bottom:3px">Tanggal Monitoring</label>
                    <input type="date" id="monTanggal" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" style="width:auto;font-size:12.5px;padding:5px 9px" onchange="loadMonitoringHarian()">
                </div>
                <div>
                    <label style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;display:block;margin-bottom:3px">Pilih Karyawan Pelaksana</label>
                    <select id="monKaryawan" class="form-select form-select-sm" style="width:auto;min-width:240px;font-size:12.5px" onchange="loadMonitoringHarian()">
                        <option value="">-- Pilih Karyawan --</option>
                    </select>
                </div>
                <div style="align-self:flex-end">
                    <button class="btn btn-outline btn-sm" onclick="loadMonitoringHarian()" title="Muat Ulang Data Monitoring"><i data-lucide="refresh-cw"></i> Segarkan</button>
                </div>
            </div>
            <div style="display:flex;gap:8px;align-items:center">
                <?php if ($canBeriTugas): ?>
                <button class="btn btn-primary btn-sm" onclick="bukaModalKegiatan()"><i data-lucide="clipboard-list"></i> Beri Tugas Non-Rutin</button>
                <?php endif; ?>
                <button class="btn btn-outline btn-sm" id="btnToggleAllTasks" onclick="toggleAllTasksView()"><i data-lucide="list"></i> Tampilkan Seluruh Riwayat</button>
            </div>
        </div>

        <!-- Panel Monitoring Harian Terfokus -->
        <div id="wrapperMonitoringHarian">
            <!-- Banner Status Karyawan & Nilai Harian -->
            <div id="monStatusBanner" style="margin-bottom:16px"></div>

            <!-- Bagian 1: Tugas Rutin Harian (Dibuat Mandiri oleh Pelaksana) -->
            <div class="card" style="margin-bottom:16px;border:1px solid var(--border);box-shadow:none">
                <div class="card-head" style="padding:12px 16px;background:#fbfaf6;border-bottom:1px solid var(--border)">
                    <div>
                        <h3 style="font-size:14px;margin:0;color:var(--primary);display:flex;align-items:center;gap:6px">
                            <i data-lucide="check-square" style="width:16px;height:16px"></i>
                            Tugas Rutin Harian (Dibuat Mandiri oleh Karyawan)
                        </h3>
                        <p style="margin:2px 0 0;font-size:11.5px;color:var(--muted)">Dikelola dan dilaporkan mandiri oleh karyawan bersangkutan dengan bukti foto dokumentasi (Read-Only).</p>
                    </div>
                    <div id="monBadgeCountRutin"></div>
                </div>
                <div class="table-wrap">
                    <table class="dtable table table-hover align-middle mb-0" id="tblMonRutin">
                        <thead>
                            <tr>
                                <th style="width:110px">Jam Target</th>
                                <th>Deskripsi Tugas Rutin</th>
                                <th style="text-align:center;width:150px">Foto Bukti</th>
                                <th style="text-align:center;width:110px">Status</th>
                                <th style="text-align:center;width:140px">Status Penilaian</th>
                            </tr>
                        </thead>
                        <tbody id="monRutinBody">
                            <tr><td colspan="5" class="empty-row">Pilih karyawan pelaksana untuk melihat tugas rutin harian.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bagian 2: Tugas Non-Rutin (Diberikan oleh Atasan / Insidental) -->
            <div class="card" style="border:1px solid var(--border);box-shadow:none">
                <div class="card-head" style="padding:12px 16px;background:#fbfaf6;border-bottom:1px solid var(--border)">
                    <div>
                        <h3 style="font-size:14px;margin:0;color:#5f3d95;display:flex;align-items:center;gap:6px">
                            <i data-lucide="layers" style="width:16px;height:16px"></i>
                            Tugas Non-Rutin (Penugasan Atasan & Tugas Khusus)
                        </h3>
                        <p style="margin:2px 0 0;font-size:11.5px;color:var(--muted)">Tugas khusus/insidental yang diberikan Team Leader atau SPV Sapras. Dinilai per tugas sebagai nilai tambahan (bobot 15%).</p>
                    </div>
                    <div id="monBadgeCountNonRutin"></div>
                </div>
                <div class="table-wrap">
                    <table class="dtable table table-hover align-middle mb-0" id="tblMonNonRutin">
                        <thead>
                            <tr>
                                <th style="width:110px">Jadwal & Tenggat</th>
                                <th>Deskripsi Tugas Khusus</th>
                                <th>Pemberi Tugas</th>
                                <th style="text-align:center;width:110px">Status</th>
                                <th>Hasil Laporan & Bukti</th>
                                <th style="text-align:center;width:130px">Penilaian (15%)</th>
                            </tr>
                        </thead>
                        <tbody id="monNonRutinBody">
                            <tr><td colspan="6" class="empty-row">Tidak ada tugas non-rutin pada tanggal ini.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Panel Riwayat Seluruh Tugas (Tabel Komprehensif, Tersembunyi Default) -->
        <div id="wrapperAllTasks" style="display:none">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
                <div class="search-bar">
                    <i data-lucide="search"></i>
                    <input type="text" id="cariKegiatan" placeholder="Cari kegiatan, tugas, atau nama karyawan..." oninput="filterDataKegiatan()">
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <select id="filterJenisKegiatan" class="form-select form-select-sm" style="width:auto;min-width:140px;font-size:12.5px" onchange="filterDataKegiatan()">
                        <option value="">-- Semua Jenis --</option>
                        <option value="rutin">Rutin</option>
                        <option value="non-rutin">Non-Rutin</option>
                    </select>
                    <select id="filterStatusKegiatan" class="form-select form-select-sm" style="width:auto;min-width:140px;font-size:12.5px" onchange="filterDataKegiatan()">
                        <option value="">-- Semua Status --</option>
                        <option value="ditugaskan">Ditugaskan</option>
                        <option value="dikerjakan">Dikerjakan</option>
                        <option value="selesai">Selesai</option>
                    </select>
                </div>
            </div>
            <div class="table-wrap">
                <table class="dtable table table-hover align-middle mb-0" id="tblKegiatan">
                    <thead>
                        <tr>
                            <th style="width:105px">Jadwal & Tenggat</th>
                            <th style="text-align:center;width:85px">Jenis</th>
                            <th>Karyawan Pelaksana</th>
                            <th>Deskripsi Tugas / Kegiatan</th>
                            <th>Pemberi Tugas</th>
                            <th style="text-align:center">Status</th>
                            <th>Hasil / Progres</th>
                            <th style="text-align:center;min-width:130px">Penilaian</th>
                            <th class="no-print" style="text-align:center;width:105px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="kegiatanBody">
                        <tr><td colspan="9" class="empty-row">Memuat data kegiatan karyawan...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    </div>
    <?php endif; ?>

    <?php if (!$isPelaksanaOnly): ?>
    <!-- ── TAB 3: REKAP KARYAWAN (KAK c) ── -->
    <div id="tabRekap" class="tab-content" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div class="search-bar">
                <i data-lucide="search"></i>
                <input type="text" id="cariRekap" placeholder="Cari nama karyawan, NIP, atau jabatan..." oninput="filterDataRekap()">
            </div>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <input type="month" id="filterBulanRekap" class="form-control form-control-sm" style="width:auto;font-size:12.5px;padding:5px 9px" onchange="loadRekapKaryawan()" title="<?= $isHRD ? 'Filter Bulan Tanggal Masuk' : 'Filter Bulan Penugasan' ?>">
                <select id="filterDivisiRekap" class="form-select form-select-sm" style="width:auto;min-width:170px;font-size:12.5px" onchange="loadRekapKaryawan()">
                    <option value="">-- Semua Unit / Divisi --</option>
                </select>
                <div id="toolbarRekap"></div>
            </div>
        </div>

        <?php if ($isHRD): ?>
        <div id="hrdRekapSummary" style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap"></div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="dtable table table-hover align-middle mb-0" id="tblRekapKaryawan">
                <thead>
                    <tr>
                        <th style="width:90px">NIP</th>
                        <th>Nama Karyawan</th>
                        <th>Jabatan</th>
                        <th>Unit / Divisi</th>
                        <?php if ($isHRD): ?>
                        <th style="text-align:center;width:90px">Status</th>
                        <th style="width:115px">Tanggal Masuk</th>
                        <?php else: ?>
                        <th style="text-align:center;width:80px">Hari Rutin</th>
                        <th style="text-align:center;width:80px">Hari Dinilai</th>
                        <th style="text-align:center;width:95px">Belum Dinilai</th>
                        <th style="text-align:center;width:105px">Rata Harian (85%)</th>
                        <th style="text-align:center;width:115px">Rata Non-Rutin (15%)</th>
                        <th style="text-align:center;width:110px">Nilai Akhir</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="rekapBody">
                    <tr><td colspan="<?= $isHRD ? 6 : 10 ?>" class="empty-row">Memuat rekapitulasi data karyawan...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$isHRD): ?>
    <!-- ── TAB 4: PENILAIAN KINERJA ── -->
    <div id="tabPenilaian" class="tab-content" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px">
            <div style="display:flex;gap:8px">
                <button type="button" class="btn btn-primary btn-sm" id="btnSubPenilaianHarian" onclick="switchSubPenilaian('harian')"><i data-lucide="award"></i> Penilaian Harian (Tugas Rutin - 85%)</button>
                <button type="button" class="btn btn-outline btn-sm" id="btnSubPenilaianNonrutin" onclick="switchSubPenilaian('nonrutin')"><i data-lucide="star"></i> Penilaian Non-Rutin (Per Tugas - 15%)</button>
            </div>
            <div class="search-bar">
                <i data-lucide="search"></i>
                <input type="text" id="cariPenilaian" placeholder="Cari karyawan, tanggal, atau catatan..." oninput="filterDataPenilaian()">
            </div>
        </div>

        <!-- Sub-view 1: Penilaian Harian (Tugas Rutin) -->
        <div id="viewPenilaianHarian">
            <div class="table-wrap">
                <table class="dtable table table-hover align-middle mb-0" id="tblPenilaianHarian">
                    <thead>
                        <tr>
                            <th style="width:110px">Tanggal</th>
                            <th>Karyawan Dinilai</th>
                            <th>Jabatan</th>
                            <th style="text-align:center;width:90px">Tugas Rutin</th>
                            <th style="text-align:center;width:85px">Skor</th>
                            <th style="text-align:center;width:125px">Predikat</th>
                            <th>Penilai (SPV Sapras)</th>
                            <th>Catatan Evaluasi Harian</th>
                            <th style="text-align:center;width:95px">Status</th>
                        </tr>
                    </thead>
                    <tbody id="penilaianHarianBody">
                        <tr><td colspan="9" class="empty-row">Memuat data penilaian harian...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sub-view 2: Penilaian Non-Rutin (Per Tugas) -->
        <div id="viewPenilaianNonrutin" style="display:none">
            <div class="table-wrap">
                <table class="dtable table table-hover align-middle mb-0" id="tblPenilaian">
                    <thead>
                        <tr>
                            <th style="width:110px">Periode</th>
                            <th>Karyawan Dinilai</th>
                            <th>Tugas Non-Rutin Yang Dinilai</th>
                            <th style="text-align:center;width:85px">Skor</th>
                            <th style="text-align:center;width:125px">Predikat</th>
                            <th>Penilai</th>
                            <th>Catatan Evaluasi</th>
                            <th style="width:95px">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody id="penilaianBody">
                        <tr><td colspan="8" class="empty-row">Memuat data penilaian non-rutin...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 1: TAMBAH / EDIT KARYAWAN -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalKaryawan">
    <div class="modal">
        <div class="modal-head">
            <h3 id="modalKaryawanTitle">Tambah Karyawan Baru</h3>
            <button class="modal-close" onclick="closeModal('modalKaryawan')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="kId">
            <div class="form-grid">
                <div class="fld">
                    <label>Nomor Induk Pegawai (NIP) <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="kNip" placeholder="Terisi otomatis dari Unit / Divisi...">
                    <small id="hintNipAuto" style="color:var(--muted);font-size:11px;margin-top:4px;display:block">Pilih Unit / Divisi untuk mengisi NIP otomatis (AWALAN-NNNN).</small>
                </div>
                <div class="fld">
                    <label>Nama Lengkap <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="kNama" placeholder="Nama lengkap karyawan">
                </div>
            </div>
            <div class="form-grid">
                <div class="fld">
                    <label>Jabatan <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="kJabatan" list="jabatanDataList" placeholder="Ketik atau pilih jabatan...">
                    <datalist id="jabatanDataList"></datalist>
                </div>
                <div class="fld">
                    <label>Unit / Divisi</label>
                    <select id="kDivisi" onchange="onDivisiChangeKaryawan(this.value)">
                        <option value="">-- Pilih Unit / Divisi --</option>
                    </select>
                    <small id="hintDivisiLock" style="display:none;color:#b45309;font-size:11px;margin-top:4px"><i data-lucide="lock" style="width:11px;height:11px;vertical-align:-1px"></i> Hanya Manajer HRD yang berwenang mengubah unit/divisi.</small>
                </div>
            </div>
            <div class="form-grid">
                <div class="fld">
                    <label>Tanggal Masuk</label>
                    <input type="date" id="kTanggalMasuk">
                </div>
                <div class="fld">
                    <label>Kontak (HP / WhatsApp / Email)</label>
                    <input type="text" id="kKontak" placeholder="Contoh: 08123456789">
                </div>
            </div>
            <div class="form-grid">
                <div class="fld">
                    <label>Atasan Langsung</label>
                    <select id="kAtasan">
                        <option value="">-- Pilih Atasan --</option>
                    </select>
                    <small id="hintAtasanLock" style="display:none;color:#b45309;font-size:11px;margin-top:4px"><i data-lucide="lock" style="width:11px;height:11px;vertical-align:-1px"></i> Hanya Manajer HRD yang berwenang mengubah atasan langsung.</small>
                </div>
                <div class="fld">
                    <label>Tautkan Akun Login Sistem (Opsional)</label>
                    <select id="kUser">
                        <option value="">-- Tanpa Akun Login --</option>
                    </select>
                </div>
            </div>
            <div class="fld" id="kStatusWrapper" style="display:none">
                <label>Status Karyawan</label>
                <select id="kStatus">
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
                <small id="hintStatusLock" style="display:none;color:#b45309;font-size:11px;margin-top:4px"><i data-lucide="lock" style="width:11px;height:11px;vertical-align:-1px"></i> Hanya Manajer HRD yang berwenang mengubah status aktif/nonaktif.</small>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalKaryawan')">Batal</button>
            <button class="btn btn-primary" id="btnSimpanKaryawan" onclick="simpanKaryawan()"><i data-lucide="save"></i> Simpan Data</button>
        </div>
    </div>
</div>

<?php if (!$isHRD): ?>
<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 2: PENUGASAN KEGIATAN NON-RUTIN (OLEH TEAM LEADER / SPV) -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalKegiatan">
    <div class="modal">
        <div class="modal-head">
            <h3>Beri Tugas Non-Rutin Karyawan</h3>
            <button class="modal-close" onclick="closeModal('modalKegiatan')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <div class="fld">
                <label>Pilih Karyawan Pelaksana <span style="color:var(--danger)">*</span></label>
                <select id="kgKaryawan">
                    <option value="">-- Pilih Karyawan Pelaksana --</option>
                </select>
            </div>

            <div class="form-grid">
                <div class="fld">
                    <label>Jenis Kegiatan / Tugas <span style="color:var(--danger)">*</span></label>
                    <select id="kgJenis" disabled style="background:#f3f4f6;cursor:not-allowed">
                        <option value="non-rutin" selected>Non-Rutin (Insidental / Tugas Khusus)</option>
                    </select>
                    <small style="color:var(--muted);font-size:11px;margin-top:3px;display:block">Tugas rutin harian diketik dan dilaporkan mandiri oleh CSO & Gardener.</small>
                </div>
                <div class="fld">
                    <label>Tanggal Pelaksanaan <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="kgTanggal" value="<?= date('Y-m-d') ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="fld">
                    <label>Tenggat Waktu / Batas Akhir (Deadline)</label>
                    <input type="date" id="kgTenggat">
                </div>
                <div class="fld">
                    <label>Jam Target / Pelaksanaan</label>
                    <input type="time" id="kgJam">
                </div>
            </div>

            <div class="fld">
                <label>Deskripsi Tugas / Kegiatan & Lokasi <span style="color:var(--danger)">*</span></label>
                <textarea id="kgDeskripsi" rows="3" placeholder="Rincikan deskripsi pekerjaan khusus, target hasil, serta instruksi spesifik lainnya..."></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalKegiatan')">Batal</button>
            <button class="btn btn-primary" id="btnTugaskan" onclick="simpanKegiatan()"><i data-lucide="send"></i> Tugaskan</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 2B: FORM TUGAS RUTIN HARIAN (CSO / GARDENER MANDIRI) -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalTugasRutinSaya">
    <div class="modal">
        <div class="modal-head">
            <h3 id="modalTugasRutinTitle">Tugas Rutin Harian Saya</h3>
            <button class="modal-close" onclick="closeModal('modalTugasRutinSaya')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rtTaskId">
            <div style="background:#f4f9f4;border:1px solid #c2e2c8;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:12.5px;color:var(--text)">
                <div style="display:flex;align-items:center;gap:8px;font-weight:600;color:var(--primary);margin-bottom:4px">
                    <i data-lucide="calendar" style="width:15px;height:15px"></i>
                    <span>Tugas Rutin untuk Hari Ini: <?= date('d M Y') ?></span>
                </div>
                <div style="color:var(--muted);font-size:11.5px">Ketik bebas deskripsi pekerjaan Anda hari ini. Menandai tugas status <strong>Selesai</strong> WAJIB melampirkan minimal 1 foto bukti dokumentasi (maksimal 3 foto).</div>
            </div>

            <div class="fld">
                <label>Deskripsi Tugas Rutin <span style="color:var(--danger)">*</span></label>
                <textarea id="rtDeskripsi" rows="3" placeholder="Contoh: Pembersihan area koridor lt. 1, pemotongan rumput taman tengah, pembersihan kaca jendela lobi..."></textarea>
            </div>

            <div class="form-grid">
                <div class="fld">
                    <label>Status Pelaksanaan <span style="color:var(--danger)">*</span></label>
                    <select id="rtStatus">
                        <option value="dikerjakan" selected>Sedang Dikerjakan</option>
                        <option value="selesai">Selesai Dikerjakan</option>
                        <option value="ditugaskan">Ditugaskan (Belum Dimulai)</option>
                    </select>
                </div>
                <div class="fld">
                    <label>Jam Target / Pelaksanaan (Opsional)</label>
                    <input type="time" id="rtJam">
                </div>
            </div>

            <!-- Upload Multi-Foto Dokumentasi (Maks 3) -->
            <div class="fld">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                    <label style="margin-bottom:0">Foto Bukti / Dokumentasi (Maks. 3 Foto)</label>
                    <span id="rtFotoCountInfo" class="badge b-gray" style="font-size:11px">0 / 3 Foto</span>
                </div>
                
                <input type="file" id="rtFotoInput" accept="image/jpeg,image/png,image/webp" multiple style="display:none" onchange="handleRutinFotosSelect(this)">
                
                <div class="upload-area" id="rtFotoDropzone" onclick="document.getElementById('rtFotoInput').click()" style="border:2px dashed var(--border);border-radius:var(--bs-border-radius);padding:14px;text-align:center;cursor:pointer;background:#faf9f5;transition:all 0.2s ease;">
                    <i data-lucide="camera" style="width:26px;height:26px;color:var(--primary);margin-bottom:4px"></i>
                    <div style="font-weight:600;font-size:12.5px;color:var(--text)">Ambil Foto atau Pilih Gambar Bukti</div>
                    <div style="font-size:11px;color:var(--muted)">Format JPG, PNG, atau WebP (Maks. 5MB per foto)</div>
                </div>

                <div id="rtFotosPreviewContainer" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px"></div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalTugasRutinSaya')">Batal</button>
            <button class="btn btn-primary" id="btnSimpanRutin" onclick="simpanTugasRutinSaya()"><i data-lucide="check"></i> Simpan Tugas Rutin</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 2C: PENILAIAN HARIAN TUGAS RUTIN (SPV SAPRAS ONLY) -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalPenilaianHarian">
    <div class="modal" style="max-width:520px">
        <div class="modal-head">
            <h3>Beri Nilai Harian Tugas Rutin</h3>
            <button class="modal-close" onclick="closeModal('modalPenilaianHarian')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pnhKaryawanId">
            <input type="hidden" id="pnhTanggal">

            <div style="background:#f4f9f4;border:1px solid #c2e2c8;border-radius:8px;padding:14px;margin-bottom:14px">
                <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Karyawan Yang Dinilai</div>
                <div id="pnhKaryawanNama" style="font-weight:700;font-size:15px;color:var(--primary);margin:2px 0 4px"></div>
                <div style="display:flex;gap:14px;font-size:12px;color:var(--text)">
                    <div><strong>Tanggal:</strong> <span id="pnhTanggalTeks">-</span></div>
                    <div><strong>Jam Pulang:</strong> <span id="pnhJamPulangTeks">-</span></div>
                </div>
                <div id="pnhSummaryTugas" style="margin-top:6px;font-size:12px;color:var(--muted)"></div>
            </div>

            <div class="fld">
                <label>Skor Nilai Harian (1 - 5) <span style="color:var(--danger)">*</span></label>
                <div style="display:flex;gap:8px;margin-bottom:8px">
                    <button type="button" class="btn btn-outline pnh-btn-score" data-score="1" onclick="setPnhScore(1)" style="flex:1">1<br><small style="font-size:10px">Kurang</small></button>
                    <button type="button" class="btn btn-outline pnh-btn-score" data-score="2" onclick="setPnhScore(2)" style="flex:1">2<br><small style="font-size:10px">Kurang</small></button>
                    <button type="button" class="btn btn-outline pnh-btn-score" data-score="3" onclick="setPnhScore(3)" style="flex:1">3<br><small style="font-size:10px">Cukup</small></button>
                    <button type="button" class="btn btn-outline pnh-btn-score" data-score="4" onclick="setPnhScore(4)" style="flex:1">4<br><small style="font-size:10px">Baik</small></button>
                    <button type="button" class="btn btn-outline pnh-btn-score" data-score="5" onclick="setPnhScore(5)" style="flex:1">5<br><small style="font-size:10px">Sgt Baik</small></button>
                </div>
                <input type="hidden" id="pnhNilai" value="">
                <div id="pnhGradeBadge" style="display:none;margin-top:4px"></div>
            </div>

            <div class="fld">
                <label>Catatan & Evaluasi Harian</label>
                <textarea id="pnhCatatan" rows="3" placeholder="Tuliskan catatan evaluasi hasil kerja harian karyawan, apresiasi, atau arahan perbaikan..."></textarea>
            </div>

            <div style="background:#fffbeb;border:1px solid #fef3c7;border-radius:6px;padding:10px 12px;font-size:11.5px;color:#92400e;line-height:1.4">
                <div style="display:flex;align-items:flex-start;gap:6px">
                    <i data-lucide="lock" style="width:14px;height:14px;flex-shrink:0;margin-top:2px"></i>
                    <div>
                        <strong>PENGUNCIAN PERMANEN:</strong> Nilai harian hanya dapat disimpan <strong>SATU KALI</strong>. Setelah tersimpan, nilai dan seluruh tugas rutin karyawan pada tanggal tersebut <strong>terkunci permanen</strong> dan tidak dapat diubah maupun dihapus kembali lewat aplikasi.
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalPenilaianHarian')">Batal</button>
            <button class="btn btn-primary" id="btnSimpanNilaiHarian" onclick="simpanNilaiHarian()"><i data-lucide="award"></i> Simpan & Kunci Nilai</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 3: UPDATE STATUS & LAPORAN TUGAS NON-RUTIN -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalUpdateKegiatan">
    <div class="modal">
        <div class="modal-head">
            <h3>Update Status & Hasil Laporan Kegiatan</h3>
            <button class="modal-close" onclick="closeModal('modalUpdateKegiatan')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="updKgId">
            <input type="hidden" id="updKgHapusFoto" value="0">
            <div class="fld">
                <label>Deskripsi Kegiatan / Tugas</label>
                <div id="updKgDeskripsi" style="background:#faf9f5;border:1px solid var(--border);border-radius:var(--bs-border-radius);padding:10px 14px;font-size:13px;color:var(--text);font-weight:500"></div>
            </div>
            <div class="fld">
                <label>Status Pelaksanaan <span style="color:var(--danger)">*</span></label>
                <select id="updKgStatus">
                    <option value="ditugaskan">Ditugaskan (Belum Dimulai)</option>
                    <option value="dikerjakan">Sedang Dikerjakan</option>
                    <option value="selesai">Selesai Dikerjakan</option>
                </select>
            </div>
            
            <!-- Upload Foto Bukti Pengerjaan Tugas -->
            <div class="fld">
                <label>Foto Bukti Pengerjaan / Hasil Kerja</label>
                <div class="upload-area" id="updFotoDropzone" onclick="document.getElementById('updKgFoto').click()" style="border:2px dashed var(--border);border-radius:var(--bs-border-radius);padding:18px 16px;text-align:center;cursor:pointer;background:#faf9f5;transition:all 0.2s ease;">
                    <input type="file" id="updKgFoto" accept="image/*" capture="environment" style="display:none" onchange="previewUpdateFoto(this)">
                    <div id="updFotoPlaceholder">
                        <i data-lucide="camera" style="width:30px;height:30px;color:var(--primary);margin-bottom:6px"></i>
                        <div style="font-weight:600;font-size:13px;color:var(--text)">Ambil Foto atau Pilih Gambar Bukti</div>
                        <div style="font-size:11.5px;color:var(--muted);margin-top:3px">Format JPG, PNG, atau WebP (Maks. 5MB)</div>
                    </div>
                    <div id="updFotoPreviewWrapper" style="display:none;position:relative">
                        <img id="updFotoPreview" src="" alt="Preview Bukti Foto" style="max-height:160px;max-width:100%;border-radius:6px;object-fit:contain;box-shadow:0 2px 8px rgba(0,0,0,0.1)">
                        <div style="margin-top:10px;display:flex;justify-content:center;gap:8px">
                            <button type="button" class="btn btn-outline btn-sm" onclick="event.stopPropagation();document.getElementById('updKgFoto').click();"><i data-lucide="refresh-cw"></i> Ganti Foto</button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="event.stopPropagation();hapusUpdateFoto();"><i data-lucide="trash-2"></i> Hapus Foto</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="fld">
                <label>Hasil / Progres Laporan Pekerjaan</label>
                <textarea id="updKgHasil" rows="4" placeholder="Jelaskan kondisi hasil pekerjaan, penyelesaian tugas, atau catatan kendala (jika ada)..."></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalUpdateKegiatan')">Batal</button>
            <button class="btn btn-primary" onclick="simpanUpdateKegiatan()"><i data-lucide="check"></i> Simpan Laporan</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 4: PENILAIAN KINERJA (NON-RUTIN) -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalPenilaian">
    <div class="modal">
        <div class="modal-head">
            <h3>Penilaian Tugas Non-Rutin</h3>
            <button class="modal-close" onclick="closeModal('modalPenilaian')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pnKegiatanId">
            <input type="hidden" id="pnKaryawanId">

            <!-- Mode 1: Jika dinilai langsung dari baris tabel tugas -->
            <div id="pnInfoTugasWrapper" style="display:none;background:#fbfaf5;border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:14px">
                <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Karyawan Pelaksana</div>
                <div id="pnInfoTugasNama" style="font-weight:700;font-size:14px;color:var(--primary);margin:2px 0 6px"></div>
                <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Tugas Yang Telah Diselesaikan</div>
                <div id="pnInfoTugasDeskripsi" style="font-size:12.5px;color:var(--text);margin-top:2px"></div>
                <div id="pnInfoTugasFotoWrapper" style="display:none;margin-top:10px">
                    <button type="button" class="btn btn-outline btn-sm" id="btnPnLihatFoto"><i data-lucide="image"></i> Lihat Foto Bukti Pengerjaan</button>
                </div>
            </div>

            <!-- Mode 2: Jika dibuka dari tombol Beri Penilaian di header -->
            <div id="pnKaryawanSelectWrapper">
                <div class="fld">
                    <label>Pilih Tugas Selesai Yang Akan Dinilai <span style="color:var(--danger)">*</span></label>
                    <select id="pnPilihTugasSelesai" onchange="onPilihTugasSelesai(this.value)">
                        <option value="">-- Pilih Tugas Selesai Yang Belum Dinilai --</option>
                    </select>
                    <div id="pnPilihFotoWrapper" style="display:none;margin-top:8px">
                        <button type="button" class="btn btn-outline btn-sm" id="btnPnPilihFoto"><i data-lucide="image"></i> Lihat Foto Bukti Tugas Ini</button>
                    </div>
                </div>
            </div>

            <div class="form-grid">
                <div class="fld">
                    <label>Periode Penilaian <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="pnPeriode" placeholder="Contoh: Oktober 2026" value="<?= date('F Y') ?>">
                </div>
                <div class="fld">
                    <label>Skor Nilai (1 - 5) <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="pnNilai" min="1" max="5" step="1" placeholder="Skor 1 s/d 5" oninput="updateGradePreview()">
                    <div style="margin-top:6px">
                        <span id="pnGradeBadge" style="display:none"></span>
                    </div>
                </div>
            </div>

            <div class="fld">
                <label>Catatan Evaluasi / Rekomendasi Kinerja</label>
                <textarea id="pnCatatan" rows="3" placeholder="Tuliskan catatan evaluasi kualitas pengerjaan, kedisiplinan, maupun arahan perbaikan..."></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalPenilaian')">Batal</button>
            <button class="btn btn-primary" onclick="simpanPenilaian()"><i data-lucide="award"></i> Simpan Penilaian</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 5: LIHAT DETAIL EVALUASI KINERJA -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalLihatEvaluasi">
    <div class="modal" style="max-width:500px">
        <div class="modal-head">
            <h3>Hasil Penilaian Kinerja</h3>
            <button class="modal-close" onclick="closeModal('modalLihatEvaluasi')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <div style="background:#f4f9f4;border:1px solid #c2e2c8;border-radius:8px;padding:16px;text-align:center;margin-bottom:16px">
                <div style="font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px">Skor Evaluasi Kinerja</div>
                <div id="evScore" style="font-size:38px;font-weight:800;color:var(--primary);margin:4px 0">- / 5</div>
                <div id="evGradeBadge"><span class="badge b-gray">-</span></div>
            </div>
            <div class="fld">
                <label>Karyawan Pelaksana</label>
                <div id="evNamaPersonil" style="font-weight:600;font-size:13.5px;color:var(--text)"></div>
            </div>
            <div class="fld">
                <label>Tugas / Kegiatan Yang Dinilai</label>
                <div id="evDeskripsiTugas" style="background:#faf9f5;border:1px solid var(--border);border-radius:6px;padding:8px 12px;font-size:12.5px;color:var(--text)"></div>
            </div>
            <div class="form-grid">
                <div class="fld">
                    <label>Penilai</label>
                    <div id="evPenilaiNama" style="font-size:13px;color:var(--text);font-weight:500"></div>
                </div>
                <div class="fld">
                    <label>Periode</label>
                    <div id="evPeriode" style="font-size:13px;color:var(--text)"></div>
                </div>
            </div>
            <div class="fld" id="evFotoWrapper" style="display:none">
                <label>Foto Bukti Pengerjaan</label>
                <div>
                    <button type="button" class="btn btn-outline btn-sm" id="btnEvLihatFoto"><i data-lucide="image"></i> Lihat Foto Bukti Tugas</button>
                </div>
            </div>
            <div class="fld">
                <label>Catatan & Masukan Evaluasi</label>
                <div id="evCatatan" style="background:#fff;border:1px solid var(--border);border-radius:6px;padding:10px 12px;font-size:13px;line-height:1.5;color:var(--text);min-height:50px"></div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-primary" onclick="closeModal('modalLihatEvaluasi')">Tutup</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- MODAL 6: PREVIEW FOTO BUKTI BESAR -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalLihatFoto">
    <div class="modal" style="max-width:580px">
        <div class="modal-head">
            <h3 id="judulLihatFoto">Foto Bukti Pengerjaan Tugas</h3>
            <button class="modal-close" onclick="closeModal('modalLihatFoto')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body" style="text-align:center;padding:18px">
            <div style="background:#111;border-radius:8px;overflow:hidden;display:inline-block;max-width:100%;box-shadow:0 4px 16px rgba(0,0,0,0.15)">
                <img id="imgPreviewBesar" src="" alt="Foto Bukti" style="max-width:100%;max-height:60vh;display:block;margin:0 auto;object-fit:contain">
            </div>
            <div id="galeriThumbsWrapper" style="display:none;justify-content:center;gap:8px;margin-top:12px;flex-wrap:wrap"></div>
            <div id="descPreviewBesar" style="font-size:13px;color:var(--text);margin-top:14px;text-align:left;background:#fbfaf6;border:1px solid var(--border);border-radius:6px;padding:10px 14px"></div>
        </div>
        <div class="modal-foot">
            <a id="btnUnduhFoto" href="#" download class="btn btn-outline"><i data-lucide="download"></i> Unduh Foto</a>
            <button class="btn btn-primary" onclick="closeModal('modalLihatFoto')">Tutup</button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- SCRIPT CLIENT LOGIC -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<script>
const CAN_MANAGE_MASTER = <?= $canManageMaster ? 'true' : 'false' ?>;
const CAN_HAPUS_KARYAWAN = <?= $canHapusKaryawan ? 'true' : 'false' ?>;
const CAN_KELOLA = <?= $canKelola ? 'true' : 'false' ?>;
const CAN_PENILAIAN = <?= $canPenilaian ? 'true' : 'false' ?>;
const CAN_RATE = <?= $canBeriPenilaian ? 'true' : 'false' ?>;
const CAN_KELOLA_STRUKTUR = <?= $canKelolaStruktur ? 'true' : 'false' ?>;
const CAN_UBAH_STATUS = <?= $canUbahStatus ? 'true' : 'false' ?>;
const IS_HRD = <?= $isHRD ? 'true' : 'false' ?>;
const IS_PELAKSANA_ONLY = <?= $isPelaksanaOnly ? 'true' : 'false' ?>;
const IS_SPV_SAPRAS = <?= $isSpvSapras ? 'true' : 'false' ?>;
const IS_TEAM_LEADER = <?= $isTeamLeader ? 'true' : 'false' ?>;
const CAN_BERI_TUGAS = <?= $canBeriTugas ? 'true' : 'false' ?>;
const CAN_BERI_PENILAIAN = <?= $canBeriPenilaian ? 'true' : 'false' ?>;
const CAN_TUGAS_RUTIN = <?= $canTugasRutinSendiri ? 'true' : 'false' ?>;
const CURRENT_USER_ID = <?= (int)$currentUser['id'] ?>;
const CURRENT_KARYAWAN_ID = <?= $myKar ? (int)$myKar['id'] : 0 ?>;
const CURRENT_KARYAWAN_NAMA = '<?= $myKar ? addslashes($myKar['nama']) : '' ?>';
const CURRENT_KARYAWAN_JABATAN = '<?= $myKar ? addslashes($myKar['jabatan']) : '' ?>';
const JAM_MULAI_RUTIN = '<?= JAM_MULAI_RUTIN ?>';
const BOBOT_HARIAN = <?= BOBOT_HARIAN ?>;
const BOBOT_NONRUTIN = <?= BOBOT_NONRUTIN ?>;

let dataKaryawanList = [];
let dataKegiatanList = [];
let dataRekapList = [];
let dataPenilaianList = [];
let dataPenilaianHarianList = [];
let dropdownOptions = { units: [], atasan: [], users: [], jabatan_list: [], karyawan_tugas: [], tugas_belum_dinilai: [] };
let currentMonitoringData = { tasks: [], penilaianHarian: null, karyawan: null };
let currentSubPenilaian = 'harian';
let showingAllTasks = false;

// State upload foto tugas rutin mandiri
let selectedRutinFiles = [];
let existingRutinFotos = [];
let hapusRutinFotoIds = [];

// Definisi predikat penilaian kinerja skala 1-5
function getPredikatInfo(score) {
    const s = parseInt(score, 10);
    if (s === 5) return { text: 'Sangat Baik (A)', badge: 'b-green' };
    if (s === 4) return { text: 'Baik (B)', badge: 'b-blue' };
    if (s === 3) return { text: 'Cukup (C)', badge: 'b-yellow' };
    if (s === 1 || s === 2) return { text: 'Kurang (D)', badge: 'b-red' };
    return { text: '-', badge: 'b-gray' };
}

function getJamPulang(jabatan) {
    const j = (jabatan || '').toLowerCase();
    if (j.includes('gardener') || j.includes('taman') || j.includes('kebun')) return '15:30';
    return '16:30';
}

function getJakartaTimeNow() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    return `${h}:${m}`;
}

function isAfterTime(timeA, timeB) {
    return timeA >= timeB;
}

function switchKaryawanTab(tabId) {
    if (IS_HRD && (tabId === 'tabKegiatan' || tabId === 'tabPenilaian')) return;
    if (IS_PELAKSANA_ONLY && (tabId === 'tabKaryawan' || tabId === 'tabRekap')) return;

    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');

    const btnBeriTugas = document.getElementById('btnBeriTugasHeader');
    if (btnBeriTugas) {
        btnBeriTugas.style.display = (tabId === 'tabKegiatan') ? '' : 'none';
    }
    
    if (tabId === 'tabKaryawan' && !IS_PELAKSANA_ONLY) {
        const btn = document.getElementById('tabBtnKaryawan');
        const content = document.getElementById('tabKaryawan');
        if (btn) btn.classList.add('active');
        if (content) content.style.display = 'block';
    } else if (tabId === 'tabKegiatan' && !IS_HRD) {
        const btn = document.getElementById('tabBtnKegiatan');
        const content = document.getElementById('tabKegiatan');
        if (btn) btn.classList.add('active');
        if (content) content.style.display = 'block';
        if (!IS_PELAKSANA_ONLY) {
            loadMonitoringHarian();
        } else {
            loadDataKegiatan();
        }
    } else if (tabId === 'tabRekap' && !IS_PELAKSANA_ONLY) {
        const btn = document.getElementById('tabBtnRekap');
        const content = document.getElementById('tabRekap');
        if (btn) btn.classList.add('active');
        if (content) content.style.display = 'block';
        loadRekapKaryawan();
    } else if (tabId === 'tabPenilaian' && !IS_HRD) {
        const btn = document.getElementById('tabBtnPenilaian');
        const content = document.getElementById('tabPenilaian');
        if (btn) btn.classList.add('active');
        if (content) content.style.display = 'block';
        switchSubPenilaian(currentSubPenilaian);
    }
    if (window.lucide) lucide.createIcons();
}

function switchSubPenilaian(type) {
    currentSubPenilaian = type;
    const btnH = document.getElementById('btnSubPenilaianHarian');
    const btnN = document.getElementById('btnSubPenilaianNonrutin');
    const viewH = document.getElementById('viewPenilaianHarian');
    const viewN = document.getElementById('viewPenilaianNonrutin');

    if (type === 'harian') {
        if (btnH) { btnH.className = 'btn btn-primary btn-sm'; }
        if (btnN) { btnN.className = 'btn btn-outline btn-sm'; }
        if (viewH) viewH.style.display = 'block';
        if (viewN) viewN.style.display = 'none';
        loadDataPenilaianHarian();
    } else {
        if (btnH) { btnH.className = 'btn btn-outline btn-sm'; }
        if (btnN) { btnN.className = 'btn btn-primary btn-sm'; }
        if (viewH) viewH.style.display = 'none';
        if (viewN) viewN.style.display = 'block';
        loadDataPenilaian();
    }
    if (window.lucide) lucide.createIcons();
}

function toggleAllTasksView() {
    showingAllTasks = !showingAllTasks;
    const wMon = document.getElementById('wrapperMonitoringHarian');
    const wAll = document.getElementById('wrapperAllTasks');
    const btn = document.getElementById('btnToggleAllTasks');
    if (showingAllTasks) {
        if (wMon) wMon.style.display = 'none';
        if (wAll) wAll.style.display = 'block';
        if (btn) btn.innerHTML = '<i data-lucide="eye"></i> Mode Monitoring Harian';
        loadDataKegiatan();
    } else {
        if (wMon) wMon.style.display = 'block';
        if (wAll) wAll.style.display = 'none';
        if (btn) btn.innerHTML = '<i data-lucide="list"></i> Tampilkan Seluruh Riwayat';
        loadMonitoringHarian();
    }
    if (window.lucide) lucide.createIcons();
}

async function loadDropdowns() {
    const r = await apiFetch('karyawan', 'dropdown_data', {}, 'GET');
    if (!r.ok) return;
    dropdownOptions = r.data;

    // Unit options untuk form tambah/edit karyawan
    const selKDivisi = document.getElementById('kDivisi');
    if (selKDivisi) {
        let optUnits = '<option value="">-- Pilih Unit / Divisi --</option>';
        (dropdownOptions.units || []).forEach(u => {
            optUnits += `<option value="${u.id}">${esc(u.nama_unit)}</option>`;
        });
        selKDivisi.innerHTML = optUnits;
    }

    // Filter Divisi pada Tab Karyawan
    const selFilterDivisi = document.getElementById('filterDivisi');
    if (selFilterDivisi) {
        const curDiv = selFilterDivisi.value;
        let optFilt = '<option value="">-- Semua Unit / Divisi --</option>';
        (dropdownOptions.units || []).forEach(u => {
            optFilt += `<option value="${u.id}">${esc(u.nama_unit)}</option>`;
        });
        selFilterDivisi.innerHTML = optFilt;
        if (curDiv) selFilterDivisi.value = curDiv;
    }

    // Filter Divisi pada Tab Rekap
    const selFilterDivisiRekap = document.getElementById('filterDivisiRekap');
    if (selFilterDivisiRekap) {
        const curDivR = selFilterDivisiRekap.value;
        let optFiltR = '<option value="">-- Semua Unit / Divisi --</option>';
        (dropdownOptions.units || []).forEach(u => {
            optFiltR += `<option value="${u.id}">${esc(u.nama_unit)}</option>`;
        });
        selFilterDivisiRekap.innerHTML = optFiltR;
        if (curDivR) selFilterDivisiRekap.value = curDivR;
    }

    // Jabatan options & datalist
    const dlist = document.getElementById('jabatanDataList');
    if (dlist && dropdownOptions.jabatan_list) {
        dlist.innerHTML = dropdownOptions.jabatan_list.map(j => `<option value="${esc(j)}">`).join('');
    }
    const selFilterJab = document.getElementById('filterJabatan');
    if (selFilterJab && dropdownOptions.jabatan_list) {
        const curJab = selFilterJab.value;
        let optJab = '<option value="">-- Semua Jabatan --</option>';
        dropdownOptions.jabatan_list.forEach(j => {
            optJab += `<option value="${esc(j)}">${esc(j)}</option>`;
        });
        selFilterJab.innerHTML = optJab;
        if (curJab) selFilterJab.value = curJab;
    }

    // Atasan options
    const selAtasan = document.getElementById('kAtasan');
    if (selAtasan) {
        let optAtasan = '<option value="">-- Pilih Atasan Langsung --</option>';
        (dropdownOptions.atasan || []).forEach(a => {
            optAtasan += `<option value="${a.id}">${esc(a.nama)} (${esc(a.jabatan)})</option>`;
        });
        selAtasan.innerHTML = optAtasan;
    }

    // User options
    const selUser = document.getElementById('kUser');
    if (selUser) {
        let optUsers = '<option value="">-- Tanpa Akun Login --</option>';
        (dropdownOptions.users || []).forEach(u => {
            optUsers += `<option value="${u.id}">${esc(u.name)} (@${esc(u.username)})</option>`;
        });
        selUser.innerHTML = optUsers;
    }

    // Karyawan pelaksana untuk penugasan non-rutin
    const selKgKar = document.getElementById('kgKaryawan');
    if (selKgKar) {
        let optKar = '<option value="">-- Pilih Karyawan Pelaksana --</option>';
        (dropdownOptions.karyawan_tugas || []).forEach(k => {
            optKar += `<option value="${k.id}">${esc(k.nama)} (${esc(k.nip)} — ${esc(k.jabatan)})</option>`;
        });
        selKgKar.innerHTML = optKar;
    }

    // Monitoring selector untuk Team Leader / SPV Sapras
    const selMonKar = document.getElementById('monKaryawan');
    if (selMonKar) {
        const curMonVal = selMonKar.value;
        let optMon = '<option value="">-- Pilih Karyawan Pelaksana --</option>';
        (dropdownOptions.karyawan_tugas || []).forEach(k => {
            optMon += `<option value="${k.id}">${esc(k.nama)} (${esc(k.nip)} — ${esc(k.jabatan)})</option>`;
        });
        selMonKar.innerHTML = optMon;
        if (curMonVal) {
            selMonKar.value = curMonVal;
        } else if (dropdownOptions.karyawan_tugas && dropdownOptions.karyawan_tugas.length > 0) {
            selMonKar.value = dropdownOptions.karyawan_tugas[0].id;
        }
        if (!IS_PELAKSANA_ONLY && !IS_HRD && selMonKar.value) {
            loadMonitoringHarian();
        }
    }

    // Tugas yang sudah selesai dan belum dinilai untuk modal penilaian
    renderDropdownTugasBelumDinilai();
}

function renderDropdownTugasBelumDinilai() {
    const selTugas = document.getElementById('pnPilihTugasSelesai');
    if (!selTugas) return;
    let optTugas = '<option value="">-- Pilih Tugas Selesai Yang Belum Dinilai --</option>';
    (dropdownOptions.tugas_belum_dinilai || []).forEach(t => {
        optTugas += `<option value="${t.id}" data-kar-id="${t.karyawan_id}" data-nama="${esc(t.karyawan_nama)}" data-jabatan="${esc(t.jabatan)}" data-deskripsi="${esc(t.deskripsi)}" data-foto="${esc(t.foto_laporan || '')}">[${esc(t.tanggal)}] ${esc(t.karyawan_nama)} (${esc(t.jabatan)}) — ${esc(t.deskripsi.substring(0, 45))}...</option>`;
    });
    selTugas.innerHTML = optTugas;
}

function onPilihTugasSelesai(kegiatanId) {
    const fotoWrapper = document.getElementById('pnPilihFotoWrapper');
    if (!kegiatanId) {
        document.getElementById('pnKegiatanId').value = '';
        document.getElementById('pnKaryawanId').value = '';
        if (fotoWrapper) fotoWrapper.style.display = 'none';
        return;
    }
    const sel = document.getElementById('pnPilihTugasSelesai');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('pnKegiatanId').value = kegiatanId;
    document.getElementById('pnKaryawanId').value = opt.dataset.karId || '';

    if (fotoWrapper) {
        if (opt.dataset.foto) {
            fotoWrapper.style.display = 'block';
            document.getElementById('btnPnPilihFoto').onclick = () => bukaModalFoto(opt.dataset.foto, opt.dataset.deskripsi, `Bukti Tugas - ${opt.dataset.nama}`);
            if (window.lucide) lucide.createIcons({ root: fotoWrapper });
        } else {
            fotoWrapper.style.display = 'none';
        }
    }
}

/* ── 1. LOGIC DATA KARYAWAN (ADMINISTRATIF) ── */
function updateStatCardsKaryawan() {
    const aktifList = dataKaryawanList.filter(k => k.status === 'aktif');
    const nonaktifList = dataKaryawanList.filter(k => k.status !== 'aktif');

    const elAktif = document.getElementById('statTotalAktif');
    if (elAktif) elAktif.textContent = aktifList.length;

    const elNonaktif = document.getElementById('statTotalNonaktif');
    if (elNonaktif) elNonaktif.textContent = nonaktifList.length;

    const elUnit = document.getElementById('statTotalUnit');
    if (elUnit) {
        const unitSet = new Set(aktifList.map(k => k.divisi_id).filter(Boolean).map(String));
        elUnit.textContent = unitSet.size;
    }

    const elJab = document.getElementById('statTotalJabatan');
    if (elJab) {
        const jabSet = new Set(aktifList.map(k => (k.jabatan || '').trim()).filter(Boolean));
        elJab.textContent = jabSet.size;
    }
}

function applyRowUpdateToLocalList(row) {
    if (!row || !row.id) return;
    const idx = dataKaryawanList.findIndex(x => Number(x.id) === Number(row.id));
    if (idx !== -1) {
        dataKaryawanList[idx] = { ...dataKaryawanList[idx], ...row };
    } else {
        dataKaryawanList.push(row);
    }
    dataKaryawanList.forEach(item => {
        if (Number(item.atasan_id) === Number(row.id)) {
            item.nama_atasan = row.nama;
        }
    });
    dataKaryawanList.sort((a, b) => {
        const cmpNip = String(a.nip || '').localeCompare(String(b.nip || ''));
        return cmpNip !== 0 ? cmpNip : String(a.nama || '').localeCompare(String(b.nama || ''));
    });
}

async function loadDataKaryawan() {
    const r = await apiFetch('karyawan', 'list', {}, 'GET');
    if (!r.ok) return;
    dataKaryawanList = r.data || [];
    updateStatCardsKaryawan();
    filterDataKaryawan();
}

function renderTabelKaryawan(list) {
    if (!document.getElementById('karyawanBody')) return;
    const colspan = CAN_MANAGE_MASTER ? 9 : 8;
    if (!list.length) {
        document.getElementById('karyawanBody').innerHTML = `<tr><td colspan="${colspan}" class="empty-row">Tidak ada data karyawan yang sesuai.</td></tr>`;
        return;
    }

    const html = list.map(k => {
        const statusBadge = k.status === 'aktif' 
            ? '<span class="badge b-green">Aktif</span>' 
            : '<span class="badge b-gray">Nonaktif</span>';

        let aksi = '';
        if (CAN_MANAGE_MASTER) {
            const btnStatus = CAN_UBAH_STATUS ? `
                <button class="btn ${k.status === 'aktif' ? 'btn-danger' : 'btn-primary'} btn-sm btn-icon" onclick="toggleStatusKaryawan(${k.id}, '${k.status === 'aktif' ? 'nonaktif' : 'aktif'}')" title="${k.status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan'}">
                    <i data-lucide="${k.status === 'aktif' ? 'user-x' : 'user-check'}"></i>
                </button>
            ` : `
                <button class="btn btn-sm btn-icon" disabled style="opacity:0.35;cursor:not-allowed;background:var(--border);color:var(--muted)" title="Hanya Manajer HRD yang dapat mengubah status aktif/nonaktif">
                    <i data-lucide="${k.status === 'aktif' ? 'user-x' : 'user-check'}"></i>
                </button>
            `;

            const btnHapus = CAN_HAPUS_KARYAWAN ? `
                <button class="btn btn-danger btn-sm btn-icon" onclick="hapusKaryawan(${k.id}, '${esc(k.nama)}', '${esc(k.nip)}')" title="Hapus Data Karyawan">
                    <i data-lucide="trash-2"></i>
                </button>
            ` : '';

            aksi = `
                <button class="btn btn-outline btn-sm btn-icon" onclick="bukaEditKaryawan(${k.id})" title="Edit Data"><i data-lucide="edit-3"></i></button>
                ${btnStatus}
                ${btnHapus}
            `;
        }

        return `<tr>
            <td style="font-weight:600;font-family:monospace;font-size:12.5px;color:var(--primary)">${esc(k.nip)}</td>
            <td style="font-weight:600">${esc(k.nama)}</td>
            <td><span class="badge b-gray" style="font-size:12px;font-weight:500">${esc(k.jabatan)}</span></td>
            <td>${esc(k.nama_unit) || '-'}</td>
            <td>${esc(k.nama_atasan) ? `<span style="font-size:12px;color:var(--text)"><i data-lucide="corner-down-right" style="width:12px;height:12px;color:var(--muted);vertical-align:-2px"></i> ${esc(k.nama_atasan)}</span>` : '<span style="color:#9ca3af;font-size:12px">-</span>'}</td>
            <td style="font-size:12px">${esc(k.tanggal_masuk) || '-'}</td>
            <td style="font-size:12px">${esc(k.kontak) || '-'}</td>
            <td style="text-align:center">${statusBadge}</td>
            ${CAN_MANAGE_MASTER ? `<td class="actions-cell no-print" style="display:table-cell !important;text-align:center;vertical-align:middle;white-space:nowrap;width:135px;min-width:135px"><div style="display:inline-flex;align-items:center;justify-content:center;gap:6px;flex-wrap:nowrap">${aksi}</div></td>` : ''}
        </tr>`;
    }).join('');

    updateDOM('karyawanBody', html);
}

function filterDataKaryawan() {
    const q = document.getElementById('cariKaryawan').value.toLowerCase().trim();
    const qClean = q.replace(/^nip\.?\s*/i, '');
    const jab = document.getElementById('filterJabatan').value.toLowerCase();
    const div = document.getElementById('filterDivisi').value;

    const filtered = dataKaryawanList.filter(k => {
        const cocokKata = !q || (k.nip && (k.nip.toLowerCase().includes(q) || (qClean && k.nip.toLowerCase().includes(qClean)))) || 
                               (k.nama && k.nama.toLowerCase().includes(q)) || 
                               (k.jabatan && k.jabatan.toLowerCase().includes(q));
        const cocokJab = !jab || (k.jabatan && k.jabatan.toLowerCase() === jab);
        const cocokDiv = !div || String(k.divisi_id) === String(div);
        return cocokKata && cocokJab && cocokDiv;
    });

    renderTabelKaryawan(filtered);
}

function bukaModalKaryawan() {
    document.getElementById('modalKaryawanTitle').textContent = 'Tambah Karyawan Baru';
    document.getElementById('kId').value = '';
    const nipInput = document.getElementById('kNip');
    nipInput.value = '';
    nipInput.readOnly = false;
    document.getElementById('kNama').value = '';
    document.getElementById('kJabatan').value = '';
    document.getElementById('kDivisi').value = '';
    document.getElementById('kTanggalMasuk').value = '';
    document.getElementById('kKontak').value = '';
    document.getElementById('kAtasan').value = '';
    document.getElementById('kUser').value = '';
    document.getElementById('kStatusWrapper').style.display = 'none';
    const hintNip = document.getElementById('hintNipAuto');
    if (hintNip) hintNip.style.display = 'block';

    document.getElementById('kDivisi').disabled = false;
    document.getElementById('kAtasan').disabled = !CAN_KELOLA_STRUKTUR;
    document.getElementById('kStatus').disabled = !CAN_UBAH_STATUS;
    const hDiv = document.getElementById('hintDivisiLock'); if (hDiv) hDiv.style.display = 'none';
    const hAtas = document.getElementById('hintAtasanLock'); if (hAtas) hAtas.style.display = CAN_KELOLA_STRUKTUR ? 'none' : 'block';
    const hStat = document.getElementById('hintStatusLock'); if (hStat) hStat.style.display = 'none';

    openModal('modalKaryawan');
}

async function onDivisiChangeKaryawan(divisiId) {
    const isEdit = Boolean(document.getElementById('kId').value);
    if (isEdit) return;

    const nipInput = document.getElementById('kNip');
    if (!divisiId) {
        nipInput.value = '';
        nipInput.readOnly = false;
        return;
    }

    const cachedNext = dropdownOptions?.next_nip_by_divisi?.[divisiId];
    if (cachedNext) {
        nipInput.value = cachedNext;
        nipInput.readOnly = true;
    }

    const r = await apiFetch('karyawan', 'next_nip', { divisi_id: divisiId }, 'GET');
    if (r.ok && r.data && r.data.nip) {
        nipInput.value = r.data.nip;
        nipInput.readOnly = true;
    } else if (!cachedNext) {
        nipInput.readOnly = false;
    }
}

function bukaEditKaryawan(id) {
    const k = dataKaryawanList.find(x => Number(x.id) === Number(id));
    if (!k) return toast('Data karyawan tidak ditemukan.', 'error');

    document.getElementById('modalKaryawanTitle').textContent = 'Edit Data Karyawan: ' + k.nama;
    document.getElementById('kId').value = k.id;
    const nipInput = document.getElementById('kNip');
    nipInput.value = k.nip || '';
    nipInput.readOnly = false;
    const hintNip = document.getElementById('hintNipAuto');
    if (hintNip) hintNip.style.display = 'none';
    document.getElementById('kNama').value = k.nama || '';
    document.getElementById('kJabatan').value = k.jabatan || '';
    document.getElementById('kDivisi').value = k.divisi_id || '';
    document.getElementById('kTanggalMasuk').value = k.tanggal_masuk || '';
    document.getElementById('kKontak').value = k.kontak || '';
    document.getElementById('kAtasan').value = k.atasan_id || '';
    document.getElementById('kUser').value = k.user_id || '';
    document.getElementById('kStatus').value = k.status || 'aktif';
    document.getElementById('kStatusWrapper').style.display = 'block';

    const hDiv = document.getElementById('hintDivisiLock');
    const hAtas = document.getElementById('hintAtasanLock');
    document.getElementById('kDivisi').disabled = false;
    if (hDiv) hDiv.style.display = 'none';

    if (!CAN_KELOLA_STRUKTUR) {
        document.getElementById('kAtasan').disabled = true;
        if (hAtas) hAtas.style.display = 'block';
    } else {
        document.getElementById('kAtasan').disabled = false;
        if (hAtas) hAtas.style.display = 'none';
    }

    const hStat = document.getElementById('hintStatusLock');
    if (!CAN_UBAH_STATUS) {
        document.getElementById('kStatus').disabled = true;
        if (hStat) hStat.style.display = 'block';
    } else {
        document.getElementById('kStatus').disabled = false;
        if (hStat) hStat.style.display = 'none';
    }

    if (window.lucide) lucide.createIcons();
    openModal('modalKaryawan');
}

async function simpanKaryawan(confirmSameName = false) {
    const id = document.getElementById('kId').value;
    const nip = document.getElementById('kNip').value.trim();
    const nama = document.getElementById('kNama').value.trim();
    const jabatan = document.getElementById('kJabatan').value.trim();
    const divisi_id = document.getElementById('kDivisi').value;
    const tanggal_masuk = document.getElementById('kTanggalMasuk').value;
    const kontak = document.getElementById('kKontak').value.trim();
    const atasan_id = document.getElementById('kAtasan').value;
    const user_id = document.getElementById('kUser').value;
    const status = document.getElementById('kStatus').value;

    if ((!nip && !divisi_id) || !nama || !jabatan) {
        return showModalError('modalKaryawan', 'Nama Lengkap, Jabatan, serta Unit/Divisi (atau NIP) wajib diisi.');
    }

    const payload = { nip, nama, jabatan, divisi_id, tanggal_masuk, kontak, atasan_id, user_id };
    if (confirmSameName) {
        payload.confirm_same_name = '1';
    }
    const action = id ? 'edit' : 'tambah';
    if (id) {
        payload.id = id;
        payload.status = status;
    }

    const oldRow = id ? dataKaryawanList.find(x => Number(x.id) === Number(id)) : null;
    const oldJabatan = oldRow ? (oldRow.jabatan || '') : '';

    const r = await apiFetch('karyawan', action, payload);
    if (r.ok) {
        const finalNip = r.data?.nip || nip;
        toast(id ? 'Data karyawan berhasil diperbarui.' : `Karyawan baru berhasil ditambahkan (${finalNip}).`, 'success');
        closeModal('modalKaryawan');

        const selFilterJab = document.getElementById('filterJabatan');
        if (selFilterJab && selFilterJab.value && oldJabatan && selFilterJab.value.toLowerCase() === oldJabatan.toLowerCase() && jabatan.toLowerCase() !== oldJabatan.toLowerCase()) {
            const stillHasOldJab = dataKaryawanList.some(x => Number(x.id) !== Number(id) && (x.jabatan || '').toLowerCase() === oldJabatan.toLowerCase());
            if (!stillHasOldJab) {
                selFilterJab.value = '';
            }
        }

        if (r.data?.row) {
            applyRowUpdateToLocalList(r.data.row);
            updateStatCardsKaryawan();
            filterDataKaryawan();
        }

        await Promise.all([loadDropdowns(), loadDataKaryawan()]);
        if (document.getElementById('tabRekap')?.style.display === 'block') {
            loadRekapKaryawan();
        }
    } else if (r.warning_same_name) {
        const lanjut = await customConfirm(r.msg, 'Ya, Tetap Simpan', 'btn-primary');
        if (lanjut) {
            return simpanKaryawan(true);
        }
    } else {
        showModalError('modalKaryawan', r.msg);
    }
}

async function toggleStatusKaryawan(id, newStatus) {
    const konfirm = await customConfirm(`Yakin ingin mengubah status karyawan ini menjadi ${newStatus.toUpperCase()}?`);
    if (!konfirm) return;
    const r = await apiFetch('karyawan', 'toggle_status', { id, status: newStatus });
    if (r.ok) {
        toast('Status karyawan berhasil diubah.', 'success');
        if (r.data?.row) {
            applyRowUpdateToLocalList(r.data.row);
            updateStatCardsKaryawan();
            filterDataKaryawan();
        }
        await Promise.all([loadDropdowns(), loadDataKaryawan()]);
    } else {
        toast(r.msg, 'error');
    }
}

async function hapusKaryawan(id, nama, nip) {
    const konfirm = await customConfirm(
        `Yakin ingin menghapus data karyawan "${nama}" (${nip}) secara permanen? Tindakan ini tidak dapat dibatalkan.`,
        'Ya, Hapus',
        'btn-danger'
    );
    if (!konfirm) return;
    const r = await apiFetch('karyawan', 'hapus', { id });
    if (r.ok) {
        toast(`Data karyawan "${nama}" berhasil dihapus.`, 'success');
        dataKaryawanList = dataKaryawanList.filter(x => Number(x.id) !== Number(id));
        updateStatCardsKaryawan();
        filterDataKaryawan();
        await Promise.all([loadDropdowns(), loadDataKaryawan()]);
    } else {
        toast(r.msg, 'error');
    }
}

/* ── 2. LOGIC TUGAS RUTIN HARIAN (CSO / GARDENER MANDIRI) ── */
function handleRutinFotosSelect(input) {
    const files = Array.from(input.files || []);
    for (const file of files) {
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) {
            toast('File "' + file.name + '" bukan format gambar JPG, PNG, atau WebP.', 'error');
            continue;
        }
        if (file.size > 5 * 1024 * 1024) {
            toast('Ukuran file "' + file.name + '" melebihi batas 5MB.', 'error');
            continue;
        }
        if (selectedRutinFiles.length + existingRutinFotos.length >= 3) {
            toast('Maksimal 3 foto dokumentasi per tugas rutin.', 'warning');
            break;
        }
        selectedRutinFiles.push(file);
    }
    input.value = '';
    renderRutinFotosPreview();
}

function renderRutinFotosPreview() {
    const container = document.getElementById('rtFotosPreviewContainer');
    if (!container) return;
    container.innerHTML = '';

    // Foto lama yang sudah ada di database
    existingRutinFotos.forEach((f, idx) => {
        const div = document.createElement('div');
        div.style = 'position:relative;width:75px;height:75px;border-radius:6px;overflow:hidden;border:1px solid var(--border);flex-shrink:0';
        div.innerHTML = `
            <img src="${esc(f.url)}" style="width:100%;height:100%;object-fit:cover;cursor:pointer" onclick="bukaModalFoto('${esc(f.url)}', 'Dokumentasi Tugas')">
            <button type="button" class="btn btn-danger btn-icon" style="position:absolute;top:2px;right:2px;width:20px;height:20px;padding:0;border-radius:50%" onclick="removeExistingRutinFoto(${idx})" title="Hapus foto"><i data-lucide="x" style="width:12px;height:12px"></i></button>
        `;
        container.appendChild(div);
    });

    // Foto baru yang baru dipilih
    selectedRutinFiles.forEach((file, idx) => {
        const div = document.createElement('div');
        div.style = 'position:relative;width:75px;height:75px;border-radius:6px;overflow:hidden;border:2px solid var(--primary);flex-shrink:0';
        const blobUrl = URL.createObjectURL(file);
        div.innerHTML = `
            <img src="${blobUrl}" style="width:100%;height:100%;object-fit:cover">
            <button type="button" class="btn btn-danger btn-icon" style="position:absolute;top:2px;right:2px;width:20px;height:20px;padding:0;border-radius:50%" onclick="removeSelectedRutinFile(${idx})" title="Batal pilih"><i data-lucide="x" style="width:12px;height:12px"></i></button>
        `;
        container.appendChild(div);
    });

    const totalCount = existingRutinFotos.length + selectedRutinFiles.length;
    const dropzone = document.getElementById('rtFotoDropzone');
    if (dropzone) {
        dropzone.style.display = totalCount >= 3 ? 'none' : 'block';
    }
    const countInfo = document.getElementById('rtFotoCountInfo');
    if (countInfo) {
        countInfo.textContent = `${totalCount} / 3 Foto`;
    }
    if (window.lucide) lucide.createIcons({ root: container });
}

function removeSelectedRutinFile(idx) {
    selectedRutinFiles.splice(idx, 1);
    renderRutinFotosPreview();
}

function removeExistingRutinFoto(idx) {
    const f = existingRutinFotos[idx];
    if (f && f.id) {
        hapusRutinFotoIds.push(f.id);
    }
    existingRutinFotos.splice(idx, 1);
    renderRutinFotosPreview();
}

function bukaModalTugasRutinSaya(editTask = null) {
    const timeNow = getJakartaTimeNow();
    if (!editTask && timeNow < JAM_MULAI_RUTIN) {
        toast(`Pengisian tugas rutin mandiri dibuka mulai pukul ${JAM_MULAI_RUTIN} WIB. (Waktu saat ini: ${timeNow} WIB)`, 'warning');
        return;
    }

    selectedRutinFiles = [];
    existingRutinFotos = [];
    hapusRutinFotoIds = [];

    if (editTask) {
        document.getElementById('modalTugasRutinTitle').textContent = 'Edit Tugas Rutin Harian';
        document.getElementById('rtTaskId').value = editTask.id;
        document.getElementById('rtDeskripsi').value = editTask.deskripsi || '';
        document.getElementById('rtStatus').value = editTask.status || 'dikerjakan';
        document.getElementById('rtJam').value = editTask.jam ? editTask.jam.substring(0,5) : '';
        existingRutinFotos = Array.isArray(editTask.fotos) ? [...editTask.fotos] : [];
    } else {
        document.getElementById('modalTugasRutinTitle').textContent = 'Tambah Tugas Rutin Harian';
        document.getElementById('rtTaskId').value = '';
        document.getElementById('rtDeskripsi').value = '';
        document.getElementById('rtStatus').value = 'dikerjakan';
        document.getElementById('rtJam').value = '';
    }

    renderRutinFotosPreview();
    openModal('modalTugasRutinSaya');
}

async function simpanTugasRutinSaya() {
    const id = document.getElementById('rtTaskId').value;
    const deskripsi = document.getElementById('rtDeskripsi').value.trim();
    const status = document.getElementById('rtStatus').value;
    const jam = document.getElementById('rtJam').value;

    if (!deskripsi) {
        return showModalError('modalTugasRutinSaya', 'Deskripsi tugas rutin harian wajib diisi.');
    }

    const totalFotos = existingRutinFotos.length + selectedRutinFiles.length;
    if (status === 'selesai' && totalFotos < 1) {
        return showModalError('modalTugasRutinSaya', 'Menandai tugas rutin SELESAI WAJIB melampirkan minimal 1 foto bukti dokumentasi.');
    }

    const btn = document.getElementById('btnSimpanRutin');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Menyimpan...';
        if (window.lucide) lucide.createIcons({ root: btn });
    }

    const fd = new FormData();
    fd.append('deskripsi', deskripsi);
    fd.append('status', status);
    if (jam) fd.append('jam', jam);

    if (id) {
        fd.append('id', id);
        if (hapusRutinFotoIds.length > 0) {
            fd.append('hapus_foto_ids', hapusRutinFotoIds.join(','));
        }
    }

    selectedRutinFiles.forEach(file => {
        fd.append('fotos[]', file);
    });

    const action = id ? 'update_kegiatan' : 'tambah_kegiatan';

    try {
        const r = await apiFetch('karyawan', action, fd);
        if (r.ok) {
            toast(id ? 'Tugas rutin berhasil diperbarui.' : 'Tugas rutin baru berhasil ditambahkan.', 'success');
            closeModal('modalTugasRutinSaya');
            loadDataKegiatan();
        } else {
            showModalError('modalTugasRutinSaya', r.msg);
        }
    } catch (e) {
        showModalError('modalTugasRutinSaya', 'Gagal memproses data tugas rutin.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="check"></i> Simpan Tugas Rutin';
            if (window.lucide) lucide.createIcons({ root: btn });
        }
    }
}

function checkPelaksanaLockStatus() {
    const todayStr = new Date().toISOString().substring(0, 10);
    const todayTasks = dataKegiatanList.filter(t => t.tanggal === todayStr && t.jenis === 'rutin');
    const isLockedToday = todayTasks.some(t => t.is_locked);
    const lockBanner = document.getElementById('pelaksanaLockBanner');
    const btnTambah = document.getElementById('btnTambahRutin');
    const btnTambahHdr = document.getElementById('btnTambahTugasRutinHeader');

    const timeNow = getJakartaTimeNow();
    const isBeforeBuka = (timeNow < JAM_MULAI_RUTIN);

    if (lockBanner) {
        if (isLockedToday) {
            const t0 = todayTasks.find(t => t.is_locked);
            const skor = t0?.nilai_harian || '-';
            const cat = t0?.catatan_harian ? ` — Catatan: "${esc(t0.catatan_harian)}"` : '';
            lockBanner.style.display = 'block';
            lockBanner.innerHTML = `
                <div class="alert" style="background:#e0f2fe;border:1px solid #7dd3fc;color:#0369a1;padding:12px 16px;border-radius:8px;display:flex;align-items:center;gap:10px">
                    <i data-lucide="lock" style="width:20px;height:20px;flex-shrink:0"></i>
                    <div>
                        <strong>Terkunci Permanen:</strong> Seluruh tugas rutin Anda hari ini telah dinilai oleh SPV Sapras (Skor: <strong>${skor} / 5</strong>${cat}). Anda tidak dapat menambah, mengubah, atau menghapus tugas hari ini.
                    </div>
                </div>`;
            if (btnTambah) { btnTambah.disabled = true; btnTambah.style.opacity = '0.5'; }
            if (btnTambahHdr) { btnTambahHdr.disabled = true; btnTambahHdr.style.opacity = '0.5'; }
        } else if (isBeforeBuka) {
            lockBanner.style.display = 'block';
            lockBanner.innerHTML = `
                <div class="alert" style="background:#fef3c7;border:1px solid #fde68a;color:#92400e;padding:12px 16px;border-radius:8px;display:flex;align-items:center;gap:10px">
                    <i data-lucide="clock" style="width:20px;height:20px;flex-shrink:0"></i>
                    <div>
                        <strong>Pengisian Belum Dibuka:</strong> Pengisian tugas rutin harian mandiri dibuka mulai pukul <strong>${JAM_MULAI_RUTIN} WIB</strong> (Waktu sekarang: ${timeNow} WIB).
                    </div>
                </div>`;
            if (btnTambah) { btnTambah.disabled = true; btnTambah.style.opacity = '0.5'; }
            if (btnTambahHdr) { btnTambahHdr.disabled = true; btnTambahHdr.style.opacity = '0.5'; }
        } else {
            lockBanner.style.display = 'none';
            if (btnTambah) { btnTambah.disabled = false; btnTambah.style.opacity = '1'; }
            if (btnTambahHdr) { btnTambahHdr.disabled = false; btnTambahHdr.style.opacity = '1'; }
        }
        if (window.lucide) lucide.createIcons({ root: lockBanner });
    }
}

/* ── 3. LOGIC MONITORING HARIAN (TEAM LEADER & SPV SAPRAS) ── */
async function loadMonitoringHarian() {
    const selKar = document.getElementById('monKaryawan');
    const selTgl = document.getElementById('monTanggal');
    if (!selKar || !selTgl) return;
    const karyawan_id = selKar.value;
    const tanggal = selTgl.value;

    if (!karyawan_id) {
        document.getElementById('monStatusBanner').innerHTML = `
            <div class="alert alert-info" style="background:#f0f9ff;border:1px solid #bae6fd;color:#0369a1;padding:12px 14px;border-radius:8px">
                Silakan pilih karyawan pelaksana untuk melihat pemantauan tugas rutin dan hasil kerja harian.
            </div>`;
        document.getElementById('monRutinBody').innerHTML = '<tr><td colspan="5" class="empty-row">Pilih karyawan pelaksana di atas.</td></tr>';
        document.getElementById('monNonRutinBody').innerHTML = '<tr><td colspan="6" class="empty-row">Pilih karyawan pelaksana di atas.</td></tr>';
        return;
    }

    const empObj = (dropdownOptions.karyawan_tugas || []).find(x => Number(x.id) === Number(karyawan_id));
    currentMonitoringData.karyawan = empObj;

    const [resTasks, resPh] = await Promise.all([
        apiFetch('karyawan', 'list_kegiatan', { karyawan_id, tanggal }, 'GET'),
        apiFetch('karyawan', 'get_penilaian_harian', { karyawan_id, tanggal }, 'GET')
    ]);

    const tasks = resTasks.ok ? (resTasks.data || []) : [];
    const ph = resPh.ok ? resPh.data : null;
    currentMonitoringData.tasks = tasks;
    currentMonitoringData.penilaianHarian = ph;

    renderMonitoringView(tasks, ph, empObj, tanggal);
}

function renderMonitoringView(tasks, ph, empObj, tanggal) {
    const todayStr = new Date().toISOString().substring(0, 10);
    const isToday = (tanggal === todayStr);
    const isPast = (tanggal < todayStr);
    const isFuture = (tanggal > todayStr);

    const jabatan = empObj ? empObj.jabatan : '';
    const jamPulang = getJamPulang(jabatan);
    const timeNow = getJakartaTimeNow();
    const isAfterPulang = isAfterTime(timeNow, jamPulang);

    const routineTasks = tasks.filter(t => t.jenis === 'rutin');
    const nonRoutineTasks = tasks.filter(t => t.jenis === 'non-rutin');

    const countRutinEl = document.getElementById('monBadgeCountRutin');
    if (countRutinEl) {
        const selCount = routineTasks.filter(t => t.status === 'selesai').length;
        countRutinEl.innerHTML = `<span class="badge ${selCount === routineTasks.length && routineTasks.length > 0 ? 'b-green' : 'b-yellow'}" style="font-size:12px">${selCount} / ${routineTasks.length} Selesai</span>`;
    }
    const countNonRutinEl = document.getElementById('monBadgeCountNonRutin');
    if (countNonRutinEl) {
        countNonRutinEl.innerHTML = `<span class="badge b-purple" style="font-size:12px">${nonRoutineTasks.length} Tugas Khusus</span>`;
    }

    // 1. Status Banner
    let bannerHtml = '';
    if (ph) {
        const pInfo = getPredikatInfo(ph.nilai);
        bannerHtml = `
            <div style="background:#e0f2fe;border:1px solid #7dd3fc;border-radius:10px;padding:14px 18px;color:#0369a1">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div style="display:flex;align-items:center;gap:12px">
                        <div style="background:#0284c7;color:#fff;border-radius:8px;padding:8px 12px;text-align:center">
                            <div style="font-size:10px;text-transform:uppercase;letter-spacing:0.5px">Nilai Harian</div>
                            <div style="font-size:24px;font-weight:800;line-height:1.1">${ph.nilai} / 5</div>
                        </div>
                        <div>
                            <div style="display:flex;align-items:center;gap:8px">
                                <span class="badge b-blue" style="font-size:11px"><i data-lucide="lock" style="width:11px;height:11px;vertical-align:-1px"></i> Terkunci Permanen</span>
                                <span class="badge ${pInfo.badge}" style="font-size:11px">${pInfo.text}</span>
                            </div>
                            <div style="font-size:12px;margin-top:4px;color:#075985">
                                Dinilai oleh <strong>${esc(ph.penilai_nama)}</strong> pada ${esc(ph.created_at || '-')}
                            </div>
                        </div>
                    </div>
                    <div style="font-size:12px;background:#fff;border:1px solid #bae6fd;padding:8px 14px;border-radius:6px;max-width:380px">
                        <strong>Catatan Evaluasi:</strong> ${esc(ph.catatan || 'Tidak ada catatan evaluasi.')}
                    </div>
                </div>
            </div>`;
    } else if (isFuture) {
        bannerHtml = `
            <div style="background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;color:#4b5563">
                <i data-lucide="info" style="width:14px;height:14px;vertical-align:-2px"></i>
                Tanggal monitoring berada di masa depan. Penilaian harian hanya dapat dilakukan pada tanggal pelaksanaan atau setelahnya.
            </div>`;
    } else if (isToday && !isAfterPulang) {
        bannerHtml = `
            <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;color:#92400e;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                    <span class="badge b-yellow" style="font-size:11px"><i data-lucide="clock" style="width:11px;height:11px;vertical-align:-1px"></i> Belum Jam Pulang (${jamPulang} WIB)</span>
                    <span style="font-size:12.5px;margin-left:8px">Karyawan <strong>${esc(empObj ? empObj.nama : '')}</strong> (${esc(jabatan)}) memiliki jam pulang pukul <strong>${jamPulang} WIB</strong>.</span>
                </div>
                <div style="font-size:12px;color:#b45309">
                    Waktu saat ini: <strong>${timeNow} WIB</strong>. Penilaian harian dibuka setelah jam pulang.
                </div>
            </div>`;
    } else {
        if (routineTasks.length === 0) {
            bannerHtml = `
                <div style="background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;color:#6b7280;display:flex;align-items:center;gap:10px">
                    <span class="badge b-gray" style="font-size:11px">Belum Ada Tugas</span>
                    <span style="font-size:12.5px">Tidak ada tugas rutin pada tanggal ini. Penilaian harian membutuhkan minimal 1 tugas rutin.</span>
                </div>`;
        } else if (IS_SPV_SAPRAS) {
            bannerHtml = `
                <div style="background:#fff1f2;border:1px solid #fecdd3;border-radius:8px;padding:12px 16px;color:#9f1239;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                    <div>
                        <span class="badge b-red" style="font-size:11px"><i data-lucide="alert-circle" style="width:11px;height:11px;vertical-align:-1px"></i> Belum Dinilai</span>
                        <span style="font-size:12.5px;margin-left:8px">Tersedia <strong>${routineTasks.length} tugas rutin</strong> siap dinilai untuk tanggal ini.</span>
                    </div>
                    <div>
                        <button class="btn btn-primary btn-sm" onclick="bukaModalNilaiHarian()"><i data-lucide="award"></i> Beri Nilai Harian</button>
                    </div>
                </div>`;
        } else {
            bannerHtml = `
                <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;color:#92400e">
                    <span class="badge b-yellow" style="font-size:11px"><i data-lucide="clock" style="width:11px;height:11px;vertical-align:-1px"></i> Menunggu Penilaian SPV Sapras</span>
                    <span style="font-size:12.5px;margin-left:8px">Tersedia ${routineTasks.length} tugas rutin. Penilaian harian akan diberikan oleh SPV Sapras.</span>
                </div>`;
        }
    }
    document.getElementById('monStatusBanner').innerHTML = bannerHtml;

    // 2. Render Tabel Tugas Rutin (Read-only)
    renderMonitoringRutinTable(routineTasks, ph);

    // 3. Render Tabel Tugas Non-Rutin
    renderMonitoringNonRutinTable(nonRoutineTasks);

    if (window.lucide) lucide.createIcons();
}

function renderMonitoringRutinTable(tasks, ph) {
    const tbody = document.getElementById('monRutinBody');
    if (!tbody) return;
    if (!tasks.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-row">Tidak ada tugas rutin pada tanggal ini.</td></tr>';
        return;
    }
    const kgBadge = { ditugaskan: 'b-yellow', dikerjakan: 'b-blue', selesai: 'b-green' };
    const kgLabel = { ditugaskan: 'Ditugaskan', dikerjakan: 'Dikerjakan', selesai: 'Selesai' };

    const html = tasks.map(t => {
        let jamHtml = t.jam ? `<span style="font-family:monospace;font-weight:600;font-size:12px">${esc(t.jam.substring(0,5))} WIB</span>` : '<span style="color:#9ca3af;font-size:12px">-</span>';
        let fotosHtml = renderGaleriThumbnailsHtml(t.fotos, t.deskripsi, t.karyawan_nama);

        let statusHarianHtml = '';
        if (ph) {
            statusHarianHtml = `<span class="badge b-blue" style="font-size:11px"><i data-lucide="lock" style="width:10px;height:10px;vertical-align:-1px"></i> Terkunci (${ph.nilai}/5)</span>`;
        } else {
            statusHarianHtml = '<span class="badge b-gray" style="font-size:11px">Menunggu Penilaian</span>';
        }

        return `<tr>
            <td style="white-space:nowrap">${jamHtml}</td>
            <td><div style="line-height:1.45;font-size:13px;color:var(--text);font-weight:500">${esc(t.deskripsi)}</div></td>
            <td style="text-align:center">${fotosHtml}</td>
            <td style="text-align:center"><span class="badge ${kgBadge[t.status] || 'b-gray'}">${kgLabel[t.status] || t.status}</span></td>
            <td style="text-align:center">${statusHarianHtml}</td>
        </tr>`;
    }).join('');

    tbody.innerHTML = html;
}

function renderMonitoringNonRutinTable(tasks) {
    const tbody = document.getElementById('monNonRutinBody');
    if (!tbody) return;
    if (!tasks.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-row">Tidak ada tugas non-rutin pada tanggal ini.</td></tr>';
        return;
    }
    const kgBadge = { ditugaskan: 'b-yellow', dikerjakan: 'b-blue', selesai: 'b-green' };
    const kgLabel = { ditugaskan: 'Ditugaskan', dikerjakan: 'Dikerjakan', selesai: 'Selesai' };

    const html = tasks.map(t => {
        let evaluasiHtml = '';
        if (t.status !== 'selesai') {
            evaluasiHtml = '<span class="badge b-gray" style="font-size:11px"><i data-lucide="clock" style="width:11px;height:11px;vertical-align:-1px"></i> Menunggu Selesai</span>';
        } else if (!t.penilaian_id) {
            if (CAN_RATE && !IS_HRD) {
                evaluasiHtml = `<button class="btn btn-primary btn-sm" onclick="bukaModalNilaiTugas(${t.id}, ${t.karyawan_id}, '${esc(t.karyawan_nama)}', '${esc(t.jabatan)}', '${esc(t.deskripsi)}', '${esc(t.foto_laporan || '')}')"><i data-lucide="award"></i> Beri Nilai</button>`;
            } else {
                evaluasiHtml = '<span class="badge b-yellow" style="font-size:11px">Belum Dinilai</span>';
            }
        } else {
            const info = getPredikatInfo(t.skor_nilai);
            evaluasiHtml = `<button class="btn btn-outline btn-sm" style="border-color:var(--primary);color:var(--primary);padding:3px 8px;font-size:11.5px" onclick="bukaModalLihatEvaluasi('${esc(t.karyawan_nama)}', '${esc(t.jabatan)}', '${esc(t.deskripsi)}', ${t.skor_nilai}, '${esc(t.penilai_nama || '-')}', '${esc(t.periode_penilaian || '-')}', '${esc(t.catatan_penilaian || '-')}', '${esc(t.foto_laporan || '')}')"><i data-lucide="award" style="width:12px;height:12px;vertical-align:-1px"></i> <strong>${t.skor_nilai} / 5</strong> (${info.text.split(' ')[0]})</button>`;
        }

        const fotosHtml = renderGaleriThumbnailsHtml(t.fotos, t.deskripsi, t.karyawan_nama);

        let tglHtml = `<div>${esc(t.tanggal)}</div>`;
        if (t.tenggat) {
            tglHtml += `<div style="font-size:11px;color:var(--muted)">Tenggat: ${esc(t.tenggat)}</div>`;
        }

        return `<tr>
            <td style="white-space:nowrap;font-size:12px">${tglHtml}</td>
            <td><div style="line-height:1.45;font-size:13px;color:var(--text);font-weight:500">${esc(t.deskripsi)}</div></td>
            <td style="font-size:12px;color:var(--muted)">${esc(t.assigner_nama || '-')}</td>
            <td style="text-align:center"><span class="badge ${kgBadge[t.status] || 'b-gray'}">${kgLabel[t.status] || t.status}</span></td>
            <td>
                <div style="font-size:12.5px;color:var(--text)">${esc(t.hasil_laporan || '-')}</div>
                ${fotosHtml}
            </td>
            <td style="text-align:center">${evaluasiHtml}</td>
        </tr>`;
    }).join('');

    tbody.innerHTML = html;
}

function bukaModalNilaiHarian() {
    const emp = currentMonitoringData.karyawan;
    const tgl = document.getElementById('monTanggal').value;
    if (!emp || !tgl) {
        return toast('Pilih karyawan dan tanggal monitoring terlebih dahulu.', 'warning');
    }

    const routineTasks = (currentMonitoringData.tasks || []).filter(t => t.jenis === 'rutin');
    if (!routineTasks.length) {
        return toast('Tidak ada tugas rutin pada tanggal ini untuk dinilai.', 'warning');
    }

    document.getElementById('pnhKaryawanId').value = emp.id;
    document.getElementById('pnhTanggal').value = tgl;
    document.getElementById('pnhKaryawanNama').textContent = `${emp.nama} (${emp.jabatan})`;
    document.getElementById('pnhTanggalTeks').textContent = tgl;
    document.getElementById('pnhJamPulangTeks').textContent = getJamPulang(emp.jabatan) + ' WIB';
    
    const selCount = routineTasks.filter(t => t.status === 'selesai').length;
    document.getElementById('pnhSummaryTugas').innerHTML = `Terdapat <strong>${routineTasks.length} tugas rutin</strong> (${selCount} ditandai selesai) yang akan dievaluasi.`;

    document.getElementById('pnhNilai').value = '';
    document.getElementById('pnhCatatan').value = '';
    document.querySelectorAll('.pnh-btn-score').forEach(b => {
        b.className = 'btn btn-outline pnh-btn-score';
    });
    const bBadge = document.getElementById('pnhGradeBadge');
    if (bBadge) bBadge.style.display = 'none';

    openModal('modalPenilaianHarian');
}

function setPnhScore(score) {
    document.getElementById('pnhNilai').value = score;
    document.querySelectorAll('.pnh-btn-score').forEach(b => {
        const s = parseInt(b.dataset.score, 10);
        if (s === score) {
            b.className = 'btn btn-primary pnh-btn-score';
        } else {
            b.className = 'btn btn-outline pnh-btn-score';
        }
    });
    const info = getPredikatInfo(score);
    const badge = document.getElementById('pnhGradeBadge');
    if (badge) {
        badge.style.display = 'inline-block';
        badge.className = 'badge ' + info.badge;
        badge.textContent = 'Predikat: ' + info.text;
    }
}

async function simpanNilaiHarian() {
    const karyawan_id = document.getElementById('pnhKaryawanId').value;
    const tanggal = document.getElementById('pnhTanggal').value;
    const rawNilai = document.getElementById('pnhNilai').value;
    const catatan = document.getElementById('pnhCatatan').value.trim();

    if (!rawNilai) {
        return showModalError('modalPenilaianHarian', 'Silakan pilih skor nilai harian (1 sampai 5).');
    }
    const nilai = parseInt(rawNilai, 10);
    if (isNaN(nilai) || nilai < 1 || nilai > 5) {
        return showModalError('modalPenilaianHarian', 'Skor nilai harus antara 1 sampai 5.');
    }

    const konfirm = await customConfirm(
        'Yakin ingin menyimpan penilaian harian ini? Nilai tidak dapat diubah setelah disimpan.',
        'Ya, Simpan Permanen',
        'btn-primary'
    );
    if (!konfirm) return;

    const btn = document.getElementById('btnSimpanNilaiHarian');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Menyimpan & Mengunci...';
        if (window.lucide) lucide.createIcons({ root: btn });
    }

    try {
        const r = await apiFetch('karyawan', 'simpan_penilaian_harian', {
            karyawan_id,
            tanggal,
            nilai,
            catatan
        });

        if (r.ok) {
            toast('Penilaian harian berhasil disimpan dan terkunci permanen.', 'success');
            closeModal('modalPenilaianHarian');
            await loadMonitoringHarian();
            if (document.getElementById('tabRekap')?.style.display === 'block') {
                loadRekapKaryawan();
            }
            if (document.getElementById('tabPenilaian')?.style.display === 'block') {
                loadDataPenilaianHarian();
            }
        } else {
            showModalError('modalPenilaianHarian', r.msg);
        }
    } catch (e) {
        showModalError('modalPenilaianHarian', 'Terjadi kesalahan sistem saat menyimpan.');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="award"></i> Simpan & Kunci Nilai';
            if (window.lucide) lucide.createIcons({ root: btn });
        }
    }
}

/* ── 4. LOGIC MODAL FOTO BUKTI & GALERI ── */
function renderGaleriThumbnailsHtml(fotos, desc, nama) {
    if (!fotos || !fotos.length) {
        return '<span style="color:#9ca3af;font-size:11.5px">Tanpa foto bukti</span>';
    }
    const fotosJson = esc(JSON.stringify(fotos));
    const descEsc = esc(desc);
    const namaEsc = esc(nama);

    let html = `<div style="display:inline-flex;gap:4px;align-items:center;justify-content:center;flex-wrap:wrap">`;
    fotos.slice(0, 3).forEach((f, idx) => {
        html += `<img src="${esc(f.url)}" alt="Bukti" style="width:34px;height:34px;border-radius:4px;object-fit:cover;border:1px solid var(--border);cursor:pointer;transition:transform 0.15s" onclick="bukaModalFoto('${esc(f.url)}', '${descEsc}', 'Bukti Dokumentasi (${idx+1}/${fotos.length}) - ${namaEsc}', ${fotosJson})" title="Klik untuk memperbesar">`;
    });
    if (fotos.length > 3) {
        html += `<span class="badge b-gray" style="font-size:10px">+${fotos.length - 3}</span>`;
    }
    html += `</div>`;
    return html;
}

function bukaModalFoto(src, desc, title = 'Foto Bukti Pengerjaan Tugas', allFotos = []) {
    document.getElementById('imgPreviewBesar').src = src;
    document.getElementById('descPreviewBesar').textContent = desc || '-';
    document.getElementById('btnUnduhFoto').href = src;
    const titleEl = document.getElementById('judulLihatFoto');
    if (titleEl) titleEl.textContent = title;

    const wrap = document.getElementById('galeriThumbsWrapper');
    if (wrap) {
        if (Array.isArray(allFotos) && allFotos.length > 1) {
            wrap.style.display = 'flex';
            wrap.innerHTML = allFotos.map(f => {
                const isCur = (f.url === src || f.path === src);
                return `<img src="${esc(f.url)}" style="width:48px;height:48px;border-radius:6px;object-fit:cover;cursor:pointer;border:2px solid ${isCur ? 'var(--primary)' : 'var(--border)'}" onclick="switchModalLihatFoto('${esc(f.url)}', this)">`;
            }).join('');
        } else {
            wrap.style.display = 'none';
            wrap.innerHTML = '';
        }
    }

    openModal('modalLihatFoto');
}

function switchModalLihatFoto(newUrl, imgEl) {
    document.getElementById('imgPreviewBesar').src = newUrl;
    document.getElementById('btnUnduhFoto').href = newUrl;
    const wrap = document.getElementById('galeriThumbsWrapper');
    if (wrap) {
        wrap.querySelectorAll('img').forEach(im => im.style.borderColor = 'var(--border)');
        if (imgEl) imgEl.style.borderColor = 'var(--primary)';
    }
}

/* ── 5. LOGIC KEGIATAN KARYAWAN (ALL TASKS TABLE & FILTER) ── */
async function loadDataKegiatan() {
    const r = await apiFetch('karyawan', 'list_kegiatan', {}, 'GET');
    if (!r.ok) return;
    dataKegiatanList = r.data || [];

    if (IS_PELAKSANA_ONLY) {
        checkPelaksanaLockStatus();
    }
    filterDataKegiatan();
}

function renderTabelKegiatan(list) {
    if (!document.getElementById('kegiatanBody')) return;
    const todayStr = new Date().toISOString().substring(0, 10);
    const kgBadge = { ditugaskan: 'b-yellow', dikerjakan: 'b-blue', selesai: 'b-green' };
    const kgLabel = { ditugaskan: 'Ditugaskan', dikerjakan: 'Dikerjakan', selesai: 'Selesai' };

    if (IS_PELAKSANA_ONLY) {
        if (!list.length) {
            document.getElementById('kegiatanBody').innerHTML = '<tr><td colspan="7" class="empty-row">Belum ada tugas rutin yang dibuat.</td></tr>';
            return;
        }

        const html = list.map(t => {
            const isToday = (t.tanggal === todayStr);
            const isLocked = Boolean(t.is_locked);
            const isEditable = isToday && !isLocked;

            let jamHtml = `<div><strong>${esc(t.tanggal)}</strong></div>`;
            if (t.jam) {
                jamHtml += `<div style="font-size:11px;color:var(--muted);margin-top:2px"><i data-lucide="clock" style="width:10px;height:10px;vertical-align:-1px"></i> ${esc(t.jam.substring(0,5))} WIB</div>`;
            }

            const fotosHtml = renderGaleriThumbnailsHtml(t.fotos, t.deskripsi, t.karyawan_nama);

            let statusHarianHtml = '';
            if (isLocked) {
                statusHarianHtml = `<span class="badge b-blue" style="font-size:11px"><i data-lucide="lock" style="width:10px;height:10px;vertical-align:-1px"></i> Terkunci (${t.nilai_harian || '-'}/5)</span>`;
            } else if (isToday) {
                statusHarianHtml = '<span class="badge b-yellow" style="font-size:11px">Hari Ini</span>';
            } else {
                statusHarianHtml = '<span class="badge b-gray" style="font-size:11px">Belum Dinilai</span>';
            }

            let aksi = '';
            if (isEditable) {
                const tJson = esc(JSON.stringify(t));
                aksi = `
                    <button class="btn btn-outline btn-sm btn-icon" onclick='bukaModalTugasRutinSaya(${tJson})' title="Edit / Update Tugas"><i data-lucide="edit-3"></i></button>
                    <button class="btn btn-danger btn-sm btn-icon" onclick="hapusKegiatan(${t.id})" title="Hapus Tugas"><i data-lucide="trash-2"></i></button>
                `;
            } else {
                aksi = `<span class="badge b-gray" style="font-size:11px"><i data-lucide="lock" style="width:11px;height:11px;vertical-align:-1px"></i> Terkunci</span>`;
            }

            return `<tr>
                <td style="white-space:nowrap">${jamHtml}</td>
                <td style="text-align:center"><span class="badge b-teal" style="font-size:11px">Rutin</span></td>
                <td><div style="line-height:1.45;font-size:13px;color:var(--text);font-weight:500;max-width:320px">${esc(t.deskripsi)}</div></td>
                <td style="text-align:center">${fotosHtml}</td>
                <td style="text-align:center"><span class="badge ${kgBadge[t.status] || 'b-gray'}">${kgLabel[t.status] || t.status}</span></td>
                <td style="text-align:center">${statusHarianHtml}</td>
                <td class="actions-cell no-print" style="justify-content:center">${aksi}</td>
            </tr>`;
        }).join('');

        updateDOM('kegiatanBody', html);
        return;
    }

    // Default view untuk supervisor di all tasks table
    if (!list.length) {
        document.getElementById('kegiatanBody').innerHTML = '<tr><td colspan="9" class="empty-row">Belum ada kegiatan atau penugasan karyawan.</td></tr>';
        return;
    }

    const html = list.map(t => {
        let evaluasiHtml = '';
        if (t.jenis === 'rutin') {
            if (t.is_locked) {
                evaluasiHtml = `<span class="badge b-blue" style="font-size:11px"><i data-lucide="lock" style="width:10px;height:10px;vertical-align:-1px"></i> Nilai Harian (${t.nilai_harian}/5)</span>`;
            } else {
                evaluasiHtml = '<span class="badge b-gray" style="font-size:11px">Dinilai Harian</span>';
            }
        } else if (t.status !== 'selesai') {
            evaluasiHtml = '<span class="badge b-gray" style="font-size:11px"><i data-lucide="clock" style="width:11px;height:11px;vertical-align:-1px"></i> Menunggu Selesai</span>';
        } else if (!t.penilaian_id) {
            const canRateThisTask = CAN_RATE && !IS_HRD && (CURRENT_KARYAWAN_ID === 0 || Number(t.karyawan_id) !== Number(CURRENT_KARYAWAN_ID));
            if (canRateThisTask) {
                evaluasiHtml = `<button class="btn btn-primary btn-sm" onclick="bukaModalNilaiTugas(${t.id}, ${t.karyawan_id}, '${esc(t.karyawan_nama)}', '${esc(t.jabatan)}', '${esc(t.deskripsi)}', '${esc(t.foto_laporan || '')}')"><i data-lucide="award"></i> Beri Nilai</button>`;
            } else {
                evaluasiHtml = '<span class="badge b-yellow" style="font-size:11px">Belum Dinilai</span>';
            }
        } else {
            const info = getPredikatInfo(t.skor_nilai);
            evaluasiHtml = `<button class="btn btn-outline btn-sm" style="border-color:var(--primary);color:var(--primary);padding:3px 8px;font-size:11.5px" onclick="bukaModalLihatEvaluasi('${esc(t.karyawan_nama)}', '${esc(t.jabatan)}', '${esc(t.deskripsi)}', ${t.skor_nilai}, '${esc(t.penilai_nama || '-')}', '${esc(t.periode_penilaian || '-')}', '${esc(t.catatan_penilaian || '-')}', '${esc(t.foto_laporan || '')}')"><i data-lucide="award" style="width:12px;height:12px;vertical-align:-1px"></i> <strong>${t.skor_nilai} / 5</strong> (${info.text.split(' ')[0]})</button>`;
        }

        const fotosHtml = renderGaleriThumbnailsHtml(t.fotos, t.deskripsi, t.karyawan_nama);

        let jadwalHtml = `<div>${esc(t.tanggal)}</div>`;
        if (t.jam) {
            jadwalHtml += `<div style="font-size:11px;color:var(--muted)"><i data-lucide="clock" style="width:10px;height:10px;vertical-align:-1px"></i> ${esc(t.jam.substring(0,5))}</div>`;
        }
        if (t.tenggat) {
            const isOverdue = (t.status !== 'selesai' && t.tenggat < todayStr) || 
                              (t.status === 'selesai' && t.selesai_pada && t.selesai_pada.substring(0, 10) > t.tenggat);
            jadwalHtml += `<div style="font-size:11px;margin-top:2px;color:${isOverdue ? 'var(--danger)' : 'var(--muted)'};font-weight:${isOverdue ? '600' : 'normal'}">
                <i data-lucide="calendar" style="width:10px;height:10px;vertical-align:-1px"></i> Tgt: ${esc(t.tenggat)} ${isOverdue ? '<span class="badge b-red" style="font-size:9.5px;padding:1px 4px">Terlambat</span>' : ''}
            </div>`;
        }

        const jenisBadge = t.jenis === 'rutin'
            ? '<span class="badge b-teal" style="font-size:11px">Rutin</span>'
            : '<span class="badge b-purple" style="font-size:11px">Non-Rutin</span>';

        let aksi = '';
        if (t.jenis === 'non-rutin') {
            aksi = `<button class="btn btn-outline btn-sm" onclick="bukaUpdateKegiatan(${t.id}, '${esc(t.status)}', '${esc(t.deskripsi)}', '${esc(t.hasil_laporan)}', '${esc(t.foto_laporan || '')}')"><i data-lucide="check-square"></i> Lapor</button>`;
            if (CAN_KELOLA) {
                aksi += ` <button class="btn btn-danger btn-sm" onclick="hapusKegiatan(${t.id})" title="Hapus"><i data-lucide="trash-2"></i></button>`;
            }
        } else {
            aksi = `<span class="badge b-gray" style="font-size:11px">Tugas Mandiri</span>`;
        }

        return `<tr>
            <td style="white-space:nowrap;font-size:12px">${jadwalHtml}</td>
            <td style="text-align:center">${jenisBadge}</td>
            <td>
                <div style="font-weight:600">${esc(t.karyawan_nama)}</div>
                <div style="font-size:11px;color:var(--muted)">${esc(t.jabatan)}</div>
            </td>
            <td><div style="max-width:230px;line-height:1.4;font-size:12.5px;color:var(--text)">${esc(t.deskripsi)}</div></td>
            <td style="font-size:12px;color:var(--muted)">${esc(t.assigner_nama) || '-'}</td>
            <td style="text-align:center"><span class="badge ${kgBadge[t.status] || 'b-gray'}">${kgLabel[t.status] || t.status}</span></td>
            <td>
                <div style="font-size:12.5px;color:var(--text);max-width:200px">${esc(t.hasil_laporan) || '<span style="color:#9ca3af;font-size:11.5px">Belum ada laporan</span>'}</div>
                ${fotosHtml}
            </td>
            <td style="text-align:center">${evaluasiHtml}</td>
            <td class="actions-cell no-print" style="justify-content:center">${aksi}</td>
        </tr>`;
    }).join('');

    updateDOM('kegiatanBody', html);
}

function filterDataKegiatan() {
    const q = document.getElementById('cariKegiatan').value.toLowerCase().trim();
    const st = document.getElementById('filterStatusKegiatan').value;
    const jn = document.getElementById('filterJenisKegiatan')?.value || '';
    const wt = document.getElementById('filterWaktuKegiatan')?.value || '';
    const todayStr = new Date().toISOString().substring(0, 10);

    const filtered = dataKegiatanList.filter(t => {
        const cocokKata = !q || (t.deskripsi && t.deskripsi.toLowerCase().includes(q)) || 
                               (t.karyawan_nama && t.karyawan_nama.toLowerCase().includes(q));
        const cocokStatus = !st || t.status === st;
        const cocokJenis = !jn || t.jenis === jn;
        const cocokWaktu = !wt || (wt === 'hari_ini' ? t.tanggal === todayStr : true);
        return cocokKata && cocokStatus && cocokJenis && cocokWaktu;
    });

    renderTabelKegiatan(filtered);
}

function bukaModalKegiatan() {
    document.getElementById('kgKaryawan').value = '';
    document.getElementById('kgJenis').value = 'non-rutin';
    document.getElementById('kgDeskripsi').value = '';
    document.getElementById('kgTanggal').value = new Date().toISOString().substring(0, 10);
    document.getElementById('kgTenggat').value = '';
    document.getElementById('kgJam').value = '';
    openModal('modalKegiatan');
}

async function simpanKegiatan() {
    const karyawan_id = document.getElementById('kgKaryawan').value;
    const jenis = 'non-rutin'; // Selalu non-rutin untuk penugasan atasan
    const tanggal = document.getElementById('kgTanggal').value;
    const tenggat = document.getElementById('kgTenggat').value;
    const jam = document.getElementById('kgJam').value;
    const deskripsi = document.getElementById('kgDeskripsi').value.trim();

    if (!karyawan_id || !deskripsi || !tanggal) {
        return showModalError('modalKegiatan', 'Karyawan pelaksana, tanggal, dan deskripsi kegiatan wajib diisi.');
    }

    const payload = { karyawan_id, jenis, tanggal, deskripsi };
    if (tenggat) payload.tenggat = tenggat;
    if (jam) payload.jam = jam;

    const btn = document.getElementById('btnTugaskan');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Menugaskan...';
    }

    try {
        const r = await apiFetch('karyawan', 'tambah_kegiatan', payload);
        if (r.ok) {
            toast('Tugas non-rutin berhasil diberikan.', 'success');
            closeModal('modalKegiatan');
            loadMonitoringHarian();
            loadDataKegiatan();
            loadDropdowns();
        } else {
            showModalError('modalKegiatan', r.msg);
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="send"></i> Tugaskan';
            if (window.lucide) lucide.createIcons({ root: btn });
        }
    }
}

function bukaUpdateKegiatan(id, status, deskripsi, hasil, foto = '') {
    document.getElementById('updKgId').value = id;
    document.getElementById('updKgDeskripsi').textContent = deskripsi;
    document.getElementById('updKgStatus').value = status;
    document.getElementById('updKgHasil').value = hasil || '';
    document.getElementById('updKgFoto').value = '';
    document.getElementById('updKgHapusFoto').value = '0';

    if (foto) {
        document.getElementById('updFotoPreview').src = foto;
        document.getElementById('updFotoPlaceholder').style.display = 'none';
        document.getElementById('updFotoPreviewWrapper').style.display = 'block';
        if (window.lucide) lucide.createIcons({ root: document.getElementById('updFotoPreviewWrapper') });
    } else {
        document.getElementById('updFotoPreview').src = '';
        document.getElementById('updFotoPreviewWrapper').style.display = 'none';
        document.getElementById('updFotoPlaceholder').style.display = 'block';
    }

    openModal('modalUpdateKegiatan');
}

function previewUpdateFoto(input) {
    const file = input.files && input.files[0];
    if (file) {
        if (!file.type.startsWith('image/')) {
            toast('File harus berupa gambar (JPG, PNG, atau WebP)', 'error');
            input.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            toast('Ukuran gambar maksimal 5MB', 'error');
            input.value = '';
            return;
        }
        document.getElementById('updKgHapusFoto').value = '0';
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('updFotoPreview').src = e.target.result;
            document.getElementById('updFotoPlaceholder').style.display = 'none';
            document.getElementById('updFotoPreviewWrapper').style.display = 'block';
            if (window.lucide) lucide.createIcons({ root: document.getElementById('updFotoPreviewWrapper') });
        };
        reader.readAsDataURL(file);
    }
}

function hapusUpdateFoto() {
    document.getElementById('updKgFoto').value = '';
    document.getElementById('updFotoPreview').src = '';
    document.getElementById('updFotoPreviewWrapper').style.display = 'none';
    document.getElementById('updFotoPlaceholder').style.display = 'block';
    document.getElementById('updKgHapusFoto').value = '1';
}

async function simpanUpdateKegiatan() {
    const id = document.getElementById('updKgId').value;
    const status = document.getElementById('updKgStatus').value;
    const hasil_laporan = document.getElementById('updKgHasil').value.trim();
    const fotoFile = document.getElementById('updKgFoto').files[0];
    const hapusFoto = document.getElementById('updKgHapusFoto').value;

    const payload = { id, status, hasil_laporan };
    if (fotoFile) {
        payload.foto_laporan = fotoFile;
    } else if (hapusFoto === '1') {
        payload.hapus_foto = '1';
    }

    const r = await apiFetch('karyawan', 'update_kegiatan', payload);
    if (r.ok) {
        toast(status === 'selesai' ? 'Tugas telah ditandai selesai.' : 'Laporan kegiatan berhasil diperbarui.', 'success');
        closeModal('modalUpdateKegiatan');
        loadDataKegiatan();
        loadMonitoringHarian();
        loadDropdowns();
    } else {
        showModalError('modalUpdateKegiatan', r.msg);
    }
}

async function hapusKegiatan(id) {
    if (!await customConfirm('Yakin ingin menghapus penugasan kegiatan ini?')) return;
    const r = await apiFetch('karyawan', 'hapus_kegiatan', { id });
    if (r.ok) {
        toast('Penugasan berhasil dihapus.', 'success');
        loadDataKegiatan();
        loadMonitoringHarian();
        loadDropdowns();
    } else {
        toast(r.msg, 'error');
    }
}

/* ── 6. LOGIC REKAP KARYAWAN (BOBOT 85% HARIAN + 15% NON-RUTIN) ── */
async function loadRekapKaryawan() {
    const bulan = document.getElementById('filterBulanRekap').value;
    const divisi_id = document.getElementById('filterDivisiRekap').value;

    const params = {};
    if (bulan) params.bulan = bulan;
    if (divisi_id) params.divisi_id = divisi_id;

    const r = await apiFetch('karyawan', 'rekap_karyawan', params, 'GET');
    if (!r.ok) return;
    dataRekapList = r.data || [];
    renderTabelRekap(dataRekapList);
}

function renderHrdSummaryCards(list) {
    const el = document.getElementById('hrdRekapSummary');
    if (!el) return;

    const perUnit = {};
    const perJabatan = {};
    let aktifCount = 0;
    let nonaktifCount = 0;

    list.forEach(item => {
        const u = item.nama_unit || 'Tanpa Unit';
        perUnit[u] = (perUnit[u] || 0) + 1;

        const j = item.jabatan || 'Lainnya';
        perJabatan[j] = (perJabatan[j] || 0) + 1;

        if (item.status === 'aktif') aktifCount++;
        else nonaktifCount++;
    });

    const totalUnit = Object.keys(perUnit).length;
    const totalJabatan = Object.keys(perJabatan).length;

    el.innerHTML = `
        <div style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:10px 14px;flex:1;min-width:180px">
            <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase;font-weight:600">Total Karyawan Rekap</div>
            <div style="font-size:20px;font-weight:700;color:var(--primary);margin-top:2px">${list.length} Orang</div>
            <div style="font-size:11px;color:var(--muted)">${aktifCount} Aktif · ${nonaktifCount} Nonaktif</div>
        </div>
        <div style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:10px 14px;flex:1;min-width:180px">
            <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase;font-weight:600">Sebaran Unit / Divisi</div>
            <div style="font-size:20px;font-weight:700;color:#1e40af;margin-top:2px">${totalUnit} Unit</div>
            <div style="font-size:11px;color:var(--muted)">Tersebar di seluruh divisi</div>
        </div>
        <div style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:10px 14px;flex:1;min-width:180px">
            <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase;font-weight:600">Sebaran Jabatan</div>
            <div style="font-size:20px;font-weight:700;color:#0f766e;margin-top:2px">${totalJabatan} Jabatan</div>
            <div style="font-size:11px;color:var(--muted)">Struktur fungsional aktif</div>
        </div>
    `;
}

function renderTabelRekap(list) {
    if (!document.getElementById('rekapBody')) return;
    const colspan = IS_HRD ? 6 : 10;
    if (!list.length) {
        document.getElementById('rekapBody').innerHTML = `<tr><td colspan="${colspan}" class="empty-row">Tidak ada data rekapitulasi karyawan.</td></tr>`;
        return;
    }

    if (IS_HRD) {
        renderHrdSummaryCards(list);

        const html = list.map(r => {
            const statusBadge = r.status === 'aktif' 
                ? '<span class="badge b-green">Aktif</span>' 
                : '<span class="badge b-gray">Nonaktif</span>';

            return `<tr>
                <td style="font-weight:600;font-family:monospace;font-size:12.5px;color:var(--primary)">${esc(r.nip)}</td>
                <td style="font-weight:600">${esc(r.nama)}</td>
                <td><span class="badge b-gray" style="font-size:11.5px">${esc(r.jabatan)}</span></td>
                <td style="font-size:12px">${esc(r.nama_unit) || '-'}</td>
                <td style="text-align:center">${statusBadge}</td>
                <td style="font-size:12px;color:var(--muted)">${esc(r.tanggal_masuk || '-')}</td>
            </tr>`;
        }).join('');

        updateDOM('rekapBody', html);
        return;
    }

    const html = list.map(r => {
        const lateBadge = Number(r.hari_belum_dinilai) > 0
            ? `<span class="badge b-red" style="font-weight:700;font-size:11px">${r.hari_belum_dinilai} Hari</span>`
            : `<span style="color:#9ca3af;font-size:12px">0 Hari</span>`;

        let harianBadge = '<span style="color:#9ca3af;font-size:12px">-</span>';
        if (r.rata_harian !== null && r.rata_harian !== undefined) {
            const predH = getPredikatInfo(Math.round(r.rata_harian));
            harianBadge = `<strong>${r.rata_harian}</strong> <span class="badge ${predH.badge}" style="font-size:10px;padding:1px 5px">${predH.text.split(' ')[0]}</span>`;
        }

        let nonrutinBadge = '<span style="color:#9ca3af;font-size:12px">-</span>';
        if (r.rata_nonrutin !== null && r.rata_nonrutin !== undefined) {
            const predN = getPredikatInfo(Math.round(r.rata_nonrutin));
            nonrutinBadge = `<strong>${r.rata_nonrutin}</strong> <span class="badge ${predN.badge}" style="font-size:10px;padding:1px 5px">${predN.text.split(' ')[0]}</span>`;
        }

        let akhirBadge = '<span style="color:#9ca3af;font-size:12px">-</span>';
        if (r.nilai_akhir !== null && r.nilai_akhir !== undefined) {
            const predA = getPredikatInfo(Math.round(r.nilai_akhir));
            akhirBadge = `<strong style="font-size:13.5px;color:var(--primary)">${r.nilai_akhir}</strong> <span class="badge ${predA.badge}" style="font-size:11px">${predA.text.split(' ')[0]}</span>`;
        }

        return `<tr>
            <td style="font-weight:600;font-family:monospace;font-size:12.5px;color:var(--primary)">${esc(r.nip)}</td>
            <td style="font-weight:600">${esc(r.nama)}</td>
            <td><span class="badge b-gray" style="font-size:11.5px">${esc(r.jabatan)}</span></td>
            <td style="font-size:12px">${esc(r.nama_unit) || '-'}</td>
            <td style="text-align:center;font-weight:700">${r.hari_rutin ?? 0}</td>
            <td style="text-align:center"><span class="badge b-blue">${r.hari_dinilai ?? 0}</span></td>
            <td style="text-align:center">${lateBadge}</td>
            <td style="text-align:center">${harianBadge}</td>
            <td style="text-align:center">${nonrutinBadge}</td>
            <td style="text-align:center">${akhirBadge}</td>
        </tr>`;
    }).join('');

    updateDOM('rekapBody', html);
}

function filterDataRekap() {
    const q = document.getElementById('cariRekap').value.toLowerCase().trim();
    const filtered = dataRekapList.filter(r => {
        return !q || (r.nip && r.nip.toLowerCase().includes(q)) || 
                     (r.nama && r.nama.toLowerCase().includes(q)) || 
                     (r.jabatan && r.jabatan.toLowerCase().includes(q));
    });
    renderTabelRekap(filtered);
}

/* ── 7. LOGIC PENILAIAN KINERJA (HARIAN & NON-RUTIN) ── */
async function loadDataPenilaianHarian() {
    const r = await apiFetch('karyawan', 'list_penilaian_harian', {}, 'GET');
    if (!r.ok) return;
    dataPenilaianHarianList = r.data || [];
    renderTabelPenilaianHarian(dataPenilaianHarianList);
}

function renderTabelPenilaianHarian(list) {
    const tbody = document.getElementById('penilaianHarianBody');
    if (!tbody) return;
    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="empty-row">Belum ada data penilaian harian tugas rutin.</td></tr>';
        return;
    }

    const html = list.map(p => {
        const info = getPredikatInfo(p.nilai);
        return `<tr>
            <td style="font-weight:600;font-size:12px;color:var(--primary)">${esc(p.tanggal)}</td>
            <td>
                <div style="font-weight:600">${esc(p.karyawan_nama)}</div>
                <div style="font-size:11px;color:var(--muted)">${esc(p.karyawan_nip)}</div>
            </td>
            <td><span class="badge b-gray" style="font-size:11.5px">${esc(p.jabatan)}</span></td>
            <td style="text-align:center"><span class="badge b-teal" style="font-size:11px">${p.tugas_rutin_selesai ?? 0} / ${p.total_tugas_rutin ?? 0} Selesai</span></td>
            <td style="text-align:center;font-weight:700;font-size:15px">${p.nilai} / 5</td>
            <td style="text-align:center"><span class="badge ${info.badge}">${info.text}</span></td>
            <td style="font-size:12.5px">${esc(p.penilai_nama)}</td>
            <td><div style="max-width:240px;font-size:12.5px;color:var(--text)">${esc(p.catatan || '-')}</div></td>
            <td style="text-align:center"><span class="badge b-blue" style="font-size:11px"><i data-lucide="lock" style="width:10px;height:10px;vertical-align:-1px"></i> Terkunci</span></td>
        </tr>`;
    }).join('');

    tbody.innerHTML = html;
    if (window.lucide) lucide.createIcons({ root: tbody });
}

async function loadDataPenilaian() {
    const r = await apiFetch('karyawan', 'list_penilaian', {}, 'GET');
    if (!r.ok) return;
    dataPenilaianList = r.data || [];
    renderTabelPenilaian(dataPenilaianList);
}

function renderTabelPenilaian(list) {
    if (!document.getElementById('penilaianBody')) return;
    if (!list.length) {
        document.getElementById('penilaianBody').innerHTML = '<tr><td colspan="8" class="empty-row">Belum ada data evaluasi tugas non-rutin.</td></tr>';
        return;
    }

    const html = list.map(p => {
        const info = getPredikatInfo(p.nilai);
        const gradeBadge = `<span class="badge ${info.badge}">${info.text}</span>`;

        let fotoHtml = '';
        if (p.tugas_foto) {
            fotoHtml = `<div style="margin-top:4px">
                <button type="button" class="btn btn-outline btn-sm" style="padding:2px 7px;font-size:11px;border-color:var(--primary);color:var(--primary)" onclick="bukaModalFoto('${esc(p.tugas_foto)}', '${esc(p.tugas_deskripsi)}', 'Bukti Tugas - ${esc(p.karyawan_nama)}')">
                    <i data-lucide="image" style="width:12px;height:12px;vertical-align:-1px"></i> Bukti Foto
                </button>
            </div>`;
        }

        return `<tr>
            <td style="font-weight:600;color:var(--primary)">${esc(p.periode)}</td>
            <td>
                <div style="font-weight:600">${esc(p.karyawan_nama)}</div>
                <div style="font-size:11px;color:var(--muted)">${esc(p.karyawan_nip)} — ${esc(p.jabatan)}</div>
            </td>
            <td>
                <div style="max-width:250px;font-size:12.5px;color:var(--text)">${esc(p.tugas_deskripsi || 'Tugas Khusus')}</div>
                ${fotoHtml}
            </td>
            <td style="text-align:center;font-weight:700;font-size:15px">${p.nilai} / 5</td>
            <td style="text-align:center">${gradeBadge}</td>
            <td style="font-size:12.5px">${esc(p.penilai_nama)}</td>
            <td><div style="max-width:240px;font-size:12.5px;color:var(--text)">${esc(p.catatan) || '-'}</div></td>
            <td style="font-size:12px;color:var(--muted)">${esc(p.created_at ? p.created_at.substring(0, 10) : '-')}</td>
        </tr>`;
    }).join('');

    updateDOM('penilaianBody', html);
}

function filterDataPenilaian() {
    const q = document.getElementById('cariPenilaian').value.toLowerCase().trim();
    if (currentSubPenilaian === 'harian') {
        const filtered = dataPenilaianHarianList.filter(p => {
            return !q || (p.karyawan_nama && p.karyawan_nama.toLowerCase().includes(q)) || 
                         (p.tanggal && p.tanggal.toLowerCase().includes(q)) || 
                         (p.catatan && p.catatan.toLowerCase().includes(q));
        });
        renderTabelPenilaianHarian(filtered);
    } else {
        const filtered = dataPenilaianList.filter(p => {
            return !q || (p.karyawan_nama && p.karyawan_nama.toLowerCase().includes(q)) || 
                         (p.tugas_deskripsi && p.tugas_deskripsi.toLowerCase().includes(q)) || 
                         (p.periode && p.periode.toLowerCase().includes(q));
        });
        renderTabelPenilaian(filtered);
    }
}

function updateGradePreview() {
    const raw = document.getElementById('pnNilai').value.trim();
    const badge = document.getElementById('pnGradeBadge');
    if (!badge) return;
    if (!raw) {
        badge.style.display = 'none';
        return;
    }
    const val = Number(raw);
    if (Number.isInteger(val) && val >= 1 && val <= 5) {
        const info = getPredikatInfo(val);
        badge.style.display = 'inline-block';
        badge.className = 'badge ' + info.badge;
        badge.textContent = 'Predikat: ' + info.text;
    } else {
        badge.style.display = 'inline-block';
        badge.className = 'badge b-red';
        badge.textContent = 'Skor harus bilangan bulat (1 - 5)';
    }
}

function bukaModalNilaiTugas(kegiatanId, karyawanId, nama, jabatan, deskripsi, foto = '') {
    document.getElementById('pnKegiatanId').value = kegiatanId;
    document.getElementById('pnKaryawanId').value = karyawanId;
    document.getElementById('pnInfoTugasWrapper').style.display = 'block';
    document.getElementById('pnInfoTugasNama').textContent = `${nama} (${jabatan})`;
    document.getElementById('pnInfoTugasDeskripsi').textContent = deskripsi;

    const fotoWrapper = document.getElementById('pnInfoTugasFotoWrapper');
    if (fotoWrapper) {
        if (foto) {
            fotoWrapper.style.display = 'block';
            document.getElementById('btnPnLihatFoto').onclick = () => bukaModalFoto(foto, deskripsi, `Bukti Tugas - ${nama}`);
            if (window.lucide) lucide.createIcons({ root: fotoWrapper });
        } else {
            fotoWrapper.style.display = 'none';
        }
    }

    document.getElementById('pnKaryawanSelectWrapper').style.display = 'none';
    document.getElementById('pnNilai').value = '';
    document.getElementById('pnCatatan').value = '';
    updateGradePreview();
    openModal('modalPenilaian');
}

function bukaModalPenilaian() {
    document.getElementById('pnKegiatanId').value = '';
    document.getElementById('pnKaryawanId').value = '';
    document.getElementById('pnInfoTugasWrapper').style.display = 'none';
    document.getElementById('pnKaryawanSelectWrapper').style.display = 'block';
    document.getElementById('pnPilihTugasSelesai').value = '';
    const fotoWrapper = document.getElementById('pnPilihFotoWrapper');
    if (fotoWrapper) fotoWrapper.style.display = 'none';
    document.getElementById('pnNilai').value = '';
    document.getElementById('pnCatatan').value = '';
    updateGradePreview();
    openModal('modalPenilaian');
}

async function simpanPenilaian() {
    const kegiatan_id = document.getElementById('pnKegiatanId').value;
    const karyawan_id = document.getElementById('pnKaryawanId').value;
    const periode = document.getElementById('pnPeriode').value.trim();
    const rawNilai = document.getElementById('pnNilai').value.trim();
    const catatan = document.getElementById('pnCatatan').value.trim();

    if (!karyawan_id) {
        return showModalError('modalPenilaian', 'Silakan pilih tugas selesai yang ingin dinilai.');
    }
    if (!periode) {
        return showModalError('modalPenilaian', 'Periode penilaian wajib diisi.');
    }
    const nilaiNum = Number(rawNilai);
    if (!rawNilai || isNaN(nilaiNum) || !Number.isInteger(nilaiNum) || nilaiNum < 1 || nilaiNum > 5) {
        return showModalError('modalPenilaian', 'Skor nilai (1-5) wajib diisi dengan bilangan bulat.');
    }
    const nilai = parseInt(rawNilai, 10);

    const payload = { kegiatan_id, karyawan_id, periode, nilai, catatan };
    const r = await apiFetch('karyawan', 'tambah_penilaian', payload);
    if (r.ok) {
        toast('Penilaian tugas non-rutin berhasil disimpan.', 'success');
        closeModal('modalPenilaian');
        loadDataKegiatan();
        loadDataPenilaian();
        loadRekapKaryawan();
        loadDropdowns();
        if (!IS_PELAKSANA_ONLY) loadMonitoringHarian();
    } else {
        showModalError('modalPenilaian', r.msg);
    }
}

function bukaModalLihatEvaluasi(nama, jabatan, deskripsi, score, penilai, periode, catatan, foto = '') {
    document.getElementById('evNamaPersonil').textContent = `${nama} (${jabatan})`;
    document.getElementById('evDeskripsiTugas').textContent = deskripsi || '-';
    document.getElementById('evScore').textContent = `${score} / 5`;
    document.getElementById('evPenilaiNama').textContent = penilai || 'Atasan / Penilai';
    document.getElementById('evPeriode').textContent = periode || '-';
    document.getElementById('evCatatan').textContent = catatan || 'Tidak ada catatan tambahan.';

    const evFotoWrap = document.getElementById('evFotoWrapper');
    if (evFotoWrap) {
        if (foto) {
            evFotoWrap.style.display = 'block';
            document.getElementById('btnEvLihatFoto').onclick = () => bukaModalFoto(foto, deskripsi, `Bukti Tugas - ${nama}`);
            if (window.lucide) lucide.createIcons({ root: evFotoWrap });
        } else {
            evFotoWrap.style.display = 'none';
        }
    }

    const info = getPredikatInfo(score);
    document.getElementById('evGradeBadge').innerHTML = `<span class="badge ${info.badge}">${info.text}</span>`;

    openModal('modalLihatEvaluasi');
}

// Drag & drop upload non-rutin
const updDropzone = document.getElementById('updFotoDropzone');
if (updDropzone) {
    ['dragenter', 'dragover'].forEach(ev => {
        updDropzone.addEventListener(ev, (e) => {
            e.preventDefault(); e.stopPropagation();
            updDropzone.style.borderColor = 'var(--primary)';
            updDropzone.style.background = 'var(--primary-tint)';
        });
    });
    ['dragleave', 'drop'].forEach(ev => {
        updDropzone.addEventListener(ev, (e) => {
            e.preventDefault(); e.stopPropagation();
            updDropzone.style.borderColor = 'var(--border)';
            updDropzone.style.background = '#faf9f5';
        });
    });
    updDropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer && e.dataTransfer.files;
        if (files && files.length > 0) {
            const input = document.getElementById('updKgFoto');
            input.files = files;
            previewUpdateFoto(input);
        }
    });
}

// Drag & drop upload tugas rutin
const rtDropzone = document.getElementById('rtFotoDropzone');
if (rtDropzone) {
    ['dragenter', 'dragover'].forEach(ev => {
        rtDropzone.addEventListener(ev, (e) => {
            e.preventDefault(); e.stopPropagation();
            rtDropzone.style.borderColor = 'var(--primary)';
            rtDropzone.style.background = 'var(--primary-tint)';
        });
    });
    ['dragleave', 'drop'].forEach(ev => {
        rtDropzone.addEventListener(ev, (e) => {
            e.preventDefault(); e.stopPropagation();
            rtDropzone.style.borderColor = 'var(--border)';
            rtDropzone.style.background = '#faf9f5';
        });
    });
    rtDropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer && e.dataTransfer.files;
        if (files && files.length > 0) {
            const input = document.getElementById('rtFotoInput');
            input.files = files;
            handleRutinFotosSelect(input);
        }
    });
}

// Inisialisasi data halaman
loadDropdowns();
if (!IS_PELAKSANA_ONLY) {
    loadDataKaryawan();
}
if (!IS_HRD) {
    loadDataKegiatan();
    loadDataPenilaianHarian();
    loadDataPenilaian();
}

<?php if (!$isPelaksanaOnly && ($canKelola || $canLihat || $canMonitoring)): ?>
renderExportToolbar('#toolbarKaryawan', 'tblKaryawan', 'data_karyawan', 'Laporan Data Karyawan');
renderExportToolbar('#toolbarRekap', 'tblRekapKaryawan', '<?= $isHRD ? 'rekap_administratif_karyawan' : 'rekap_kinerja_karyawan' ?>', '<?= $isHRD ? 'Rekapitulasi Administratif Karyawan' : 'Rekapitulasi Kinerja Karyawan' ?>');
<?php endif; ?>
</script>
