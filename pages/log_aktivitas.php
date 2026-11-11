<?php
// Proteksi akses halaman log aktivitas (hanya admin, manajer_hrd, manajer_ga)
if ($role !== 'admin' && !hasAccess($role, 'log_aktivitas', 'lihat')) {
    http_response_code(403);
    echo '<div class="card"><div class="card-body p-4 text-center text-danger"><h4>Akses Ditolak</h4><p>Anda tidak memiliki hak akses ke halaman Log Aktivitas Sistem.</p></div></div>';
    return;
}

$isAdmin = ($role === 'admin');
$isHRD = in_array($role, ['manajer_hrd', 'staff_hrd'], true);
$isManajerGA = ($role === 'manajer_ga');

if ($isAdmin) {
    $whereModulScope = "1=1";
    $totalLog = $conn->query("SELECT COUNT(*) n FROM log_aktivitas WHERE $whereModulScope")->fetch_assoc()['n'] ?? 0;
    $todayLog = $conn->query("SELECT COUNT(*) n FROM log_aktivitas WHERE $whereModulScope AND DATE(waktu) = CURDATE()")->fetch_assoc()['n'] ?? 0;
    $userAktifLog = $conn->query("SELECT COUNT(DISTINCT COALESCE(user_id, nama_user)) n FROM log_aktivitas WHERE $whereModulScope AND waktu >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['n'] ?? 0;
    $usersFilter = $conn->query("SELECT id, name, username FROM users ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
} elseif ($isHRD) {
    // HRD: Daftar putih hanya modul 'karyawan' (data administratif)
    $whereModulScope = "modul = 'karyawan'";
    $totalLog = $conn->query("SELECT COUNT(*) n FROM log_aktivitas WHERE $whereModulScope")->fetch_assoc()['n'] ?? 0;
    $todayLog = $conn->query("SELECT COUNT(*) n FROM log_aktivitas WHERE $whereModulScope AND DATE(waktu) = CURDATE()")->fetch_assoc()['n'] ?? 0;
    $userAktifLog = $conn->query("SELECT COUNT(DISTINCT COALESCE(user_id, nama_user)) n FROM log_aktivitas WHERE $whereModulScope AND waktu >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['n'] ?? 0;
    $usersFilter = $conn->query("SELECT id, name, username FROM users ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
} elseif ($isManajerGA) {
    // Manajer GA: Hanya modul 'karyawan_kinerja' dan hanya user_id dirinya + bawahannya
    $myKar = getKaryawanByUserId($user['id']);
    $gaUserIds = [(int)$user['id']];
    if ($myKar) {
        $subKaryawanIds = getSubordinateKaryawanIds($myKar['id'], true);
        if (!empty($subKaryawanIds)) {
            $idsStr = implode(',', array_map('intval', $subKaryawanIds));
            $uRows = $conn->query("SELECT user_id FROM karyawan WHERE id IN ($idsStr) AND user_id IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
            foreach ($uRows as $ur) {
                $gaUserIds[] = (int)$ur['user_id'];
            }
        }
    }
    $gaUserIds = array_values(array_unique($gaUserIds));
    $ph = implode(',', array_fill(0, count($gaUserIds), '?'));
    $typesGA = str_repeat('i', count($gaUserIds));

    $stmtTotal = $conn->prepare("SELECT COUNT(*) n FROM log_aktivitas WHERE modul = 'karyawan_kinerja' AND user_id IN ($ph)");
    $stmtTotal->bind_param($typesGA, ...$gaUserIds);
    $stmtTotal->execute();
    $totalLog = $stmtTotal->get_result()->fetch_assoc()['n'] ?? 0;

    $stmtToday = $conn->prepare("SELECT COUNT(*) n FROM log_aktivitas WHERE modul = 'karyawan_kinerja' AND user_id IN ($ph) AND DATE(waktu) = CURDATE()");
    $stmtToday->bind_param($typesGA, ...$gaUserIds);
    $stmtToday->execute();
    $todayLog = $stmtToday->get_result()->fetch_assoc()['n'] ?? 0;

    $stmtActive = $conn->prepare("SELECT COUNT(DISTINCT COALESCE(user_id, nama_user)) n FROM log_aktivitas WHERE modul = 'karyawan_kinerja' AND user_id IN ($ph) AND waktu >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmtActive->bind_param($typesGA, ...$gaUserIds);
    $stmtActive->execute();
    $userAktifLog = $stmtActive->get_result()->fetch_assoc()['n'] ?? 0;

    $stmtU = $conn->prepare("SELECT id, name, username FROM users WHERE id IN ($ph) ORDER BY name ASC");
    $stmtU->bind_param($typesGA, ...$gaUserIds);
    $stmtU->execute();
    $usersFilter = $stmtU->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $totalLog = 0;
    $todayLog = 0;
    $userAktifLog = 0;
    $usersFilter = [];
}
?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#145c3f"><i data-lucide="activity"></i></div>
        <h3><?= number_format($totalLog, 0, ',', '.') ?></h3>
        <p><?= $isAdmin ? 'Total Log Aktivitas' : ($isHRD ? 'Total Log Karyawan (Administratif)' : 'Total Log Kinerja Karyawan') ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#2f6690"><i data-lucide="clock"></i></div>
        <h3><?= number_format($todayLog, 0, ',', '.') ?></h3>
        <p>Aktivitas Hari Ini</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#6a4fa0"><i data-lucide="users"></i></div>
        <h3><?= number_format($userAktifLog, 0, ',', '.') ?></h3>
        <p>Pengguna Aktif (7 Hari)</p>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h2>Log Aktivitas Sistem</h2>
            <p>
                Pencatatan jejak audit aktivitas pengguna untuk keamanan dan integritas data.
                <?php if ($isHRD): ?>
                    <span class="badge b-purple ms-1">Cakupan: Modul Karyawan (Administratif)</span>
                <?php elseif ($isManajerGA): ?>
                    <span class="badge b-blue ms-1">Cakupan: Modul Kinerja Karyawan (Bawahan GA)</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="hgroup">
            <div id="toolbarLog"></div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="p-3 border-bottom bg-light">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3 col-lg-3">
                <label class="form-label small text-muted mb-1"><i data-lucide="search" style="width:12px;height:12px"></i> Pencarian</label>
                <div class="search-bar" style="min-width:100%; background:#fff;">
                    <i data-lucide="search"></i>
                    <input type="text" id="filterSearch" placeholder="Cari aktivitas, nama, atau IP...">
                </div>
            </div>

            <div class="col-6 col-md-2 col-lg-2">
                <label class="form-label small text-muted mb-1"><i data-lucide="calendar" style="width:12px;height:12px"></i> Tanggal Mulai</label>
                <input type="date" id="filterTglMulai" class="form-control form-control-sm" style="font-size:12.5px; height:38px;">
            </div>

            <div class="col-6 col-md-2 col-lg-2">
                <label class="form-label small text-muted mb-1"><i data-lucide="calendar" style="width:12px;height:12px"></i> Tanggal Selesai</label>
                <input type="date" id="filterTglSelesai" class="form-control form-control-sm" style="font-size:12.5px; height:38px;">
            </div>

            <div class="col-6 col-md-2 col-lg-2">
                <label class="form-label small text-muted mb-1"><i data-lucide="user" style="width:12px;height:12px"></i> Pengguna</label>
                <select id="filterUser" class="form-select form-select-sm" style="font-size:12.5px; height:38px;">
                    <option value="">Semua Pengguna</option>
                    <?php foreach ($usersFilter as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($isAdmin): ?>
            <div class="col-6 col-md-2 col-lg-2">
                <label class="form-label small text-muted mb-1"><i data-lucide="layers" style="width:12px;height:12px"></i> Modul</label>
                <select id="filterModul" class="form-select form-select-sm" style="font-size:12.5px; height:38px;">
                    <option value="">Semua Modul</option>
                    <option value="karyawan">Karyawan (Administratif)</option>
                    <option value="karyawan_kinerja">Kinerja Karyawan</option>
                    <option value="inventaris">Inventaris</option>
                    <option value="event">Event</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="arsip">Arsip</option>
                    <option value="pengajuan_unit">Pengajuan Unit</option>
                    <option value="laporan">Laporan</option>
                    <option value="akun">Akun Pengguna</option>
                    <option value="auth">Autentikasi</option>
                    <option value="log_aktivitas">Log Aktivitas</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-12 col-md-1 col-lg-1 d-flex gap-1">
                <button type="button" class="btn btn-outline btn-sm w-100" style="height:38px;" onclick="resetLogFilter()" title="Reset Filter">
                    <i data-lucide="rotate-ccw"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Table Wrap -->
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblLogAktivitas">
            <thead>
                <tr>
                    <th style="width:160px">Waktu</th>
                    <th style="width:200px">Pengguna</th>
                    <th style="width:120px">Modul</th>
                    <th>Aktivitas</th>
                    <th style="width:130px">Alamat IP</th>
                </tr>
            </thead>
            <tbody id="logTableBody">
                <tr>
                    <td colspan="5" class="empty-row text-center py-4">Memuat data log aktivitas...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination & Row Limit Footer -->
    <div class="card-foot d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-top">
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">Tampilkan:</span>
            <select id="limitSelect" class="form-select form-select-sm" style="width:auto; font-size:12px;" onchange="changeLimit(this.value)">
                <option value="15">15</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="250">250</option>
            </select>
            <span id="logPageInfo" class="text-muted small ms-2">Memuat...</span>
        </div>
        <div class="d-flex align-items-center gap-1">
            <button id="btnPrevPage" class="btn btn-outline btn-sm" onclick="navPage(-1)" disabled>
                <i data-lucide="chevron-left"></i> Sebelumnya
            </button>
            <span id="pageIndicator" class="badge b-gray px-3 py-2" style="font-weight:600;">1 / 1</span>
            <button id="btnNextPage" class="btn btn-outline btn-sm" onclick="navPage(1)" disabled>
                Selanjutnya <i data-lucide="chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<script>
let currentPage = 1;
let currentLimit = 25;
let totalPages = 1;
let searchTimer = null;

const badgeModulMap = {
    'karyawan': 'b-purple',
    'karyawan_kinerja': 'b-blue',
    'inventaris': 'b-green',
    'event': 'b-blue',
    'maintenance': 'b-yellow',
    'arsip': 'b-teal',
    'akun': 'b-red',
    'auth': 'b-gray',
    'pengajuan_unit': 'b-blue',
    'laporan': 'b-green',
    'log_aktivitas': 'b-purple'
};

const labelModulMap = {
    'karyawan': 'Karyawan',
    'karyawan_kinerja': 'Kinerja Karyawan',
    'inventaris': 'Inventaris',
    'event': 'Event',
    'maintenance': 'Maintenance',
    'arsip': 'Arsip',
    'pengajuan_unit': 'Pengajuan Unit',
    'laporan': 'Laporan',
    'akun': 'Akun Pengguna',
    'auth': 'Autentikasi',
    'log_aktivitas': 'Log Aktivitas'
};

async function loadLogs(page = 1) {
    currentPage = page;
    const tbody = document.getElementById('logTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="empty-row text-center py-4">Memuat data log aktivitas...</td></tr>';

    const payload = {
        action: 'list',
        page: currentPage,
        limit: currentLimit,
        q: document.getElementById('filterSearch').value.trim(),
        tgl_mulai: document.getElementById('filterTglMulai').value,
        tgl_selesai: document.getElementById('filterTglSelesai').value,
        user_id: document.getElementById('filterUser').value
    };

    const filterModulEl = document.getElementById('filterModul');
    if (filterModulEl) {
        payload.modul = filterModulEl.value;
    }

    const res = await apiFetch('log', 'list', payload, 'GET');
    if (!res || !res.ok) {
        tbody.innerHTML = `<tr><td colspan="5" class="empty-row text-center text-danger py-4">${esc(res?.msg || 'Gagal memuat log aktivitas.')}</td></tr>`;
        return;
    }

    const items = res.data.items || [];
    const p = res.data.pagination || { page: 1, limit: 25, total_rows: 0, total_pages: 1 };
    totalPages = p.total_pages;

    // Update pagination controls
    const startRow = p.total_rows > 0 ? (p.page - 1) * p.limit + 1 : 0;
    const endRow = Math.min(p.page * p.limit, p.total_rows);
    document.getElementById('logPageInfo').textContent = `Menampilkan ${startRow}-${endRow} dari ${p.total_rows} log`;
    document.getElementById('pageIndicator').textContent = `${p.page} / ${p.total_pages}`;
    document.getElementById('btnPrevPage').disabled = (p.page <= 1);
    document.getElementById('btnNextPage').disabled = (p.page >= p.total_pages);

    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-row text-center py-4">Tidak ada log aktivitas yang sesuai dengan filter.</td></tr>';
        return;
    }

    let html = '';
    items.forEach(item => {
        const badgeClass = badgeModulMap[item.modul] || 'b-gray';
        const isDeleted = !item.user_id;
        const deletedBadge = isDeleted ? ' <span class="badge b-gray" style="font-size:10px; padding:2px 5px;">Dihapus</span>' : '';
        const ipDisplay = item.ip_address ? esc(item.ip_address) : '-';

        html += `
            <tr>
                <td style="font-size:12.5px; white-space:nowrap; color:var(--text); font-variant-numeric: tabular-nums;">
                    ${esc(item.waktu)}
                </td>
                <td>
                    <div style="font-weight:600; font-size:13px; line-height:1.3;">
                        ${esc(item.nama_user)}${deletedBadge}
                    </div>
                    <div class="text-muted" style="font-size:11.5px;">
                        ${esc(item.role_user)}
                    </div>
                </td>
                <td>
                    <span class="badge ${badgeClass}">
                        ${esc(labelModulMap[item.modul] || item.modul)}
                    </span>
                </td>
                <td style="font-size:12.5px; word-break:break-word;">
                    ${esc(item.aktivitas)}
                </td>
                <td style="font-size:12px;">
                    <code style="font-size:11.5px; color:#495057;">${ipDisplay}</code>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    if (window.lucide) lucide.createIcons();
}

function navPage(delta) {
    const target = currentPage + delta;
    if (target >= 1 && target <= totalPages) {
        loadLogs(target);
    }
}

function changeLimit(newLimit) {
    currentLimit = parseInt(newLimit, 10) || 25;
    loadLogs(1);
}

function resetLogFilter() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterTglMulai').value = '';
    document.getElementById('filterTglSelesai').value = '';
    document.getElementById('filterUser').value = '';
    const filterModulEl = document.getElementById('filterModul');
    if (filterModulEl) filterModulEl.value = '';
    loadLogs(1);
}

// Event Listeners
document.getElementById('filterSearch').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadLogs(1), 350);
});

document.getElementById('filterTglMulai').addEventListener('change', () => loadLogs(1));
document.getElementById('filterTglSelesai').addEventListener('change', () => loadLogs(1));
document.getElementById('filterUser').addEventListener('change', () => loadLogs(1));
const filterModulInput = document.getElementById('filterModul');
if (filterModulInput) {
    filterModulInput.addEventListener('change', () => loadLogs(1));
}

// Inisialisasi Export Toolbar dan muat data
renderExportToolbar('#toolbarLog', 'tblLogAktivitas', 'log_aktivitas_sistem', 'Log Aktivitas Sistem');
loadLogs(1);
</script>
