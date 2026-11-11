<?php
$modules = getRoleModules($role);
$data = [];
if (in_array('inventaris', $modules) && !in_array($role, ['manajer_markom', 'manajer_qms'], true)) {
    $data['Inventaris'] = $conn->query("SELECT status, COUNT(*) n FROM peminjaman GROUP BY status")->fetch_all(MYSQLI_ASSOC);
}
if (in_array('maintenance', $modules) && !in_array($role, ['manajer_markom', 'manajer_qms'], true)) {
    $data['Maintenance'] = $conn->query("SELECT status, COUNT(*) n FROM pengajuan_maintenance GROUP BY status")->fetch_all(MYSQLI_ASSOC);
}
if (in_array('event', $modules) && $role !== 'manajer_qms') {
    $data['Event'] = $conn->query("SELECT status, COUNT(*) n FROM event GROUP BY status")->fetch_all(MYSQLI_ASSOC);
}
if (in_array('karyawan', $modules) && !isHrdAdministratifRole($user) && !in_array($role, ['manajer_markom', 'manajer_qms'], true)) {
    $data['Kegiatan Karyawan'] = $conn->query("SELECT kk.status, COUNT(*) n FROM kegiatan_karyawan kk JOIN karyawan k ON k.id = kk.karyawan_id WHERE LOWER(TRIM(k.jabatan)) IN ('cso', 'gardener') GROUP BY kk.status")->fetch_all(MYSQLI_ASSOC);
}
if (in_array('arsip', $modules) && hasAccess($role, 'arsip', 'monitoring') && $role !== 'manajer_markom') {
    $data['Arsip'] = $conn->query("SELECT tingkat_akses AS status, COUNT(*) n FROM arsip GROUP BY tingkat_akses")->fetch_all(MYSQLI_ASSOC);
}
?>
<div class="card">
    <div class="card-head">
        <div><h2>Dashboard Analitik & Laporan Operasional</h2><p>Rekap lintas modul sesuai hak akses peran Anda.</p></div>
        <div class="hgroup no-print">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()"><i data-lucide="printer"></i> Cetak Laporan</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="exportExcel('tblLaporanRekap', 'laporan_operasional')"><i data-lucide="file-spreadsheet"></i> Excel</button>
            <button type="button" class="btn btn-outline btn-sm" onclick="exportPDF('tblLaporanRekap', 'laporan_operasional', 'Laporan Operasional')"><i data-lucide="file-text"></i> PDF</button>
        </div>
    </div>
</div>
<div class="stat-grid" id="laporanGrid">
    <?php foreach ($data as $modul => $rows): ?>
        <div class="card" style="margin-bottom:0">
            <div class="card-head"><div><h2><?= htmlspecialchars($modul) ?></h2></div></div>
            <?php foreach ($rows as $r): ?>
                <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13px;border-bottom:1px solid var(--border)">
                    <span><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $r['status']))) ?></span><b><?= $r['n'] ?></b>
                </div>
            <?php endforeach; ?>
            <?php if (!$rows): ?><p style="color:var(--muted);font-size:13px">Belum ada data.</p><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php if (!$data): ?><div class="empty-state"><i data-lucide="bar-chart-2"></i><h3>Belum ada data laporan</h3><p>Data akan tampil setelah ada aktivitas pada modul yang menjadi hak akses Anda.</p></div><?php endif; ?>

<!-- Tabel tersembunyi khusus sumber data ekspor Excel/PDF (rekap semua modul) -->
<table id="tblLaporanRekap" class="d-none">
    <thead><tr><th>Modul</th><th>Status</th><th>Jumlah</th></tr></thead>
    <tbody>
    <?php foreach ($data as $modul => $rows): foreach ($rows as $r): ?>
        <tr><td><?= htmlspecialchars($modul) ?></td><td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $r['status']))) ?></td><td><?= $r['n'] ?></td></tr>
    <?php endforeach; endforeach; ?>
    </tbody>
</table>
