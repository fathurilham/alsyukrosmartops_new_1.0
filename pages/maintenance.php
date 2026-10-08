<?php
$canAjukan = hasAccess($role, 'maintenance', 'ajukan');
$canVerifikasi = hasAccess($role, 'maintenance', 'verifikasi');
$canApprove = hasAccess($role, 'maintenance', 'approve');
$canApproveAtasan = hasAccess($role, 'maintenance', 'approve_atasan');
$canKelola = hasAccess($role, 'maintenance', 'kelola');
$canMonitoring = hasAccess($role, 'maintenance', 'monitoring');
$fasilitasList = $conn->query("SELECT * FROM fasilitas ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$totalOpen = $conn->query("SELECT COUNT(*) n FROM pengajuan_maintenance WHERE status NOT IN ('selesai','ditolak')")->fetch_assoc()['n'];
$totalSelesai = $conn->query("SELECT COUNT(*) n FROM pengajuan_maintenance WHERE status='selesai'")->fetch_assoc()['n'];
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="building-2"></i></div><h3><?= count($fasilitasList) ?></h3><p>Fasilitas Terdaftar</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#a97c22"><i data-lucide="loader"></i></div><h3><?= $totalOpen ?></h3><p>Dalam Proses</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="check-circle"></i></div><h3><?= $totalSelesai ?></h3><p>Selesai</p></div>
</div>

<div class="card">
    <div class="card-head">
        <div><h2>Pengajuan Maintenance Fasilitas</h2><p>Alur: Pengajuan → Verifikasi (Team Leader) → Approval (SPV Sapras) → Pelaksanaan (Engineering).</p></div>
        <div class="hgroup">
            <div id="toolbarMaintenance"></div>
            <?php if ($canAjukan): ?>
            <button class="btn btn-primary" onclick="openModal('modalAjukan')"><i data-lucide="plus"></i> Ajukan Maintenance</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblMaintenance">
            <thead><tr><th>Fasilitas</th><th>Deskripsi Kerusakan</th><th>Pemohon</th><th>Status</th><th>Teknisi</th><th>Aksi</th></tr></thead>
            <tbody id="maintBody"><tr><td colspan="6" class="empty-row">Memuat data...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalAjukan">
    <div class="modal">
        <div class="modal-head"><h3>Ajukan Perbaikan/Perawatan Fasilitas</h3><button class="modal-close" onclick="closeModal('modalAjukan')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Fasilitas</label>
                <select id="mFasilitas"><?php foreach ($fasilitasList as $f) echo '<option value="' . $f['id'] . '">' . htmlspecialchars($f['nama_fasilitas']) . ' — ' . htmlspecialchars($f['lokasi']) . '</option>'; ?></select>
            </div>
            <div class="fld"><label>Deskripsi Kerusakan</label><textarea id="mDeskripsi" placeholder="Jelaskan kondisi kerusakan..."></textarea></div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalAjukan')">Batal</button><button class="btn btn-primary" onclick="ajukanMaint()">Ajukan</button></div>
    </div>
</div>

<div class="modal-overlay" id="modalProses">
    <div class="modal">
        <div class="modal-head"><h3>Update Proses Maintenance</h3><button class="modal-close" onclick="closeModal('modalProses')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <input type="hidden" id="prosesId">
            <div class="fld"><label>Hasil / Progres Pekerjaan</label><textarea id="prosesHasil"></textarea></div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="simpanProses(0)">Simpan Progres</button>
            <button class="btn btn-primary" onclick="simpanProses(1)"><i data-lucide="check"></i> Tandai Selesai</button>
        </div>
    </div>
</div>

<script>
const M_VERIFIKASI = <?= $canVerifikasi ? 'true' : 'false' ?>;
const M_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
const M_APPROVE_ATASAN = <?= $canApproveAtasan ? 'true' : 'false' ?>;
const M_KELOLA = <?= $canKelola ? 'true' : 'false' ?>;
const mBadge = { diajukan:'b-gray', menunggu_approval_atasan:'b-yellow', disetujui_atasan:'b-teal', ditolak_atasan:'b-red', diverifikasi:'b-blue', disetujui:'b-green', ditolak:'b-red', dikerjakan:'b-purple', selesai:'b-green' };
const mLabel = { diajukan:'Diajukan', menunggu_approval_atasan:'Menunggu Atasan', disetujui_atasan:'Disetujui Atasan', ditolak_atasan:'Ditolak Atasan', diverifikasi:'Diverifikasi TL', disetujui:'Disetujui SPV', ditolak:'Ditolak', dikerjakan:'Dikerjakan', selesai:'Selesai' };

async function ajukanMaint() {
    const r = await apiFetch('maintenance', 'ajukan', { fasilitas_id: document.getElementById('mFasilitas').value, deskripsi_kerusakan: document.getElementById('mDeskripsi').value });
    if (r.ok) { toast('Pengajuan maintenance terkirim.', 'success'); closeModal('modalAjukan'); loadMaint(); } else showModalError('modalAjukan', r.msg);
}
async function loadMaint() {
    const r = await apiFetch('maintenance', 'list_pengajuan', {}, 'GET');
    if (!r.ok) return;
    if (document.getElementById('maintBody')) updateDOM('maintBody', r.data.length ? r.data.map(p => {
        let aksi = '';
        if (p.status === 'menunggu_approval_atasan' && M_APPROVE_ATASAN) aksi = pairAtasan('approve_atasan', p.id);
        else if ((p.status === 'diajukan' || p.status === 'disetujui_atasan') && M_VERIFIKASI) aksi = pair('verifikasi', p.id, 'valid');
        else if (p.status === 'diverifikasi' && M_APPROVE) aksi = pair('approve', p.id, 'setuju');
        else if ((p.status === 'disetujui' || p.status === 'dikerjakan') && M_KELOLA) aksi = `<button class="btn btn-outline btn-sm proses-trigger" data-id="${p.id}" data-hasil="${esc(p.hasil_pekerjaan)}"><i data-lucide="hammer"></i> Proses</button>`;
        else if (p.status.includes('ditolak')) aksi = '<i data-lucide="x" style="color:var(--danger);width:20px;height:20px;margin-top:4px"></i>';
        else if (['disetujui_atasan', 'diverifikasi', 'disetujui', 'dikerjakan', 'selesai'].includes(p.status)) aksi = '<i data-lucide="check" style="color:var(--primary);width:20px;height:20px;margin-top:4px"></i>';
        else aksi = '<span style="color:#9ca3af;font-size:12px;margin-top:4px">—</span>';
        return `<tr><td>${esc(p.nama_fasilitas)}</td><td>${esc(p.deskripsi_kerusakan)}</td><td>${esc(p.pemohon_nama)}</td>
        <td><span class="badge ${mBadge[p.status]}">${mLabel[p.status]}</span></td><td>${esc(p.teknisi_nama)||'-'}</td><td class="actions-cell">${aksi}</td></tr>`;
    }).join('') : '<tr><td colspan="6" class="empty-row">Belum ada pengajuan maintenance.</td></tr>');
    
    setTimeout(() => {
        document.querySelectorAll('.proses-trigger').forEach(el => {
            el.addEventListener('click', function() { bukaProses(this.dataset.id, this.dataset.hasil); });
        });
    }, 50);
}
function pair(action, id, field) {
    return `<button class="btn btn-primary btn-sm" onclick="proses1('${action}',${id},'${field}',1)"><i data-lucide="check"></i></button>
            <button class="btn btn-danger btn-sm" onclick="proses1('${action}',${id},'${field}',0)"><i data-lucide="x"></i></button>`;
}
function pairAtasan(action, id) {
    return `<button class="btn btn-primary btn-sm" onclick="prosesAtasan('${action}',${id},1)">Setuju</button>
            <button class="btn btn-danger btn-sm" onclick="prosesAtasan('${action}',${id},0)">Tolak</button>`;
}
async function proses1(action, id, field, val) {
    const payload = { id }; payload[field] = val;
    const r = await apiFetch('maintenance', action, payload);
    if (r.ok) { toast('Berhasil diproses.', 'success'); loadMaint(); } else toast(r.msg, 'error');
}
async function prosesAtasan(action, id, val) {
    let catatan = '';
    if (!val) {
        catatan = await customPrompt('Masukkan alasan penolakan:');
        if (catatan === null) return;
    }
    const r = await apiFetch('maintenance', action, { id, setuju: val, catatan_atasan: catatan });
    if (r.ok) { toast('Berhasil diproses.', 'success'); loadMaint(); } else toast(r.msg, 'error');
}
function bukaProses(id, hasil) { document.getElementById('prosesId').value = id; document.getElementById('prosesHasil').value = hasil; openModal('modalProses'); }
async function simpanProses(selesai) {
    const r = await apiFetch('maintenance', 'proses', { id: document.getElementById('prosesId').value, hasil_pekerjaan: document.getElementById('prosesHasil').value, selesai });
    if (r.ok) { toast(selesai ? 'Maintenance ditandai selesai.' : 'Progres disimpan.', 'success'); closeModal('modalProses'); loadMaint(); } else showModalError('modalProses', r.msg);
}
loadMaint();
setInterval(loadMaint, 10000); // Polling real-time data
<?php if ($canKelola || $canVerifikasi || $canMonitoring): ?>
renderExportToolbar('#toolbarMaintenance', 'tblMaintenance', 'maintenance_fasilitas', 'Pengajuan Maintenance Fasilitas');
<?php endif; ?>
</script>
