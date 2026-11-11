<?php
$roles = getAllRoles();
if (canAccessPimpinanUnit($user)) {
    $units = $conn->query("SELECT * FROM struktur_unit ORDER BY nama_unit")->fetch_all(MYSQLI_ASSOC);
} else {
    $units = $conn->query("SELECT * FROM struktur_unit WHERE nama_unit != 'Pimpinan/Direksi' ORDER BY nama_unit")->fetch_all(MYSQLI_ASSOC);
}
?>
<div class="card">
    <div class="card-head">
        <div><h2>Kelola Akun Pengguna</h2><p>Setiap akun terikat pada satu peran sesuai struktur organisasi (Model 1 &amp; Model 2).</p></div>
        <div class="hgroup"><div id="toolbarAkun"></div><button class="btn btn-primary" onclick="bukaTambahAkun()"><i data-lucide="user-plus"></i> Tambah Akun</button></div>
    </div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblAkun">
            <thead><tr><th>Username</th><th>Nama</th><th>Peran</th><th>Unit</th><th>Status</th><th class="no-print">Aksi</th></tr></thead>
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
        <div class="modal-head"><h3 id="modalAkunTitle">Tambah Akun Pengguna</h3><button class="modal-close" onclick="closeModal('modalAkun')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <input type="hidden" id="uId" value="">
            <div class="form-grid">
                <div class="fld"><label>Username *</label><input id="uUsername" autocomplete="off" placeholder="contoh: budi_ga"></div>
                <div class="fld"><label>Nama Lengkap *</label><input id="uName" placeholder="contoh: Budi Santoso"></div>
                <div class="fld"><label>Email</label><input id="uEmail" type="email" placeholder="contoh: budi@alsyukro.sch.id"></div>
                <div class="fld">
                    <label id="uPasswordLabel">Password *</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input id="uPassword" type="password" style="width:100%; padding-right:40px;" placeholder="Minimal 8 karakter (huruf & angka)">
                        <button type="button" onclick="const p=document.getElementById('uPassword'); p.type=p.type==='password'?'text':'password'; this.innerHTML=p.type==='password'?'<i data-lucide=\'eye\'></i>':'<i data-lucide=\'eye-off\'></i>'; lucide.createIcons();" style="position:absolute; right:10px; background:none; border:none; cursor:pointer; color:#6f7669; display:flex; align-items:center; padding:0;">
                            <i data-lucide="eye"></i>
                        </button>
                    </div>
                    <small id="uPasswordHint" style="display:none; color:var(--muted); font-size:11.5px; margin-top:4px;">Kosongkan jika tidak ingin mengubah password.</small>
                </div>
                <div class="fld"><label>Peran *</label><select id="uRole"><?php foreach ($roles as $r) echo '<option value="' . $r['id'] . '">' . htmlspecialchars($r['nama_role']) . '</option>'; ?></select></div>
                <div class="fld"><label>Unit/Divisi</label><select id="uUnit"><option value="">-- Tidak terikat unit --</option><?php foreach ($units as $u) echo '<option value="' . $u['id'] . '">' . htmlspecialchars($u['nama_unit']) . '</option>'; ?></select></div>
                <div class="fld" id="uStatusGroup" style="display:none;">
                    <label>Status Akun *</label>
                    <select id="uStatus">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalAkun')">Batal</button>
            <button class="btn btn-primary" id="btnSimpanAkun" onclick="simpanAkun()"><i data-lucide="save"></i> Simpan</button>
        </div>
    </div>
</div>

<script>
const CURRENT_USER_ID = <?= (int)$user['id'] ?>;
let dataAkunList = [];

async function loadAkun() {
    const r = await apiFetch('akun', 'list', {}, 'GET');
    if (!r.ok) return;
    dataAkunList = r.data || [];
    document.getElementById('akunBody').innerHTML = dataAkunList.map(u => {
        const unitLabel = (u.nama_unit && u.nama_unit.trim() !== '') ? esc(u.nama_unit) : '<span style="color:#9ca3af;font-size:12px">- Tidak ada unit -</span>';
        const isSelf = Number(u.id) === CURRENT_USER_ID;
        const isOnline = Number(u.sedang_login) === 1;
        const sessionBadge = isOnline
            ? `<span class="badge b-blue" style="margin-left:4px" title="Aktif terakhir: ${esc(u.session_last_activity || '-')}"><i data-lucide="monitor" style="width:11px;height:11px;vertical-align:-1px"></i> Sedang Login</span>`
            : `<span class="badge b-gray" style="margin-left:4px;opacity:0.7">Offline</span>`;
        const btnForceLogout = (isOnline && !isSelf)
            ? `<button class="btn btn-outline btn-sm" style="border-color:#d97706;color:#b45309" onclick="paksaLogout(${u.id},'${esc(u.username)}')" title="Paksa Logout Sesi Aktif"><i data-lucide="log-out"></i> Paksa Logout</button>`
            : '';
        return `<tr>
        <td style="cursor:pointer;color:var(--primary)" class="akses-trigger" data-role="${esc(u.role)}" data-nama="${esc(u.nama_role)}">${esc(u.username)}</td>
        <td style="cursor:pointer" class="akses-trigger" data-role="${esc(u.role)}" data-nama="${esc(u.nama_role)}">${esc(u.name)}</td>
        <td>${esc(u.nama_role)}</td>
        <td>${unitLabel}</td>
        <td><span class="badge ${u.status==='aktif'?'b-green':'b-gray'}">${esc(u.status)}</span>${sessionBadge}</td>
        <td class="actions-cell no-print">
            <button class="btn btn-outline btn-sm" onclick="bukaEditAkun(${u.id})" title="Edit Data Pengguna"><i data-lucide="pencil"></i></button>
            <button class="btn btn-outline btn-sm akses-trigger" data-role="${esc(u.role)}" data-nama="${esc(u.nama_role)}" title="Detail Akses"><i data-lucide="key"></i></button>
            <button class="btn btn-outline btn-sm" onclick="resetPw(${u.id})" title="Reset Password"><i data-lucide="refresh-cw"></i></button>
            <button class="btn ${u.status==='aktif'?'btn-outline':'btn-primary'} btn-sm" onclick="toggleStatus(${u.id},'${u.status==='aktif'?'nonaktif':'aktif'}')" title="${u.status==='aktif'?'Nonaktifkan Akun':'Aktifkan Akun'}">${u.status==='aktif'?'Nonaktifkan':'Aktifkan'}</button>
            ${btnForceLogout}
            ${isSelf ? '' : `<button class="btn btn-danger btn-sm" onclick="hapusAkun(${u.id},'${esc(u.username)}')" title="Hapus Akun Pengguna"><i data-lucide="trash-2"></i></button>`}
        </td></tr>`;
    }).join('');
    lucide.createIcons();
    document.querySelectorAll('.akses-trigger').forEach(el => {
        el.addEventListener('click', function() { showAkses(this.dataset.role, this.dataset.nama); });
    });
}

function bukaTambahAkun() {
    document.getElementById('modalAkunTitle').textContent = 'Tambah Akun Pengguna';
    document.getElementById('uId').value = '';
    document.getElementById('uUsername').value = '';
    document.getElementById('uName').value = '';
    document.getElementById('uEmail').value = '';
    document.getElementById('uPassword').value = '';
    document.getElementById('uPasswordLabel').innerHTML = 'Password *';
    document.getElementById('uPasswordHint').style.display = 'none';
    document.getElementById('uRole').selectedIndex = 0;
    document.getElementById('uUnit').value = '';
    document.getElementById('uStatusGroup').style.display = 'none';
    document.getElementById('uStatus').value = 'aktif';
    document.getElementById('btnSimpanAkun').innerHTML = '<i data-lucide="save"></i> Simpan';
    openModal('modalAkun');
    lucide.createIcons();
    setTimeout(() => document.getElementById('uUsername')?.focus(), 50);
}

function bukaEditAkun(id) {
    const u = dataAkunList.find(x => Number(x.id) === Number(id));
    if (!u) return toast('Data pengguna tidak ditemukan.', 'error');

    document.getElementById('modalAkunTitle').textContent = 'Edit Akun: ' + u.name;
    document.getElementById('uId').value = u.id;
    document.getElementById('uUsername').value = u.username || '';
    document.getElementById('uName').value = u.name || '';
    document.getElementById('uEmail').value = u.email || '';
    document.getElementById('uPassword').value = '';
    document.getElementById('uPasswordLabel').innerHTML = 'Password Baru <span style="font-weight:normal;font-size:12px;color:var(--muted);">(Opsional)</span>';
    document.getElementById('uPasswordHint').style.display = 'block';
    document.getElementById('uRole').value = u.role_id || '';
    document.getElementById('uUnit').value = u.unit_id || '';
    document.getElementById('uStatusGroup').style.display = 'block';
    document.getElementById('uStatus').value = u.status || 'aktif';
    document.getElementById('btnSimpanAkun').innerHTML = '<i data-lucide="check"></i> Simpan Perubahan';
    openModal('modalAkun');
    lucide.createIcons();
    setTimeout(() => document.getElementById('uName')?.focus(), 50);
}

async function simpanAkun() {
    const id = document.getElementById('uId').value;
    const username = document.getElementById('uUsername').value.trim();
    const name = document.getElementById('uName').value.trim();
    const email = document.getElementById('uEmail').value.trim();
    const password = document.getElementById('uPassword').value;
    const role_id = document.getElementById('uRole').value;
    const unit_id = document.getElementById('uUnit').value;
    const status = document.getElementById('uStatus').value;

    if (!username || !name || !role_id) {
        return showModalError('modalAkun', 'Semua kolom wajib bertanda * harus diisi.');
    }

    if (!id && !password) {
        return showModalError('modalAkun', 'Password wajib diisi untuk akun baru.');
    }

    if (password && (password.length < 8 || !/[A-Za-z]/.test(password) || !/[0-9]/.test(password))) {
        return showModalError('modalAkun', 'Password minimal 8 karakter dan harus mengandung huruf serta angka.');
    }

    const payload = {
        username,
        name,
        email,
        role_id,
        unit_id
    };

    if (password) {
        payload.password = password;
    }

    const action = id ? 'edit' : 'tambah';
    if (id) {
        payload.id = id;
        payload.status = status;
    }

    const r = await apiFetch('akun', action, payload);
    if (r.ok) {
        toast(id ? 'Data pengguna berhasil diperbarui.' : 'Akun berhasil dibuat.', 'success');
        closeModal('modalAkun');
        loadAkun();
    } else {
        showModalError('modalAkun', r.msg);
    }
}

async function toggleStatus(id, status) {
    const r = await apiFetch('akun', 'toggle_status', { id, status });
    if (r.ok) { toast('Status akun diperbarui.', 'success'); loadAkun(); } else toast(r.msg, 'error');
}

async function paksaLogout(id, username) {
    if (await customConfirm(`Yakin ingin memaksa logout sesi aktif milik akun "${username}"?`)) {
        const r = await apiFetch('akun', 'paksa_logout', { id });
        if (r.ok) {
            toast(`Sesi aktif akun "${username}" berhasil dihentikan.`, 'success');
            loadAkun();
        } else {
            toast(r.msg, 'error');
        }
    }
}

async function resetPw(id) {
    if(await customConfirm('Yakin ingin mereset password pengguna ini?')) {
        const r = await apiFetch('akun', 'reset_password', { id, password: 'password123' });
        if (r.ok) toast('Password direset ke: password123', 'success'); else toast(r.msg, 'error');
    }
}

async function hapusAkun(id, username) {
    if (await customConfirm(`Yakin ingin menghapus akun pengguna "${username}" secara permanen? Tindakan ini tidak dapat dibatalkan.`)) {
        const r = await apiFetch('akun', 'hapus', { id });
        if (r.ok) {
            toast(`Akun "${username}" berhasil dihapus.`, 'success');
            loadAkun();
        } else {
            toast(r.msg, 'error');
        }
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
