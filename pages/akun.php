<?php
$roles = getAllRoles();
$units = $conn->query("SELECT * FROM struktur_unit ORDER BY nama_unit")->fetch_all(MYSQLI_ASSOC);
?>
<div class="card">
    <div class="card-head">
        <div><h2>Kelola Akun Pengguna</h2><p>Setiap akun terikat pada satu peran sesuai struktur organisasi (Model 1 &amp; Model 2).</p></div>
        <div class="hgroup"><div id="toolbarAkun"></div><button class="btn btn-primary" onclick="openModal('modalAkun')"><i data-lucide="user-plus"></i> Tambah Akun</button></div>
    </div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblAkun">
            <thead><tr><th>Username</th><th>Nama</th><th>Peran</th><th>Unit</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody id="akunBody"><tr><td colspan="6" class="empty-row">Memuat data...</td></tr></tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="modalAkses">
    <div class="modal">
        <div class="modal-head"><h3>Detail Hak Akses: <span id="mAksesRole"></span></h3><button class="modal-close" onclick="closeModal('modalAkses')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div id="mAksesBody">Memuat data...</div>
        </div>
        <div class="modal-foot"><button class="btn btn-primary" onclick="closeModal('modalAkses')">Tutup</button></div>
    </div>
</div>

<div class="modal-overlay" id="modalAkun">
    <div class="modal">
        <div class="modal-head"><h3>Tambah Akun Pengguna</h3><button class="modal-close" onclick="closeModal('modalAkun')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="fld"><label>Username *</label><input id="uUsername"></div>
                <div class="fld"><label>Nama Lengkap *</label><input id="uName"></div>
                <div class="fld"><label>Email</label><input id="uEmail" type="email"></div>
                <div class="fld">
                    <label>Password *</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input id="uPassword" type="password" style="width:100%; padding-right:40px;">
                        <button type="button" onclick="const p=document.getElementById('uPassword'); p.type=p.type==='password'?'text':'password'; this.innerHTML=p.type==='password'?'<i data-lucide=\'eye\'></i>':'<i data-lucide=\'eye-off\'></i>'; lucide.createIcons();" style="position:absolute; right:10px; background:none; border:none; cursor:pointer; color:#6f7669; display:flex; align-items:center; padding:0;">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                </div>
                <div class="fld"><label>Peran *</label><select id="uRole"><?php foreach ($roles as $r) echo '<option value="' . $r['id'] . '">' . htmlspecialchars($r['nama_role']) . '</option>'; ?></select></div>
                <div class="fld"><label>Unit/Divisi</label><select id="uUnit"><option value="">-- Tidak terikat unit --</option><?php foreach ($units as $u) echo '<option value="' . $u['id'] . '">' . htmlspecialchars($u['nama_unit']) . '</option>'; ?></select></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalAkun')">Batal</button><button class="btn btn-primary" onclick="simpanAkun()">Simpan</button></div>
    </div>
</div>

<script>
async function loadAkun() {
    const r = await apiFetch('akun', 'list', {}, 'GET');
    if (!r.ok) return;
    document.getElementById('akunBody').innerHTML = r.data.map(u => {
        const unitLabel = (u.nama_unit && u.nama_unit.trim() !== '') ? esc(u.nama_unit) : '<span style="color:#9ca3af;font-size:12px">- Tidak ada unit -</span>';
        return `<tr>
        <td style="cursor:pointer;color:var(--primary)" class="akses-trigger" data-role="${esc(u.role)}" data-nama="${esc(u.nama_role)}">${esc(u.username)}</td>
        <td style="cursor:pointer" class="akses-trigger" data-role="${esc(u.role)}" data-nama="${esc(u.nama_role)}">${esc(u.name)}</td>
        <td>${esc(u.nama_role)}</td>
        <td>${unitLabel}</td>
        <td><span class="badge ${u.status==='aktif'?'b-green':'b-gray'}">${esc(u.status)}</span></td>
        <td class="actions-cell">
            <button class="btn btn-outline btn-sm akses-trigger" data-role="${esc(u.role)}" data-nama="${esc(u.nama_role)}" title="Detail Akses"><i data-lucide="key"></i></button>
            <button class="btn btn-outline btn-sm" onclick="resetPw(${u.id})" title="Reset Password"><i data-lucide="refresh-cw"></i></button>
            <button class="btn ${u.status==='aktif'?'btn-danger':'btn-primary'} btn-sm" onclick="toggleStatus(${u.id},'${u.status==='aktif'?'nonaktif':'aktif'}')">${u.status==='aktif'?'Nonaktifkan':'Aktifkan'}</button>
        </td></tr>`;
    }).join('');
    lucide.createIcons();
    document.querySelectorAll('.akses-trigger').forEach(el => {
        el.addEventListener('click', function() { showAkses(this.dataset.role, this.dataset.nama); });
    });
}
async function simpanAkun() {
    const r = await apiFetch('akun', 'tambah', {
        username: document.getElementById('uUsername').value, name: document.getElementById('uName').value,
        email: document.getElementById('uEmail').value, password: document.getElementById('uPassword').value,
        role_id: document.getElementById('uRole').value, unit_id: document.getElementById('uUnit').value,
    });
    if (r.ok) { toast('Akun berhasil dibuat.', 'success'); closeModal('modalAkun'); loadAkun(); } else showModalError('modalAkun', r.msg);
}
async function toggleStatus(id, status) {
    const r = await apiFetch('akun', 'toggle_status', { id, status });
    if (r.ok) { toast('Status akun diperbarui.', 'success'); loadAkun(); } else toast(r.msg, 'error');
}
async function resetPw(id) {
    if(await customConfirm('Yakin ingin mereset password pengguna ini?')) {
        const r = await apiFetch('akun', 'reset_password', { id, password: 'password123' });
        if (r.ok) toast('Password direset ke: password123', 'success'); else toast(r.msg, 'error');
    }
}
async function showAkses(roleKode, roleNama) {
    document.getElementById('mAksesRole').textContent = roleNama;
    document.getElementById('mAksesBody').innerHTML = 'Memuat data...';
    openModal('modalAkses');
    const r = await apiFetch('akun', 'hak_akses', { role: roleKode }, 'GET');
    if (r.ok) {
        if (Object.keys(r.data).length === 0) {
            document.getElementById('mAksesBody').innerHTML = '<p class="text-muted">Tidak ada hak akses khusus (hanya akses dasar/login).</p>';
            return;
        }
        let html = '<ul style="padding-left: 20px; line-height: 1.6;">';
        for (const [modul, aksi] of Object.entries(r.data)) {
            html += `<li><b>${esc(modul).toUpperCase()}</b>: ${aksi.map(a => esc(a)).join(', ')}</li>`;
        }
        html += '</ul>';
        document.getElementById('mAksesBody').innerHTML = html;
    } else {
        document.getElementById('mAksesBody').innerHTML = '<p class="text-danger">Gagal memuat hak akses.</p>';
    }
}
loadAkun();
renderExportToolbar('#toolbarAkun', 'tblAkun', 'kelola_akun', 'Kelola Akun Pengguna');
</script>
