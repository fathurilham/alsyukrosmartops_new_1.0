<?php
$modules = getRoleModules($role);
$cards = [];

if (in_array('inventaris', $modules)) {
    $n = $conn->query("SELECT COUNT(*) n FROM peminjaman WHERE status IN ('diajukan','diverifikasi')")->fetch_assoc()['n'];
    $cards[] = ['icon' => 'package', 'color' => '#145c3f', 'label' => 'Pengajuan Peminjaman Menunggu', 'value' => $n, 'link' => 'inventory'];
}
if (in_array('maintenance', $modules)) {
    $n = $conn->query("SELECT COUNT(*) n FROM pengajuan_maintenance WHERE status NOT IN ('selesai','ditolak')")->fetch_assoc()['n'];
    $cards[] = ['icon' => 'wrench', 'color' => '#a97c22', 'label' => 'Maintenance Dalam Proses', 'value' => $n, 'link' => 'maintenance'];
}
if (in_array('event', $modules)) {
    $n = $conn->query("SELECT COUNT(*) n FROM event WHERE status IN ('diajukan','diverifikasi','disetujui','berlangsung')")->fetch_assoc()['n'];
    $cards[] = ['icon' => 'calendar', 'color' => '#2f6690', 'label' => 'Event Aktif', 'value' => $n, 'link' => 'event'];
}
if (in_array('karyawan', $modules)) {
    if (isHrdAdministratifRole($user)) {
        $pimFilterDash = getPimpinanExclusionSql($user, '');
        $n = $conn->query("SELECT COUNT(*) n FROM karyawan WHERE status='aktif' AND ($pimFilterDash)")->fetch_assoc()['n'];
        $cards[] = ['icon' => 'users', 'color' => '#6a4fa0', 'label' => 'Total Karyawan Aktif', 'value' => $n, 'link' => 'karyawan'];
    } else {
        $n = $conn->query("SELECT COUNT(*) n FROM kegiatan_karyawan kk JOIN karyawan k ON k.id = kk.karyawan_id WHERE kk.status!='selesai' AND LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener')")->fetch_assoc()['n'];
        $cards[] = ['icon' => 'users', 'color' => '#6a4fa0', 'label' => 'Kegiatan Karyawan Berjalan', 'value' => $n, 'link' => 'karyawan'];
    }
}
if (in_array('arsip', $modules)) {
    $n = $conn->query("SELECT COUNT(*) n FROM arsip")->fetch_assoc()['n'];
    $cards[] = ['icon' => 'archive', 'color' => '#1f7a6c', 'label' => 'Total Dokumen Arsip', 'value' => $n, 'link' => 'arsip'];
}

if ($role === 'admin') {
    $logs = $conn->prepare("SELECT l.*, COALESCE(u.name, l.nama_user, 'Pengguna Dihapus') AS name 
                           FROM log_aktivitas l 
                           LEFT JOIN users u ON u.id = l.user_id 
                           WHERE l.id IN (SELECT MAX(id) FROM log_aktivitas GROUP BY modul) 
                           ORDER BY l.id DESC LIMIT 8");
    $logs->execute();
} else {
    $logs = $conn->prepare("SELECT l.*, COALESCE(u.name, l.nama_user, 'Pengguna Dihapus') AS name 
                           FROM log_aktivitas l 
                           LEFT JOIN users u ON u.id = l.user_id 
                           WHERE l.user_id = ? 
                           ORDER BY l.id DESC LIMIT 8");
    $logs->bind_param("i", $user['id']);
    $logs->execute();
}
$recentLogs = $logs->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="stat-grid">
    <?php foreach ($cards as $c): ?>
        <a href="dashboard.php?page=<?= $c['link'] ?>" class="stat-card" style="display:block">
            <div class="stat-icon" style="background:<?= $c['color'] ?>"><i data-lucide="<?= $c['icon'] ?>"></i></div>
            <h3><?= $c['value'] ?></h3><p><?= htmlspecialchars($c['label']) ?></p>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($role === 'manajer_ga' || $role === 'admin'): ?>
<div class="card">
    <div class="card-head"><div><h2>Struktur Organisasi GA</h2><p>Manajer GA &gt; SPV &gt; Team Leader &gt; CSO / Gardener / Engineering.</p></div></div>
    <div class="hierarchy-tree">
        <?php
        $ga = $conn->query("SELECT * FROM karyawan WHERE atasan_id IS NULL AND jabatan='Manajer GA'")->fetch_all(MYSQLI_ASSOC);
        function renderTree($conn, $node) {
            $children = $conn->query("SELECT * FROM karyawan WHERE atasan_id=" . $node['id'])->fetch_all(MYSQLI_ASSOC);
            echo '<div class="hierarchy-node"><i data-lucide="user"></i> ' . htmlspecialchars($node['nama']) . ' — ' . htmlspecialchars($node['jabatan']) . '</div>';
            if ($children) {
                echo '<ul>';
                foreach ($children as $c) { echo '<li>'; renderTree($conn, $c); echo '</li>'; }
                echo '</ul>';
            }
        }
        foreach ($ga as $g) renderTree($conn, $g);
        ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head"><div><h2>Aktivitas Terbaru</h2><p>Pencatatan aktivitas pengguna pada sistem.</p></div><div id="toolbarAktivitas"></div></div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblAktivitas">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Modul</th><th>Aktivitas</th></tr></thead>
            <tbody>
            <?php if (!$recentLogs): ?><tr><td colspan="4" class="empty-row">Belum ada aktivitas tercatat.</td></tr><?php endif; ?>
            <?php 
            $modulLabels = [
                'karyawan' => 'Karyawan',
                'karyawan_kinerja' => 'Kinerja Karyawan',
                'inventaris' => 'Inventaris',
                'event' => 'Event',
                'maintenance' => 'Maintenance',
                'arsip' => 'Arsip',
                'pengajuan_unit' => 'Pengajuan Unit',
                'laporan' => 'Laporan',
                'akun' => 'Akun Pengguna',
                'auth' => 'Autentikasi',
                'log_aktivitas' => 'Log Aktivitas'
            ];
            foreach ($recentLogs as $l): 
                $modulName = $modulLabels[$l['modul']] ?? ucfirst(str_replace('_', ' ', $l['modul']));
            ?>
                <tr><td><?= htmlspecialchars($l['waktu']) ?></td><td><?= htmlspecialchars($l['name']) ?></td><td><?= htmlspecialchars($modulName) ?></td><td><?= htmlspecialchars($l['aktivitas']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<script>
renderExportToolbar('#toolbarAktivitas', 'tblAktivitas', 'aktivitas_terbaru', 'Aktivitas Terbaru');
</script>
