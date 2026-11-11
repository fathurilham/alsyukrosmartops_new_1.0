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
    'log_aktivitas'  => 'log_aktivitas',
];

if ($page !== 'dashboard' && $page !== 'akun') {
    $modul = $pageModuleMap[$page] ?? null;
    if (!$modul || !hasAccess($user['role'], $modul, 'lihat') || ($modul === 'karyawan' && !canAccessKaryawanModule($user))) {
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
<script src="js/export.js?v=2"></script>
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
                case 'akun': include 'pages/akun.php'; break;
                case 'log_aktivitas': include 'pages/log_aktivitas.php'; break;
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

<!-- Floating Notification Banner Container -->
<div id="notifBannerContainer" role="status" aria-live="polite"></div>

<!-- Modal Pengaturan Notifikasi -->
<div class="modal-overlay" id="modalNotifPref">
    <div class="modal" style="max-width:440px">
        <div class="modal-head">
            <h3><i data-lucide="bell" style="width:18px;height:18px;vertical-align:middle;margin-right:6px"></i>Pengaturan Notifikasi</h3>
            <button class="modal-close" onclick="closeModal('modalNotifPref')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <div class="notif-pref-row">
                <div class="notif-pref-info">
                    <h5>Bunyi Notifikasi</h5>
                    <p>Dentingan lembut saat notifikasi baru muncul</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="prefSound" checked>
                </div>
            </div>
            <div class="notif-pref-row">
                <div class="notif-pref-info">
                    <h5>Persetujuan (Approval)</h5>
                    <p>Pengajuan izin, pinjam, event & maintenance</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="prefPersetujuan" checked>
                </div>
            </div>
            <div class="notif-pref-row">
                <div class="notif-pref-info">
                    <h5>Tugas & Pekerjaan</h5>
                    <p>Penugasan non-rutin, pekerjaan teknisi</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="prefTugas" checked>
                </div>
            </div>
            <div class="notif-pref-row">
                <div class="notif-pref-info">
                    <h5>Pengingat (Reminder)</h5>
                    <p>Batas waktu pengembalian, evaluasi harian</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="prefPengingat" checked>
                </div>
            </div>
            <div class="notif-pref-row">
                <div class="notif-pref-info">
                    <h5>Informasi Umum</h5>
                    <p>Pemberitahuan umum dan status penyelesaian</p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="prefInfo" checked>
                </div>
            </div>
            <div style="margin-top:12px;font-size:11.5px;color:var(--muted);background:rgba(0,0,0,0.03);padding:10px 12px;border-radius:6px;border:1px solid var(--border)">
                <i data-lucide="info" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;color:var(--primary)"></i>
                Notifikasi berprioritas <b>DARURAT</b> tidak dapat dibisukan dan akan selalu dimunculkan demi keselamatan operasional.
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn btn-outline" onclick="closeModal('modalNotifPref')">Tutup</button>
            <button type="button" class="btn btn-primary" onclick="simpanPrefNotif()">Simpan Pengaturan</button>
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

/* =========================================================================
   NOTIFIKASI PERANGKAT & PANEL (TAHAP 1)
   ========================================================================= */

let audioCtx = null;
function initAudio() {
    try {
        if (!audioCtx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) audioCtx = new AudioContext();
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    } catch (e) {}
}
document.addEventListener('click', initAudio, { once: false, passive: true });
document.addEventListener('keydown', initAudio, { once: false, passive: true });

let userNotifPref = {
    sound_enabled: true,
    kategori_persetujuan: true,
    kategori_tugas: true,
    kategori_pengingat: true,
    kategori_info: true
};

function playChime() {
    if (!userNotifPref.sound_enabled) return;
    try {
        initAudio();
        if (!audioCtx || audioCtx.state !== 'running') return;
        const now = audioCtx.currentTime;
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();

        // Single soft sine chime: ~0.4s, low volume
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, now); // D5
        osc.frequency.exponentialRampToValueAtTime(880, now + 0.08); // A5

        gain.gain.setValueAtTime(0.06, now);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.38);

        osc.connect(gain);
        gain.connect(audioCtx.destination);

        osc.start(now);
        osc.stop(now + 0.4);
    } catch (e) {
        // Fail silently if audio blocked
    }
}

function updateBadge(count) {
    const dot = document.getElementById('notifDot');
    if (!dot) return;
    if (count > 0) {
        dot.textContent = count > 9 ? '9+' : count.toString();
        dot.style.display = 'inline-flex';
    } else {
        dot.textContent = '';
        dot.style.display = 'none';
    }
}

function resolveNotifUrl(n) {
    if (n.link_page) {
        let url = `dashboard.php?page=${encodeURIComponent(n.link_page)}`;
        if (n.link_tab) {
            url += `&tab=${encodeURIComponent(n.link_tab)}`;
        }
        return url;
    }
    const pageMap = {
        'inventaris': 'inventory',
        'karyawan': 'karyawan',
        'event': 'event',
        'maintenance': 'maintenance',
        'arsip': 'arsip',
        'laporan': 'laporan',
        'pengajuan_unit': 'dashboard'
    };
    let targetPage = pageMap[n.modul_asal] || n.modul_asal;
    if (n.modul_asal === 'inventaris') {
        const p = (n.pesan || '').toLowerCase();
        if (p.includes('pinjam')) targetPage += '&tab=pinjam';
        else if (p.includes('kembali')) targetPage += '&tab=pengembalian';
    }
    return `dashboard.php?page=${targetPage}`;
}

function spawnBanner({ title, body, isDarurat, url }) {
    const container = document.getElementById('notifBannerContainer');
    if (!container) return;

    const banner = document.createElement('div');
    banner.className = 'notif-banner' + (isDarurat ? ' notif-banner-darurat' : '');
    banner.setAttribute('role', 'alert');

    const badgeText = isDarurat ? `[DARURAT] ${title}` : title;
    const badgeClass = isDarurat ? 'notif-banner-badge badge-darurat' : 'notif-banner-badge';

    banner.innerHTML = `
        <div class="notif-banner-header">
            <span class="${badgeClass}">${esc(badgeText)}</span>
            <span class="notif-banner-time">Baru saja</span>
        </div>
        <div class="notif-banner-body">${esc(body)}</div>
    `;

    const duration = isDarurat ? 10000 : 5000;
    let remainingTime = duration;
    let startTime = Date.now();
    let timerId = null;
    let isDismissed = false;

    function dismiss() {
        if (isDismissed) return;
        isDismissed = true;
        banner.classList.add('is-leaving');
        setTimeout(() => {
            if (banner.parentElement) banner.parentElement.removeChild(banner);
        }, 300);
    }

    function startTimer() {
        startTime = Date.now();
        timerId = setTimeout(dismiss, remainingTime);
    }

    function pauseTimer() {
        clearTimeout(timerId);
        remainingTime -= (Date.now() - startTime);
        if (remainingTime < 500) remainingTime = 500;
    }

    banner.addEventListener('mouseenter', pauseTimer);
    banner.addEventListener('mouseleave', startTimer);

    banner.addEventListener('click', () => {
        dismiss();
        if (url) {
            window.location.href = url;
        } else {
            const panel = document.getElementById('notifPanel');
            if (panel) {
                panel.style.display = 'block';
                loadNotif();
            }
        }
    });

    container.appendChild(banner);
    startTimer();
}

let pendingBackgroundBanners = [];
const defaultDocTitle = document.title || 'AL-SYUKROSMART OPS';

function displayBanners(items) {
    if (!items || items.length === 0) return;
    playChime();

    // Anti-spam: if multiple notifications in 1 poll cycle, show 1 summary banner
    if (items.length > 1) {
        const hasDarurat = items.some(it => it.prioritas === 'darurat');
        spawnBanner({
            title: 'PEMBERITAHUAN',
            body: `Anda memiliki ${items.length} notifikasi baru.`,
            isDarurat: hasDarurat,
            url: null
        });
    } else {
        const it = items[0];
        spawnBanner({
            title: it.modul_asal ? it.modul_asal.toUpperCase() : 'NOTIFIKASI',
            body: it.pesan,
            isDarurat: it.prioritas === 'darurat',
            url: resolveNotifUrl(it)
        });
    }
}

function processNewNotifications(newItems) {
    if (!newItems || newItems.length === 0) return;

    // Filter by user preference (darurat cannot be muted)
    const visibleItems = newItems.filter(item => {
        if (item.prioritas === 'darurat') return true;
        const cat = item.kategori || 'info';
        if (cat === 'persetujuan' && !userNotifPref.kategori_persetujuan) return false;
        if (cat === 'tugas' && !userNotifPref.kategori_tugas) return false;
        if (cat === 'pengingat' && !userNotifPref.kategori_pengingat) return false;
        if (cat === 'info' && !userNotifPref.kategori_info) return false;
        return true;
    });

    if (visibleItems.length === 0) return;

    if (document.hidden) {
        playChime();
        pendingBackgroundBanners.push(...visibleItems);
        return;
    }

    displayBanners(visibleItems);
}

let pollTimerId = null;
let isPolling = false;
let isFirstLoad = true;
let lastSeenNotifId = parseInt(localStorage.getItem('alsyukro_last_notif_id') || '0', 10);

async function pollNotifications() {
    if (isPolling) return;
    isPolling = true;

    try {
        const res = await apiFetch('notifikasi', 'poll', { since_id: lastSeenNotifId }, 'GET');
        if (!res || !res.ok) {
            if (res && res.msg && res.msg.toLowerCase().includes('sesi')) {
                stopPolling();
                return;
            }
            return;
        }

        if (res.pref) {
            userNotifPref = res.pref;
        }

        updateBadge(res.unread || 0);

        if (isFirstLoad) {
            isFirstLoad = false;
            // First load: seed last seen ID without showing stale banners
            if (res.max_id > lastSeenNotifId) {
                lastSeenNotifId = res.max_id;
                try {
                    localStorage.setItem('alsyukro_last_notif_id', lastSeenNotifId.toString());
                } catch(e) {}
            }
            return;
        }

        if (res.new_items && res.new_items.length > 0) {
            if (res.max_id > lastSeenNotifId) {
                lastSeenNotifId = res.max_id;
                try {
                    localStorage.setItem('alsyukro_last_notif_id', lastSeenNotifId.toString());
                } catch(e) {}
            }

            if (document.hidden && (res.unread || 0) > 0) {
                document.title = `(${res.unread}) ${defaultDocTitle}`;
            }

            processNewNotifications(res.new_items);
        }
    } catch (e) {
        console.error('Poll error:', e);
    } finally {
        isPolling = false;
        scheduleNextPoll();
    }
}

function scheduleNextPoll() {
    clearTimeout(pollTimerId);
    const interval = document.hidden ? 30000 : 10000;
    pollTimerId = setTimeout(pollNotifications, interval);
}

function stopPolling() {
    clearTimeout(pollTimerId);
}

// Multi-tab sync via localStorage
window.addEventListener('storage', (e) => {
    if (e.key === 'alsyukro_last_notif_id') {
        const val = parseInt(e.newValue || '0', 10);
        if (val > lastSeenNotifId) {
            lastSeenNotifId = val;
        }
    }
});

// Background tab handling
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        document.title = defaultDocTitle;
        if (pendingBackgroundBanners.length > 0) {
            const items = [...pendingBackgroundBanners];
            pendingBackgroundBanners = [];
            displayBanners(items);
        }
        scheduleNextPoll();
    } else {
        scheduleNextPoll();
    }
});

// Bell Panel Management
async function loadNotif() {
    const r = await apiFetch('notifikasi', 'list', {}, 'GET');
    const panel = document.getElementById('notifPanel');
    if (!panel) return;

    if (r.ok) {
        updateBadge(r.unread || 0);

        let listHtml = '';
        if (r.data && r.data.length > 0) {
            listHtml = r.data.map(n => {
                const targetUrl = resolveNotifUrl(n);
                const isUnread = n.status_baca === 'belum';
                const unreadClass = isUnread ? ' notif-unread' : '';
                const prioritasBadge = n.prioritas === 'darurat' ? '<span style="color:#ef4444;font-weight:700;margin-left:4px">[DARURAT]</span>' : '';
                return `
                <div class="notif-item${unreadClass}" style="cursor:pointer;" onclick="window.location.href='${targetUrl}'">
                    <b>${esc(n.modul_asal).toUpperCase()} ${prioritasBadge}</b>
                    <div style="font-size:12.5px;color:var(--text);margin:2px 0 4px 0">${esc(n.pesan)}</div>
                    <div><span>${esc(n.waktu)}</span></div>
                </div>`;
            }).join('');
        } else {
            listHtml = '<div class="notif-item" style="text-align:center;color:#9ca3af;padding:24px 16px">Tidak ada notifikasi</div>';
        }

        panel.innerHTML = `
            <div class="notif-panel-head">
                <h4>Notifikasi</h4>
                <div class="notif-panel-actions">
                    <button type="button" class="notif-panel-btn" id="btnTandaiSemuaDibaca" title="Tandai semua dibaca"><i data-lucide="check-check"></i></button>
                    <button type="button" class="notif-panel-btn" id="btnBukaPref" title="Pengaturan Notifikasi"><i data-lucide="settings"></i></button>
                </div>
            </div>
            <div class="notif-list-container">
                ${listHtml}
            </div>
        `;
        lucide.createIcons();

        document.getElementById('btnTandaiSemuaDibaca')?.addEventListener('click', async (e) => {
            e.stopPropagation();
            await apiFetch('notifikasi', 'mark_read', {}, 'POST');
            updateBadge(0);
            await loadNotif();
        });

        document.getElementById('btnBukaPref')?.addEventListener('click', (e) => {
            e.stopPropagation();
            bukaPengaturanNotif();
        });
    }
}

document.getElementById('notifBtn')?.addEventListener('click', async (e) => {
    e.stopPropagation();
    const panel = document.getElementById('notifPanel');
    if (!panel) return;
    const show = panel.style.display === 'none';
    panel.style.display = show ? 'block' : 'none';
    if (show) {
        await loadNotif();
    }
});

document.addEventListener('click', (e) => {
    const panel = document.getElementById('notifPanel');
    const btn = document.getElementById('notifBtn');
    if (panel && panel.style.display !== 'none' && !panel.contains(e.target) && !btn.contains(e.target)) {
        panel.style.display = 'none';
    }
});

function bukaPengaturanNotif() {
    document.getElementById('prefSound').checked = !!userNotifPref.sound_enabled;
    document.getElementById('prefPersetujuan').checked = !!userNotifPref.kategori_persetujuan;
    document.getElementById('prefTugas').checked = !!userNotifPref.kategori_tugas;
    document.getElementById('prefPengingat').checked = !!userNotifPref.kategori_pengingat;
    document.getElementById('prefInfo').checked = !!userNotifPref.kategori_info;
    openModal('modalNotifPref');
}

async function simpanPrefNotif() {
    const payload = {
        sound_enabled: document.getElementById('prefSound').checked ? 1 : 0,
        kategori_persetujuan: document.getElementById('prefPersetujuan').checked ? 1 : 0,
        kategori_tugas: document.getElementById('prefTugas').checked ? 1 : 0,
        kategori_pengingat: document.getElementById('prefPengingat').checked ? 1 : 0,
        kategori_info: document.getElementById('prefInfo').checked ? 1 : 0
    };
    const res = await apiFetch('notifikasi', 'save_pref', payload, 'POST');
    if (res.ok) {
        userNotifPref = {
            sound_enabled: !!payload.sound_enabled,
            kategori_persetujuan: !!payload.kategori_persetujuan,
            kategori_tugas: !!payload.kategori_tugas,
            kategori_pengingat: !!payload.kategori_pengingat,
            kategori_info: !!payload.kategori_info
        };
        closeModal('modalNotifPref');
        toast('Pengaturan notifikasi berhasil disimpan', 'success');
    } else {
        toast(res.msg || 'Gagal menyimpan pengaturan', 'error');
    }
}

// Start polling
pollNotifications();

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
