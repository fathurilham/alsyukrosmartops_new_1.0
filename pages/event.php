<?php
$canAjukan = hasAccess($role, 'event', 'ajukan');
$canVerifikasi = hasAccess($role, 'event', 'verifikasi');
$canApprove = hasAccess($role, 'event', 'approve');
$canApproveAtasan = hasAccess($role, 'event', 'approve_atasan');
$canKelola = hasAccess($role, 'event', 'kelola');
$canMonitoring = hasAccess($role, 'event', 'monitoring');
$totalBerjalan = $conn->query("SELECT COUNT(*) n FROM event WHERE status IN ('disetujui','berlangsung')")->fetch_assoc()['n'];
$totalMenunggu = $conn->query("SELECT COUNT(*) n FROM event WHERE status IN ('diajukan','diverifikasi')")->fetch_assoc()['n'];
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#c79a3f"><i data-lucide="calendar-clock"></i></div><h3><?= $totalMenunggu ?></h3><p>Menunggu Persetujuan</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="calendar-check-2"></i></div><h3><?= $totalBerjalan ?></h3><p>Disetujui / Berlangsung</p></div>
</div>

<div class="card">
    <div class="card-head">
        <div><h2>Event Management System</h2><p>Alur: Pengajuan → Persetujuan Atasan Unit Pengaju → Verifikasi (SPV CRM) → Approval (Manajer MARKOM). Sistem mencegah benturan jadwal otomatis.</p></div>
        <div class="hgroup">
            <div id="toolbarEvent"></div>
            <?php if ($canAjukan): ?>
            <button class="btn btn-primary" onclick="bukaFormEvent()"><i data-lucide="plus"></i> Ajukan Event</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblEvent">
            <thead><tr><th>Nama Event</th><th>Deskripsi</th><th>Jadwal</th><th>Lokasi</th><th>Pemohon</th><th>Status</th><th>Dokumentasi</th><th>Aksi</th></tr></thead>
            <tbody id="eventBody"><tr><td colspan="8" class="empty-row">Memuat data...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalEvent">
    <div class="modal">
        <div class="modal-head"><h3>Ajukan Kegiatan / Event</h3><button class="modal-close" onclick="closeModal('modalEvent')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Nama Event</label><input id="eNama"></div>
            <div class="fld"><label>Deskripsi</label><textarea id="eDeskripsi"></textarea></div>
            <div class="form-grid">
                <div class="fld"><label>Tanggal</label><input type="date" id="eTanggal"></div>
                <div class="fld"><label>Lokasi</label><input id="eLokasi" placeholder="Aula, Lapangan, dsb."></div>
                <div class="fld"><label>Jam Mulai</label><input type="time" id="eJamMulai" value="08:00"></div>
                <div class="fld"><label>Jam Selesai</label><input type="time" id="eJamSelesai" value="10:00"></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalEvent')">Batal</button><button class="btn btn-primary" onclick="ajukanEvent()">Ajukan</button></div>
    </div>
</div>

<div class="modal-overlay" id="modalDokumentasi">
    <div class="modal">
        <div class="modal-head"><h3>Dokumentasi Kegiatan: <span id="dokEventNama"></span></h3><button class="modal-close" onclick="closeModal('modalDokumentasi')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="fld"><label>Unggah Foto/File Dokumentasi</label><input type="file" id="dFile"></div>
            <div class="fld"><label>Keterangan</label><input id="dKeterangan" placeholder="mis. Dokumentasi pelaksanaan acara"></div>
            <div class="table-wrap" style="margin-top:12px">
                <table class="dtable table table-sm mb-0">
                    <thead><tr><th>File</th><th>Keterangan</th><th>Tanggal Unggah</th></tr></thead>
                    <tbody id="dokBody"><tr><td colspan="3" class="empty-row">Belum ada dokumentasi.</td></tr></tbody>
                </table>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalDokumentasi')">Tutup</button><button class="btn btn-primary" onclick="uploadDokumentasi()">Unggah</button></div>
    </div>
</div>

<script>
const E_VERIFIKASI = <?= $canVerifikasi ? 'true' : 'false' ?>;
const E_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
const E_APPROVE_ATASAN = <?= $canApproveAtasan ? 'true' : 'false' ?>;
const E_KELOLA = <?= $canKelola ? 'true' : 'false' ?>;
const eBadge = { diajukan:'b-gray', menunggu_approval_atasan:'b-yellow', disetujui_atasan:'b-teal', ditolak_atasan:'b-red', diverifikasi:'b-blue', disetujui:'b-green', ditolak:'b-red', berlangsung:'b-purple', selesai:'b-green' };
const eLabel = { diajukan:'Diajukan', menunggu_approval_atasan:'Menunggu Atasan', disetujui_atasan:'Disetujui Atasan', ditolak_atasan:'Ditolak Atasan', diverifikasi:'Diverifikasi', disetujui:'Disetujui', ditolak:'Ditolak', berlangsung:'Berlangsung', selesai:'Selesai' };

function bukaFormEvent() {
    document.getElementById('eNama').value = '';
    document.getElementById('eDeskripsi').value = '';
    document.getElementById('eTanggal').value = '';
    document.getElementById('eLokasi').value = '';
    document.getElementById('eJamMulai').value = '08:00';
    document.getElementById('eJamSelesai').value = '10:00';
    openModal('modalEvent');
}

async function ajukanEvent() {
    const r = await apiFetch('event', 'ajukan', {
        nama_event: document.getElementById('eNama').value,
        deskripsi: document.getElementById('eDeskripsi').value,
        tanggal: document.getElementById('eTanggal').value,
        lokasi: document.getElementById('eLokasi').value,
        jam_mulai: document.getElementById('eJamMulai').value,
        jam_selesai: document.getElementById('eJamSelesai').value,
    });
    if (r.ok) { toast('Pengajuan event terkirim.', 'success'); closeModal('modalEvent'); loadEvent(); } else showModalError('modalEvent', r.msg);
}
async function loadEvent() {
    const r = await apiFetch('event', 'list', {}, 'GET');
    if (!r.ok) return;
    if (document.getElementById('eventBody')) updateDOM('eventBody', r.data.length ? r.data.map(e => {
        let aksi = '';
        if (e.status === 'menunggu_approval_atasan' && E_APPROVE_ATASAN) aksi = pairAtasanE('approve_atasan', e.id);
        else if ((e.status === 'diajukan' || e.status === 'disetujui_atasan') && E_VERIFIKASI) aksi = pairE('verifikasi', e.id, 'valid');
        else if (e.status === 'diverifikasi' && E_APPROVE) aksi = pairE('approve', e.id, 'setuju');
        else if (e.status === 'disetujui' && (E_KELOLA || E_VERIFIKASI)) aksi = `<button class="btn btn-outline btn-sm" onclick="setStatus(${e.id},'berlangsung')">Mulai</button>`;
        else if (e.status === 'berlangsung' && (E_KELOLA || E_VERIFIKASI)) aksi = `<button class="btn btn-primary btn-sm" onclick="setStatus(${e.id},'selesai')">Selesaikan</button>`;
        else if (e.status.includes('ditolak')) aksi = '<i data-lucide="x" style="color:var(--danger);width:20px;height:20px;margin-top:4px"></i>';
        else if (['disetujui_atasan', 'diverifikasi', 'disetujui', 'berlangsung', 'selesai'].includes(e.status)) aksi = '<i data-lucide="check" style="color:var(--primary);width:20px;height:20px;margin-top:4px"></i>';
        else aksi = '<span style="color:#9ca3af;font-size:12px;margin-top:4px">—</span>';
        let dok = '<span style="color:#9ca3af;font-size:12px">—</span>';
        if (['berlangsung','selesai'].includes(e.status) && (E_KELOLA || E_VERIFIKASI)) {
            dok = `<button class="btn btn-outline btn-sm dok-trigger" data-id="${e.id}" data-nama="${esc(e.nama_event)}"><i data-lucide="camera"></i> Dokumentasi</button>`;
        }
        return `<tr><td>${esc(e.nama_event)}</td><td>${esc(e.deskripsi)||'-'}</td><td>${e.tanggal||'-'} ${e.jam_mulai||''}-${e.jam_selesai||''}</td><td>${esc(e.lokasi)||'-'}</td><td>${esc(e.pemohon_nama)}</td>
        <td><span class="badge ${eBadge[e.status]}">${eLabel[e.status]}</span></td><td>${dok}</td><td class="actions-cell">${aksi}</td></tr>`;
    }).join('') : '<tr><td colspan="8" class="empty-row">Belum ada pengajuan event.</td></tr>');
    
    // Add event listeners for elements with data attributes
    setTimeout(() => {
        document.querySelectorAll('.dok-trigger').forEach(el => {
            el.addEventListener('click', function() { bukaDokumentasi(this.dataset.id, this.dataset.nama); });
        });
    }, 50);
}
function pairE(action, id, field) {
    return `<button class="btn btn-primary btn-sm" onclick="prosesE('${action}',${id},'${field}',1)"><i data-lucide="check"></i></button>
            <button class="btn btn-danger btn-sm" onclick="prosesE('${action}',${id},'${field}',0)"><i data-lucide="x"></i></button>`;
}
function pairAtasanE(action, id) {
    return `<button class="btn btn-primary btn-sm" onclick="prosesAtasanE('${action}',${id},1)">Setuju</button>
            <button class="btn btn-danger btn-sm" onclick="prosesAtasanE('${action}',${id},0)">Tolak</button>`;
}
async function prosesE(action, id, field, val) {
    const payload = { id }; payload[field] = val;
    const r = await apiFetch('event', action, payload);
    if (r.ok) { toast('Berhasil diproses.', 'success'); loadEvent(); } else toast(r.msg, 'error');
}
async function prosesAtasanE(action, id, val) {
    let catatan = '';
    if (!val) {
        catatan = await customPrompt('Masukkan alasan penolakan:');
        if (catatan === null) return;
    }
    const r = await apiFetch('event', action, { id, setuju: val, catatan_atasan: catatan });
    if (r.ok) { toast('Berhasil diproses.', 'success'); loadEvent(); } else toast(r.msg, 'error');
}
async function setStatus(id, status) {
    const r = await apiFetch('event', 'update_status', { id, status });
    if (r.ok) { toast('Status event diperbarui.', 'success'); loadEvent(); } else toast(r.msg, 'error');
}
let dokEventId = null;
async function bukaDokumentasi(id, nama) {
    dokEventId = id;
    document.getElementById('dokEventNama').textContent = nama;
    document.getElementById('dFile').value = '';
    document.getElementById('dKeterangan').value = '';
    openModal('modalDokumentasi');
    await loadDokumentasi();
}
async function loadDokumentasi() {
    const r = await apiFetch('event', 'list_dokumentasi', { event_id: dokEventId }, 'GET');
    if (!r.ok) return;
    document.getElementById('dokBody').innerHTML = r.data.length ? r.data.map(d => `<tr><td>${esc(d.file_path.split('/').pop())}</td><td>${esc(d.keterangan)||'-'}</td><td>${d.tanggal_unggah}</td></tr>`).join('') : '<tr><td colspan="3" class="empty-row">Belum ada dokumentasi.</td></tr>';
}
async function uploadDokumentasi() {
    const fd = new FormData();
    fd.append('action', 'upload_dokumentasi');
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('event_id', dokEventId);
    fd.append('keterangan', document.getElementById('dKeterangan').value);
    const f = document.getElementById('dFile').files[0];
    if (f) fd.append('file', f);
    const res = await fetch('api/event.php', { method: 'POST', body: fd });
    const r = await res.json();
    if (r.ok) { toast('Dokumentasi berhasil diunggah.', 'success'); document.getElementById('dFile').value=''; document.getElementById('dKeterangan').value=''; loadDokumentasi(); }
    else showModalError('modalDokumentasi', r.msg);
}
loadEvent();
setInterval(loadEvent, 10000); // Polling real-time data
<?php if ($canKelola || $canVerifikasi || $canMonitoring): ?>
renderExportToolbar('#toolbarEvent', 'tblEvent', 'data_event', 'Event Management');
<?php endif; ?>
</script>
