<?php
require_once 'config/auth.php';
requireLogin();
require_once 'config/sidebar.php';

$user = getCurrentUser();
$role = $user['role'];
$page = $_GET['page'] ?? 'dashboard';

// Peta page -> modul (untuk validasi hak akses "lihat" sebelum memuat halaman)
$pageModuleMap = [
    'inventory'      => 'inventaris',
    'karyawan'       => 'karyawan',
    'event'          => 'event',
    'maintenance'    => 'maintenance',
    'arsip'          => 'arsip',
    'laporan'        => 'laporan',
    'pengajuan_unit' => 'pengajuan_unit',
];

if ($page !== 'dashboard' && $page !== 'akun') {
    $modul = $pageModuleMap[$page] ?? null;
    if (!$modul || !hasAccess($user['role'], $modul, 'lihat')) {
        http_response_code(403);
        $page = 'forbidden';
    }
}
if ($page === 'akun' && $user['role'] !== 'admin') {
    http_response_code(403);
    $page = 'forbidden';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<meta name="app-title" content="AL-SYUKROSMART OPS">
<title>Dashboard — AL-SYUKROSMART OPS</title>
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="512x512" href="logo-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<!-- Bootstrap 5 (framework CSS utama aplikasi) -->
<link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
<script src="assets/vendor/lucide.min.js"></script>
<link rel="stylesheet" href="css/app.css?v=<?= time() ?>">
<script src="js/main.js?v=<?= time() ?>"></script>
<script src="js/export.js"></script>
<!-- Bootstrap 5 JS bundle (Popper termasuk) -->
<script src="assets/vendor/bootstrap.bundle.min.js" defer></script>
<!-- SweetAlert2 untuk pop-up notifikasi -->
<script src="assets/vendor/sweetalert2.min.js" defer></script>
</head>
<body>
<div class="app-shell">
    <?php renderSidebar($page === 'forbidden' ? '' : $page); ?>
    <main class="app-main">
        <header class="topbar">
            <div class="topbar-title">
                <button class="icon-btn hamburger-btn" id="hamburgerBtn" aria-label="Buka menu"><i data-lucide="menu"></i></button>
                <div>
                    <h1><?= $page === 'dashboard' ? 'Dashboard' : ($page === 'akun' ? 'Kelola Akun' : htmlspecialchars(getModuleLabel($pageModuleMap[$page] ?? $page))) ?></h1>
                    <p>Selamat datang, <b><?= htmlspecialchars($user['name']) ?></b> — <?= htmlspecialchars($user['nama_role']) ?><?= $user['nama_unit'] ? ' · ' . htmlspecialchars($user['nama_unit']) : '' ?></p>
                </div>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn" id="notifBtn"><i data-lucide="bell"></i><span class="notif-dot" id="notifDot" style="display:none"></span></button>
                <div class="badge-role" style="background:<?= getRoleColor($user['role']) ?>22;color:<?= getRoleColor($user['role']) ?>"><?= htmlspecialchars($user['nama_role']) ?></div>
            </div>
        </header>
        <div id="notifPanel" class="notif-panel" style="display:none"></div>
        <section class="app-content">
            <?php
            switch ($page) {
                case 'dashboard': include 'pages/dashboard_home.php'; break;
                case 'inventory': include 'pages/inventory.php'; break;
                case 'karyawan': include 'pages/karyawan.php'; break;
                case 'event': include 'pages/event.php'; break;
                case 'maintenance': include 'pages/maintenance.php'; break;
                case 'arsip': include 'pages/arsip.php'; break;
                case 'laporan': include 'pages/laporan.php'; break;
                case 'pengajuan_unit': include 'pages/pengajuan_unit.php'; break;
                case 'akun': include 'pages/akun.php'; break;
                default: echo '<div class="empty-state"><i data-lucide="shield-alert"></i><h3>Akses Ditolak</h3><p>Anda tidak memiliki hak akses ke halaman ini.</p></div>';
            }
            ?>
        </section>
    </main>
</div>
<!-- Modal Ganti Password -->
<div class="modal-overlay" id="modalGantiPassword">
    <div class="modal">
        <div class="modal-head">
            <h3>Ganti Password</h3>
            <button class="modal-close" onclick="closeModal('modalGantiPassword')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="fld">
                    <label>Password Lama *</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input type="password" id="gpOld" style="width:100%; padding-right:40px;">
                        <button type="button" onclick="const p=document.getElementById('gpOld'); p.type=p.type==='password'?'text':'password'; this.innerHTML=p.type==='password'?'<i data-lucide=\'eye\'></i>':'<i data-lucide=\'eye-off\'></i>'; lucide.createIcons();" style="position:absolute; right:10px; background:none; border:none; cursor:pointer; color:var(--muted); padding:0; display:flex; align-items:center;"><i data-lucide="eye"></i></button>
                    </div>
                </div>
                <div class="fld">
                    <label>Password Baru *</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input type="password" id="gpNew" style="width:100%; padding-right:40px;">
                        <button type="button" onclick="const p=document.getElementById('gpNew'); p.type=p.type==='password'?'text':'password'; this.innerHTML=p.type==='password'?'<i data-lucide=\'eye\'></i>':'<i data-lucide=\'eye-off\'></i>'; lucide.createIcons();" style="position:absolute; right:10px; background:none; border:none; cursor:pointer; color:var(--muted); padding:0; display:flex; align-items:center;"><i data-lucide="eye"></i></button>
                    </div>
                </div>
                <div class="fld">
                    <label>Konfirmasi Password Baru *</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input type="password" id="gpConfirm" style="width:100%; padding-right:40px;">
                        <button type="button" onclick="const p=document.getElementById('gpConfirm'); p.type=p.type==='password'?'text':'password'; this.innerHTML=p.type==='password'?'<i data-lucide=\'eye\'></i>':'<i data-lucide=\'eye-off\'></i>'; lucide.createIcons();" style="position:absolute; right:10px; background:none; border:none; cursor:pointer; color:var(--muted); padding:0; display:flex; align-items:center;"><i data-lucide="eye"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalGantiPassword')">Batal</button>
            <button class="btn btn-primary" onclick="simpanGantiPassword()">Simpan</button>
        </div>
    </div>
</div>

<script>
lucide.createIcons();

/* Sidebar mobile (hamburger + overlay) */
function openSidebar() { document.getElementById('sidebar').classList.add('open'); document.getElementById('sidebarOverlay').classList.add('open'); document.body.classList.add('no-scroll'); }
function closeSidebar() { document.getElementById('sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('open'); document.body.classList.remove('no-scroll'); }
document.getElementById('hamburgerBtn')?.addEventListener('click', openSidebar);
document.querySelectorAll('.sidebar .nav-item').forEach(a => a.addEventListener('click', closeSidebar));

/* Notifikasi & reminder lintas modul */
async function loadNotif() {
    const r = await apiFetch('notifikasi', 'list', {}, 'GET');
    const dot = document.getElementById('notifDot');
    if (r.ok) {
        dot.style.display = r.unread > 0 ? 'block' : 'none';
        const panel = document.getElementById('notifPanel');
        
        const pageMap = {
            'inventaris': 'inventory',
            'karyawan': 'karyawan',
            'event': 'event',
            'maintenance': 'maintenance',
            'arsip': 'arsip',
            'laporan': 'laporan',
            'pengajuan_unit': 'dashboard'
        };

        panel.innerHTML = r.data.length
            ? r.data.map(n => {
                let targetPage = pageMap[n.modul_asal] || n.modul_asal;
                if (n.modul_asal === 'inventaris') {
                    if (n.pesan.toLowerCase().includes('pinjam')) targetPage += '&tab=pinjam';
                    else if (n.pesan.toLowerCase().includes('kembali')) targetPage += '&tab=pengembalian';
                }
                return `<div class="notif-item" style="cursor:pointer;" onclick="window.location.href='dashboard.php?page=${targetPage}'">
                    <b>${esc(n.modul_asal).toUpperCase()}</b> ${esc(n.pesan)}<div><span>${n.waktu}</span></div>
                </div>`;
            }).join('')
            : '<div class="notif-item" style="text-align:center;color:#9ca3af">Tidak ada notifikasi</div>';
    }
}
document.getElementById('notifBtn').addEventListener('click', async () => {
    const panel = document.getElementById('notifPanel');
    const show = panel.style.display === 'none';
    panel.style.display = show ? 'block' : 'none';
    if (show) { await loadNotif(); await apiFetch('notifikasi', 'mark_read', {}, 'POST'); document.getElementById('notifDot').style.display = 'none'; }
});
loadNotif();
setInterval(loadNotif, 10000);

async function simpanGantiPassword() {
    const oldP = document.getElementById('gpOld').value;
    const newP = document.getElementById('gpNew').value;
    const cP = document.getElementById('gpConfirm').value;
    
    if(!oldP || !newP || !cP) return toast('Harap isi semua kolom.', 'error');
    if(newP !== cP) return toast('Password baru dan konfirmasi tidak cocok.', 'error');
    if(newP.length < 6) return toast('Password baru minimal 6 karakter.', 'error');
    
    const r = await apiFetch('profil', 'ganti_password', { old_password: oldP, new_password: newP });
    if(r.ok) {
        toast('Password berhasil diubah!', 'success');
        closeModal('modalGantiPassword');
        document.getElementById('gpOld').value = '';
        document.getElementById('gpNew').value = '';
        document.getElementById('gpConfirm').value = '';
    } else {
        showModalError('modalGantiPassword', r.msg);
    }
}
</script>
</body>
</html>
