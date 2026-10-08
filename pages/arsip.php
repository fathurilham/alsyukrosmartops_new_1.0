<?php
$canTambah = hasAccess($role, 'arsip', 'tambah');
$canKelola = hasAccess($role, 'arsip', 'kelola');
$kategoriList = $conn->query("SELECT * FROM kategori_arsip ORDER BY nama_kategori")->fetch_all(MYSQLI_ASSOC);
$totalDok = $conn->query("SELECT COUNT(*) n FROM arsip")->fetch_assoc()['n'];
$totalBackup = $conn->query("SELECT COUNT(*) n FROM backup_arsip")->fetch_assoc()['n'];
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="file-text"></i></div><h3><?= $totalDok ?></h3><p>Total Dokumen</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#6a4fa0"><i data-lucide="folder-tree"></i></div><h3><?= count($kategoriList) ?></h3><p>Kategori Arsip</p></div>
    <?php if ($canKelola): ?><div class="stat-card"><div class="stat-icon" style="background:#2f6690"><i data-lucide="database-backup"></i></div><h3><?= $totalBackup ?></h3><p>Riwayat Backup</p></div><?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <div><h2>Arsip Digital</h2><p>Penyimpanan, klasifikasi &amp; pengaturan hak akses dokumen.</p></div>
        <div class="hgroup">
            <div class="search-bar"><i data-lucide="search"></i><input id="searchArsip" placeholder="Cari dokumen..."></div>
            <div id="toolbarArsip"></div>
            <?php if ($canTambah || $canKelola): ?><button class="btn btn-primary" onclick="openModal('modalUpload')"><i data-lucide="upload"></i> Unggah Dokumen</button><?php endif; ?>
            <?php if ($canKelola): ?><button class="btn btn-outline" onclick="openModal('modalKategori')"><i data-lucide="folder-plus"></i> Kategori</button><?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblArsip">
            <thead><tr><th>Judul Dokumen</th><th>Kategori</th><th>Tingkat Akses</th><th>Diunggah oleh</th><th>Tanggal</th><?php if ($canKelola): ?><th>Aksi</th><?php endif; ?></tr></thead>
            <tbody id="arsipBody"><tr><td colspan="6" class="empty-row">Memuat data...</td></tr></tbody>
        </table>
    </div>
</div>

<?php if ($canKelola): ?>
<div class="card">
    <div class="card-head"><div><h2>Riwayat Backup Arsip</h2><p>Pencadangan data untuk mengurangi risiko kehilangan dokumen.</p></div><div id="toolbarBackup"></div></div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblBackup"><thead><tr><th>Dokumen</th><th>Waktu Backup</th><th>Lokasi</th></tr></thead>
        <tbody id="backupBody"><tr><td colspan="3" class="empty-row">Memuat data...</td></tr></tbody></table>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="modalUpload">
    <div class="modal">
        <div class="modal-head"><h3>Unggah Dokumen</h3><button class="modal-close" onclick="closeModal('modalUpload')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Judul Dokumen</label><input id="aJudul"></div>
            <div class="form-grid">
                <div class="fld"><label>Kategori</label><select id="aKategori"><?php foreach ($kategoriList as $k) echo '<option value="' . $k['id'] . '">' . htmlspecialchars($k['nama_kategori']) . '</option>'; ?></select></div>
                <div class="fld"><label>Tingkat Akses</label><select id="aTingkat"><option value="umum">Umum</option><option value="terbatas">Terbatas</option><option value="rahasia">Rahasia</option></select></div>
            </div>
            <div class="fld"><label>File</label><input type="file" id="aFile"></div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalUpload')">Batal</button><button class="btn btn-primary" onclick="uploadArsip()">Unggah</button></div>
    </div>
</div>

<?php if ($canKelola): ?>
<div class="modal-overlay" id="modalKategori">
    <div class="modal">
        <div class="modal-head"><h3>Tambah Kategori Arsip</h3><button class="modal-close" onclick="closeModal('modalKategori')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Nama Kategori</label><input id="ktNama"></div>
            <div class="fld"><label>Deskripsi</label><textarea id="ktDeskripsi"></textarea></div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalKategori')">Batal</button><button class="btn btn-primary" onclick="simpanKategori()">Simpan</button></div>
    </div>
</div>
<?php endif; ?>

<script>
const A_KELOLA = <?= $canKelola ? 'true' : 'false' ?>;
document.getElementById('searchArsip')?.addEventListener('input', e => {
    const q = e.target.value.toLowerCase();
    document.querySelectorAll('#arsipBody tr').forEach(tr => tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none');
});
const aksesBadge = { umum: 'b-green', terbatas: 'b-yellow', rahasia: 'b-red' };
async function loadArsip() {
    const r = await apiFetch('arsip', 'list', {}, 'GET');
    if (!r.ok) return;
    if (document.getElementById('arsipBody')) updateDOM('arsipBody', r.data.length ? r.data.map(a => `<tr><td><a href="download.php?id=${a.id}" target="_blank" style="text-decoration:none; color:var(--primary); font-weight:600;"><i data-lucide="download" style="width:16px;height:16px;margin-right:4px;"></i>${esc(a.judul_dokumen)}</a></td><td>${esc(a.nama_kategori)||'-'}</td>
        <td><span class="badge ${aksesBadge[a.tingkat_akses]}">${esc(a.tingkat_akses)}</span></td><td>${esc(a.uploader_nama)}</td><td>${a.tanggal_unggah}</td>
        ${A_KELOLA ? `<td><button class="btn btn-outline btn-sm" onclick="backupArsip(${a.id})"><i data-lucide="database-backup"></i> Backup</button></td>` : ''}
        </tr>`).join('') : '<tr><td colspan="6" class="empty-row">Belum ada dokumen.</td></tr>');
}
async function uploadArsip() {
    const fd = new FormData();
    fd.append('action', 'upload');
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('judul_dokumen', document.getElementById('aJudul').value);
    fd.append('kategori_id', document.getElementById('aKategori').value || '');
    fd.append('tingkat_akses', document.getElementById('aTingkat').value);
    const f = document.getElementById('aFile').files[0];
    if (f) fd.append('file', f);
    const res = await fetch('api/arsip.php', { method: 'POST', body: fd });
    const r = await res.json();
    if (r.ok) { toast('Dokumen berhasil diunggah.', 'success'); closeModal('modalUpload'); loadArsip(); } else showModalError('modalUpload', r.msg);
}
async function simpanKategori() {
    const r = await apiFetch('arsip', 'tambah_kategori', { nama_kategori: document.getElementById('ktNama').value, deskripsi: document.getElementById('ktDeskripsi').value });
    if (r.ok) { toast('Kategori tersimpan.', 'success'); location.reload(); } else showModalError('modalKategori', r.msg);
}
async function backupArsip(id) {
    const r = await apiFetch('arsip', 'backup', { arsip_id: id });
    if (r.ok) { toast('Dokumen berhasil di-backup.', 'success'); loadBackup(); } else toast(r.msg, 'error');
}
async function loadBackup() {
    const el = document.getElementById('backupBody');
    if (!el) return;
    const r = await apiFetch('arsip', 'list_backup', {}, 'GET');
    if (!r.ok) return;
    updateDOM('backupBody', r.data.length ? r.data.map(b => `<tr><td>${esc(b.judul_dokumen)||'-'}</td><td>${b.tanggal_backup}</td><td>${esc(b.lokasi_backup)}</td></tr>`).join('') : '<tr><td colspan="3" class="empty-row">Belum ada riwayat backup.</td></tr>');
}
loadArsip(); loadBackup();
setInterval(loadArsip, 10000); // Polling real-time data
setInterval(loadBackup, 10000);
renderExportToolbar('#toolbarArsip', 'tblArsip', 'arsip_digital', 'Arsip Digital');
renderExportToolbar('#toolbarBackup', 'tblBackup', 'riwayat_backup', 'Riwayat Backup Arsip');
</script>
