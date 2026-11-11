<?php
$canAjukan = hasAccess($role, 'maintenance', 'ajukan');
$canVerifikasi = hasAccess($role, 'maintenance', 'verifikasi');
$canVerifikasiPengganti = hasAccess($role, 'maintenance', 'verifikasi_pengganti');
$canApprove = hasAccess($role, 'maintenance', 'approve');
$canApproveAkhirPengganti = hasAccess($role, 'maintenance', 'approve_akhir_pengganti');
$canApproveAtasan = hasAccess($role, 'maintenance', 'approve_atasan');
$canApprovePengganti = hasAccess($role, 'maintenance', 'approve_pengganti');
$canKelola = hasAccess($role, 'maintenance', 'kelola');
$canMonitoring = hasAccess($role, 'maintenance', 'monitoring');
$totalPengajuan = $conn->query("SELECT COUNT(*) n FROM pengajuan_maintenance")->fetch_assoc()['n'];
$totalOpen = $conn->query("SELECT COUNT(*) n FROM pengajuan_maintenance WHERE status NOT IN ('selesai','ditolak')")->fetch_assoc()['n'];
$totalSelesai = $conn->query("SELECT COUNT(*) n FROM pengajuan_maintenance WHERE status='selesai'")->fetch_assoc()['n'];
?>
<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="clipboard-list"></i></div><h3><?= $totalPengajuan ?></h3><p>Total Pengajuan</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#a97c22"><i data-lucide="loader"></i></div><h3><?= $totalOpen ?></h3><p>Dalam Proses</p></div>
    <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="check-circle"></i></div><h3><?= $totalSelesai ?></h3><p>Selesai</p></div>
</div>

<div class="card">
    <div class="card-head">
        <div><h2>Pengajuan Maintenance Fasilitas</h2><p>Alur: Pengajuan → Verifikasi (Team Leader) → Approval (SPV Sapras) → Pelaksanaan (Engineering).</p></div>
        <div class="hgroup">
            <div id="toolbarMaintenance"></div>
            <?php if ($canAjukan): ?>
            <button class="btn btn-primary" onclick="bukaModalAjukan()"><i data-lucide="plus"></i> Ajukan Maintenance</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table class="dtable table table-hover align-middle mb-0" id="tblMaintenance">
            <thead>
                <tr>
                    <th style="width:70px;text-align:center">Foto</th>
                    <th>Deskripsi Kerusakan / Kendala</th>
                    <th>Pemohon</th>
                    <th>Status</th>
                    <th>Teknisi</th>
                    <th class="no-print" style="text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody id="maintBody"><tr><td colspan="6" class="empty-row">Memuat data...</td></tr></tbody>
        </table>
    </div>
</div>

<!-- MODAL AJUKAN PERBAIKAN/PERAWATAN FASILITAS -->
<div class="modal-overlay" id="modalAjukan">
    <div class="modal">
        <div class="modal-head">
            <h3>Ajukan Perbaikan/Perawatan Fasilitas</h3>
            <button class="modal-close" onclick="closeModal('modalAjukan')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <!-- Foto Kerusakan / Kendala -->
            <div class="fld">
                <label>Foto Kerusakan atau Kendala <span style="color:var(--danger)">*</span></label>
                <div class="upload-area" id="mFotoDropzone" onclick="document.getElementById('mFoto').click()" style="border:2px dashed var(--border);border-radius:var(--bs-border-radius);padding:22px 16px;text-align:center;cursor:pointer;background:#faf9f5;transition:all 0.2s ease;">
                    <input type="file" id="mFoto" accept="image/*" capture="environment" style="display:none" onchange="previewMaintFoto(this)">
                    <div id="mFotoPlaceholder">
                        <i data-lucide="camera" style="width:34px;height:34px;color:var(--primary);margin-bottom:8px"></i>
                        <div style="font-weight:600;font-size:13.5px;color:var(--text)">Ambil Foto atau Pilih Gambar</div>
                        <div style="font-size:11.5px;color:var(--muted);margin-top:4px">Format JPG, PNG, atau WebP (Maks. 5MB)</div>
                    </div>
                    <div id="mFotoPreviewWrapper" style="display:none;position:relative">
                        <img id="mFotoPreview" src="" alt="Preview Foto" style="max-height:180px;max-width:100%;border-radius:6px;object-fit:contain;box-shadow:0 2px 8px rgba(0,0,0,0.1)">
                        <div style="margin-top:12px;display:flex;justify-content:center;gap:8px">
                            <button type="button" class="btn btn-outline btn-sm" onclick="event.stopPropagation();document.getElementById('mFoto').click();"><i data-lucide="refresh-cw"></i> Ganti Foto</button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="event.stopPropagation();hapusMaintFoto();"><i data-lucide="trash-2"></i> Hapus</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deskripsi Kerusakan atau Kendala -->
            <div class="fld">
                <label>Deskripsi Kerusakan atau Kendala <span style="color:var(--danger)">*</span></label>
                <textarea id="mDeskripsi" placeholder="Jelaskan kondisi kerusakan atau kendala yang dialami secara rinci..." rows="4"></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalAjukan')">Batal</button>
            <button class="btn btn-primary" id="btnSubmitMaint" onclick="ajukanMaint()"><i data-lucide="send"></i> Ajukan</button>
        </div>
    </div>
</div>

<!-- MODAL PREVIEW FOTO BESAR -->
<div class="modal-overlay" id="modalLihatFoto">
    <div class="modal" style="max-width:540px">
        <div class="modal-head">
            <h3 id="judulLihatFoto">Foto Kerusakan / Kendala</h3>
            <button class="modal-close" onclick="closeModal('modalLihatFoto')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body" style="text-align:center;padding:18px">
            <div style="background:#111;border-radius:8px;overflow:hidden;display:inline-block;max-width:100%;box-shadow:0 4px 16px rgba(0,0,0,0.15)">
                <img id="imgPreviewBesar" src="" alt="Foto Kerusakan" style="max-width:100%;max-height:65vh;display:block;margin:0 auto;object-fit:contain">
            </div>
            <div id="descPreviewBesar" style="font-size:13px;color:var(--text);margin-top:14px;text-align:left;background:#fbfaf6;border:1px solid var(--border);border-radius:6px;padding:10px 14px"></div>
        </div>
        <div class="modal-foot">
            <a id="btnUnduhFoto" href="#" download class="btn btn-outline"><i data-lucide="download"></i> Unduh Foto</a>
            <button class="btn btn-primary" onclick="closeModal('modalLihatFoto')">Tutup</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modalProses">
    <div class="modal">
        <div class="modal-head"><h3>Update Proses Maintenance</h3><button class="modal-close" onclick="closeModal('modalProses')"><i data-lucide="x"></i></button></div>
        <div class="modal-body">
            <input type="hidden" id="prosesId">

            <!-- Foto Bukti Progres / Selesai -->
            <div class="fld">
                <label>Foto Bukti Progres / Penyelesaian Pekerjaan</label>
                <div class="upload-area" id="pFotoDropzone" onclick="document.getElementById('pFoto').click()" style="border:2px dashed var(--border);border-radius:var(--bs-border-radius);padding:18px 16px;text-align:center;cursor:pointer;background:#faf9f5;transition:all 0.2s ease;">
                    <input type="file" id="pFoto" accept="image/*" capture="environment" style="display:none" onchange="previewProsesFoto(this)">
                    <div id="pFotoPlaceholder">
                        <i data-lucide="camera" style="width:30px;height:30px;color:var(--primary);margin-bottom:6px"></i>
                        <div style="font-weight:600;font-size:13px;color:var(--text)">Ambil Foto atau Pilih Gambar Bukti</div>
                        <div style="font-size:11.5px;color:var(--muted);margin-top:3px">Format JPG, PNG, atau WebP (Maks. 5MB)</div>
                    </div>
                    <div id="pFotoPreviewWrapper" style="display:none;position:relative">
                        <img id="pFotoPreview" src="" alt="Preview Bukti Foto" style="max-height:160px;max-width:100%;border-radius:6px;object-fit:contain;box-shadow:0 2px 8px rgba(0,0,0,0.1)">
                        <div style="margin-top:10px;display:flex;justify-content:center;gap:8px">
                            <button type="button" class="btn btn-outline btn-sm" onclick="event.stopPropagation();document.getElementById('pFoto').click();"><i data-lucide="refresh-cw"></i> Ganti Foto</button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="event.stopPropagation();hapusProsesFoto();"><i data-lucide="trash-2"></i> Hapus</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hasil / Progres Pekerjaan -->
            <div class="fld">
                <label>Hasil / Progres Pekerjaan</label>
                <textarea id="prosesHasil" rows="3" placeholder="Jelaskan progres perbaikan, suku cadang yang diganti, atau kendala pekerjaan..."></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" id="btnSimpanProgres" onclick="simpanProses(0)">Simpan Progres</button>
            <button class="btn btn-primary" id="btnTandaiSelesai" onclick="simpanProses(1)"><i data-lucide="check"></i> Tandai Selesai</button>
        </div>
    </div>
</div>

<!-- MODAL APPROVAL PENGGANTI MAINTENANCE -->
<div class="modal-overlay" id="modalPenggantiMaint">
    <div class="modal">
        <div class="modal-head">
            <h3 id="modalPenggantiMaintTitle">Persetujuan sebagai Pengganti</h3>
            <button class="modal-close" onclick="closeModal('modalPenggantiMaint')"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="pgMaintId">
            <input type="hidden" id="pgMaintSetuju">
            <input type="hidden" id="pgMaintAction" value="approve_atasan">
            <p id="pgMaintDesc" style="margin-bottom:14px;font-size:13.5px;color:var(--muted)">Anda akan memproses pengajuan maintenance ini menggantikan pejabat yang sedang berhalangan. Tindakan ini dicatat di log audit dan dikirimkan ke pejabat asli.</p>
            <div class="fld">
                <label>Alasan Menggantikan <span style="color:var(--danger)">*</span> <small style="color:var(--muted);font-weight:400">(10–255 karakter)</small></label>
                <textarea id="pgMaintAlasan" rows="3" maxlength="255" placeholder="Contoh: Petugas yang bersangkutan sedang izin / sakit hari ini..."></textarea>
            </div>
            <div class="fld" id="pgMaintCatatanWrap" style="display:none">
                <label>Catatan Penolakan (Opsional)</label>
                <textarea id="pgMaintCatatan" rows="2" placeholder="Catatan tambahan untuk pemohon..."></textarea>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-outline" onclick="closeModal('modalPenggantiMaint')">Batal</button>
            <button class="btn btn-primary" id="btnSubmitPenggantiMaint" type="button" onclick="submitApprovePenggantiMaint(this)">Simpan Keputusan</button>
        </div>
    </div>
</div>

<script>
const M_VERIFIKASI = <?= $canVerifikasi ? 'true' : 'false' ?>;
const M_VERIFIKASI_PENGGANTI = <?= $canVerifikasiPengganti ? 'true' : 'false' ?>;
const M_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
const M_APPROVE_AKHIR_PENGGANTI = <?= $canApproveAkhirPengganti ? 'true' : 'false' ?>;
const M_APPROVE_ATASAN = <?= $canApproveAtasan ? 'true' : 'false' ?>;
const M_APPROVE_PENGGANTI = <?= $canApprovePengganti ? 'true' : 'false' ?>;
const M_KELOLA = <?= $canKelola ? 'true' : 'false' ?>;
const mBadge = { diajukan:'b-gray', menunggu_approval_atasan:'b-yellow', disetujui_atasan:'b-teal', ditolak_atasan:'b-red', diverifikasi:'b-blue', disetujui:'b-green', ditolak:'b-red', dikerjakan:'b-purple', selesai:'b-green' };
const mLabel = { diajukan:'Diajukan', menunggu_approval_atasan:'Menunggu Atasan', disetujui_atasan:'Disetujui Atasan', ditolak_atasan:'Ditolak Atasan', diverifikasi:'Diverifikasi TL', disetujui:'Disetujui SPV', ditolak:'Ditolak', dikerjakan:'Dikerjakan', selesai:'Selesai' };

function previewMaintFoto(input) {
    const file = input.files && input.files[0];
    if (file) {
        if (!file.type.startsWith('image/')) {
            toast('File harus berupa gambar (JPG, PNG, atau WebP)', 'error');
            input.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            toast('Ukuran gambar maksimal 5MB', 'error');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('mFotoPreview').src = e.target.result;
            document.getElementById('mFotoPlaceholder').style.display = 'none';
            document.getElementById('mFotoPreviewWrapper').style.display = 'block';
            if (window.lucide) lucide.createIcons({ root: document.getElementById('mFotoPreviewWrapper') });
        };
        reader.readAsDataURL(file);
    }
}

function hapusMaintFoto() {
    document.getElementById('mFoto').value = '';
    document.getElementById('mFotoPreview').src = '';
    document.getElementById('mFotoPreviewWrapper').style.display = 'none';
    document.getElementById('mFotoPlaceholder').style.display = 'block';
}

function resetFormAjukan() {
    hapusMaintFoto();
    document.getElementById('mDeskripsi').value = '';
}

function bukaModalAjukan() {
    resetFormAjukan();
    openModal('modalAjukan');
}

function bukaModalFoto(src, desc, title = 'Foto Kerusakan / Kendala') {
    document.getElementById('imgPreviewBesar').src = src;
    document.getElementById('descPreviewBesar').textContent = desc || '-';
    document.getElementById('btnUnduhFoto').href = src;
    const titleEl = document.getElementById('judulLihatFoto');
    if (titleEl) titleEl.textContent = title;
    openModal('modalLihatFoto');
}

// Drag & Drop pada area upload
const dropzone = document.getElementById('mFotoDropzone');
if (dropzone) {
    ['dragenter', 'dragover'].forEach(ev => {
        dropzone.addEventListener(ev, (e) => {
            e.preventDefault(); e.stopPropagation();
            dropzone.style.borderColor = 'var(--primary)';
            dropzone.style.background = 'var(--primary-tint)';
        });
    });
    ['dragleave', 'drop'].forEach(ev => {
        dropzone.addEventListener(ev, (e) => {
            e.preventDefault(); e.stopPropagation();
            dropzone.style.borderColor = 'var(--border)';
            dropzone.style.background = '#faf9f5';
        });
    });
    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files && files.length > 0) {
            document.getElementById('mFoto').files = files;
            previewMaintFoto(document.getElementById('mFoto'));
        }
    });
}

async function ajukanMaint() {
    const deskripsi = document.getElementById('mDeskripsi').value.trim();
    const fotoInput = document.getElementById('mFoto');
    const fotoFile = fotoInput.files && fotoInput.files[0];

    if (!fotoFile) {
        showModalError('modalAjukan', 'Silakan unggah foto kerusakan atau kendala terlebih dahulu.');
        return;
    }
    if (!deskripsi) {
        showModalError('modalAjukan', 'Deskripsi kerusakan atau kendala wajib diisi.');
        return;
    }

    const btn = document.getElementById('btnSubmitMaint');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Mengirim...';
        if (window.lucide) lucide.createIcons({ root: btn });
    }

    const r = await apiFetch('maintenance', 'ajukan', {
        deskripsi_kerusakan: deskripsi,
        foto: fotoFile
    });

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="send"></i> Ajukan';
        if (window.lucide) lucide.createIcons({ root: btn });
    }

    if (r.ok) {
        toast('Pengajuan maintenance berhasil terkirim.', 'success');
        closeModal('modalAjukan');
        resetFormAjukan();
        loadMaint();
    } else {
        showModalError('modalAjukan', r.msg);
    }
}

let existingFotoPekerjaan = '';

function previewProsesFoto(input) {
    const file = input.files && input.files[0];
    if (file) {
        if (!file.type.startsWith('image/')) {
            toast('File harus berupa gambar (JPG, PNG, atau WebP)', 'error');
            input.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            toast('Ukuran gambar maksimal 5MB', 'error');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('pFotoPreview').src = e.target.result;
            document.getElementById('pFotoPlaceholder').style.display = 'none';
            document.getElementById('pFotoPreviewWrapper').style.display = 'block';
            if (window.lucide) lucide.createIcons({ root: document.getElementById('pFotoPreviewWrapper') });
        };
        reader.readAsDataURL(file);
    }
}

function hapusProsesFoto() {
    document.getElementById('pFoto').value = '';
    document.getElementById('pFotoPreview').src = '';
    document.getElementById('pFotoPreviewWrapper').style.display = 'none';
    document.getElementById('pFotoPlaceholder').style.display = 'block';
    existingFotoPekerjaan = '';
}

// Drag & drop area upload bukti proses
const pDropzone = document.getElementById('pFotoDropzone');
if (pDropzone) {
    ['dragenter', 'dragover'].forEach(ev => {
        pDropzone.addEventListener(ev, (e) => {
            e.preventDefault(); e.stopPropagation();
            pDropzone.style.borderColor = 'var(--primary)';
            pDropzone.style.background = 'var(--primary-tint)';
        });
    });
    ['dragleave', 'drop'].forEach(ev => {
        pDropzone.addEventListener(ev, (e) => {
            e.preventDefault(); e.stopPropagation();
            pDropzone.style.borderColor = 'var(--border)';
            pDropzone.style.background = '#faf9f5';
        });
    });
    pDropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files && files.length > 0) {
            document.getElementById('pFoto').files = files;
            previewProsesFoto(document.getElementById('pFoto'));
        }
    });
}

async function loadMaint() {
    const r = await apiFetch('maintenance', 'list_pengajuan', {}, 'GET');
    if (!r.ok) return;
    if (document.getElementById('maintBody')) updateDOM('maintBody', r.data.length ? r.data.map(p => {
        let aksi = '';
        if (p.status === 'menunggu_approval_atasan' && M_APPROVE_ATASAN && p.can_approve_atasan) aksi = pairAtasan('approve_atasan', p.id);
        else if (p.status === 'menunggu_approval_atasan' && M_APPROVE_PENGGANTI && p.can_approve_pengganti) aksi = pairPenggantiMaint(p.id, 'approve_atasan', 'Setujui');
        else if ((p.status === 'diajukan' || p.status === 'disetujui_atasan') && M_VERIFIKASI && p.can_verifikasi) aksi = pair('verifikasi', p.id, 'valid');
        else if ((p.status === 'diajukan' || p.status === 'disetujui_atasan') && M_VERIFIKASI_PENGGANTI && p.can_verifikasi_pengganti) aksi = pairPenggantiMaint(p.id, 'verifikasi', 'Verifikasi');
        else if (p.status === 'diverifikasi' && M_APPROVE && p.can_approve_akhir) aksi = pair('approve', p.id, 'setuju');
        else if (p.status === 'diverifikasi' && M_APPROVE_AKHIR_PENGGANTI && p.can_approve_akhir_pengganti) aksi = pairPenggantiMaint(p.id, 'approve', 'Approve');
        else if ((p.status === 'disetujui' || p.status === 'dikerjakan') && M_KELOLA) aksi = `<button class="btn btn-outline btn-sm proses-trigger" data-id="${p.id}" data-hasil="${esc(p.hasil_pekerjaan || '')}" data-foto="${esc(p.foto_pekerjaan || '')}"><i data-lucide="hammer"></i> Proses</button>`;
        else if (p.status.includes('ditolak')) aksi = '<i data-lucide="x" style="color:var(--danger);width:20px;height:20px;margin-top:4px"></i>';
        else if (['disetujui_atasan', 'diverifikasi', 'disetujui', 'dikerjakan', 'selesai'].includes(p.status)) aksi = '<i data-lucide="check" style="color:var(--primary);width:20px;height:20px;margin-top:4px"></i>';
        else aksi = '<span style="color:#9ca3af;font-size:12px;margin-top:4px">—</span>';

        let fotoHtml = '<div style="width:50px;height:50px;background:#eeece4;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#9ca3af;margin:0 auto"><i data-lucide="image-off" style="width:18px;height:18px"></i></div>';
        if (p.foto) {
            fotoHtml = `<img src="${esc(p.foto)}" alt="Foto Kerusakan" style="width:50px;height:50px;object-fit:cover;border-radius:6px;cursor:pointer;border:1px solid var(--border);transition:transform 0.15s ease;display:block;margin:0 auto;" onmouseover="this.style.transform='scale(1.08)'" onmouseout="this.style.transform='scale(1)'" onclick="bukaModalFoto('${esc(p.foto)}', 'Foto Kerusakan: ${esc(p.deskripsi_kerusakan)}')" title="Klik untuk perbesar">`;
        }

        let descHtml = `<div style="font-weight:500;color:var(--text);">${esc(p.deskripsi_kerusakan)}</div>`;
        if (p.nama_fasilitas) {
            descHtml += `<div style="font-size:11.5px;color:var(--muted);margin-top:3px"><i data-lucide="building-2" style="width:12px;height:12px;display:inline-block;vertical-align:-2px"></i> ${esc(p.nama_fasilitas)}${p.lokasi ? ' — ' + esc(p.lokasi) : ''}</div>`;
        }
        if (p.catatan_atasan) {
            descHtml += `<div style="font-size:11.5px;color:var(--danger);margin-top:3px"><i data-lucide="alert-circle" style="width:12px;height:12px;display:inline-block;vertical-align:-2px"></i> Catatan Atasan: ${esc(p.catatan_atasan)}</div>`;
        }
        if (p.approval_pengganti_oleh) {
            descHtml += `<div style="font-size:11.5px;color:#6b4f1d;background:#fef3c7;border:1px solid #fde68a;border-radius:4px;padding:3px 6px;margin-top:4px">Disetujui Atasan (Pengganti): <b>${esc(p.pengganti_nama || '-')}</b> — ${esc(p.approval_pengganti_alasan || '-')}</div>`;
        }
        if (p.verifikasi_pengganti_oleh) {
            descHtml += `<div style="font-size:11.5px;color:#1e40af;background:#dbeafe;border:1px solid #bfdbfe;border-radius:4px;padding:3px 6px;margin-top:4px">Diverifikasi Pengganti: <b>${esc(p.verifikasi_pengganti_nama || '-')}</b> — ${esc(p.verifikasi_pengganti_alasan || '-')}</div>`;
        }
        if (p.approve_akhir_pengganti_oleh) {
            descHtml += `<div style="font-size:11.5px;color:#065f46;background:#d1fae5;border:1px solid #a7f3d0;border-radius:4px;padding:3px 6px;margin-top:4px">Approve Akhir Pengganti: <b>${esc(p.approve_akhir_pengganti_nama || '-')}</b> — ${esc(p.approve_akhir_pengganti_alasan || '-')}</div>`;
        }
        if (p.foto_pekerjaan) {
            descHtml += `<div style="margin-top:6px"><button type="button" class="btn btn-outline btn-sm" style="padding:2px 8px;font-size:11.5px;color:var(--primary);border-color:var(--primary)" onclick="bukaModalFoto('${esc(p.foto_pekerjaan)}', 'Bukti Hasil Pekerjaan Teknisi: ${esc(p.hasil_pekerjaan || '-')}', 'Foto Bukti Pekerjaan Maintenance')"><i data-lucide="camera" style="width:12px;height:12px;vertical-align:-2px"></i> Bukti Pekerjaan</button></div>`;
        }

        return `<tr>
            <td style="text-align:center">${fotoHtml}</td>
            <td>${descHtml}</td>
            <td>${esc(p.pemohon_nama)}</td>
            <td><span class="badge ${mBadge[p.status] || 'b-gray'}">${mLabel[p.status] || p.status}</span></td>
            <td>${esc(p.teknisi_nama) || '-'}</td>
            <td class="actions-cell no-print" style="justify-content:center">${aksi}</td>
        </tr>`;
    }).join('') : '<tr><td colspan="6" class="empty-row">Belum ada pengajuan maintenance.</td></tr>');
    
    setTimeout(() => {
        document.querySelectorAll('.proses-trigger').forEach(el => {
            el.addEventListener('click', function() { bukaProses(this.dataset.id, this.dataset.hasil, this.dataset.foto); });
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
function pairPenggantiMaint(id, action = 'approve_atasan', label = 'Setujui') {
    return `<button class="btn btn-outline btn-sm" onclick="bukaModalPenggantiMaint(${id},1,'${action}','${label}')">${label} sbg Pengganti</button>
            <button class="btn btn-danger btn-sm" onclick="bukaModalPenggantiMaint(${id},0,'${action}','${label}')">Tolak sbg Pengganti</button>`;
}
function bukaModalPenggantiMaint(id, setuju, action = 'approve_atasan', label = 'Setujui') {
    document.getElementById('pgMaintId').value = id;
    document.getElementById('pgMaintSetuju').value = setuju ? '1' : '0';
    document.getElementById('pgMaintAction').value = action;
    document.getElementById('pgMaintAlasan').value = '';
    document.getElementById('pgMaintCatatan').value = '';
    document.getElementById('pgMaintCatatanWrap').style.display = setuju ? 'none' : 'block';
    const descEl = document.getElementById('pgMaintDesc');
    if (descEl) {
        if (action === 'verifikasi') {
            descEl.textContent = 'Anda akan memverifikasi pengajuan maintenance ini sebagai pengganti Team Leader yang sedang berhalangan. Tindakan ini dicatat di log audit dan dikirimkan ke Team Leader.';
        } else if (action === 'approve') {
            descEl.textContent = 'Anda akan memberikan persetujuan akhir pengajuan maintenance ini sebagai pengganti SPV Sapras yang sedang berhalangan. Tindakan ini dicatat di log audit dan dikirimkan ke SPV Sapras.';
        } else {
            descEl.textContent = 'Anda akan memproses pengajuan maintenance ini menggantikan atasan langsung pemohon yang sedang berhalangan. Tindakan ini dicatat di log audit dan dikirimkan ke approver asli.';
        }
    }
    document.getElementById('modalPenggantiMaintTitle').textContent = setuju
        ? `${label} sebagai Pengganti`
        : `Tolak (${label}) sebagai Pengganti`;
    const btn = document.getElementById('btnSubmitPenggantiMaint');
    if (btn) {
        btn.className = setuju ? 'btn btn-primary' : 'btn btn-danger';
        btn.textContent = setuju ? `${label} sebagai Pengganti` : 'Tolak sebagai Pengganti';
    }
    openModal('modalPenggantiMaint');
}
async function submitApprovePenggantiMaint(btn) {
    const id = document.getElementById('pgMaintId').value;
    const setuju = document.getElementById('pgMaintSetuju').value;
    const action = document.getElementById('pgMaintAction').value || 'approve_atasan';
    const alasan = document.getElementById('pgMaintAlasan').value.trim();
    const catatan = document.getElementById('pgMaintCatatan').value.trim();
    if (alasan.length < 10) {
        return showModalError('modalPenggantiMaint', 'Alasan menggantikan wajib diisi minimal 10 karakter.');
    }
    if (alasan.length > 255) {
        return showModalError('modalPenggantiMaint', 'Alasan menggantikan maksimal 255 karakter.');
    }
    if (btn) btn.disabled = true;
    try {
        const r = await apiFetch('maintenance', action, {
            id,
            setuju,
            valid: setuju,
            pengganti: 1,
            alasan,
            catatan: catatan,
            catatan_atasan: catatan
        });
        if (r.ok) {
            toast('Keputusan pengganti berhasil disimpan.', 'success');
            closeModal('modalPenggantiMaint');
            loadMaint();
        } else {
            showModalError('modalPenggantiMaint', r.msg);
        }
    } finally {
        if (btn) btn.disabled = false;
    }
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
function bukaProses(id, hasil, foto) {
    document.getElementById('prosesId').value = id;
    document.getElementById('prosesHasil').value = hasil || '';
    hapusProsesFoto();
    if (foto) {
        existingFotoPekerjaan = foto;
        document.getElementById('pFotoPreview').src = foto;
        document.getElementById('pFotoPlaceholder').style.display = 'none';
        document.getElementById('pFotoPreviewWrapper').style.display = 'block';
        if (window.lucide) lucide.createIcons({ root: document.getElementById('pFotoPreviewWrapper') });
    }
    openModal('modalProses');
}
async function simpanProses(selesai) {
    const id = document.getElementById('prosesId').value;
    const hasil = document.getElementById('prosesHasil').value.trim();
    const fotoInput = document.getElementById('pFoto');
    const fotoFile = fotoInput.files && fotoInput.files[0];

    if (selesai && !fotoFile && !existingFotoPekerjaan) {
        showModalError('modalProses', 'Silakan unggah foto bukti pekerjaan yang telah selesai.');
        return;
    }

    const btn = selesai ? document.getElementById('btnTandaiSelesai') : document.getElementById('btnSimpanProgres');
    const oldHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Menyimpan...';
        if (window.lucide) lucide.createIcons({ root: btn });
    }

    const payload = {
        id: id,
        hasil_pekerjaan: hasil,
        selesai: selesai
    };
    if (fotoFile) {
        payload.foto_pekerjaan = fotoFile;
    }

    const r = await apiFetch('maintenance', 'proses', payload);

    if (btn) {
        btn.disabled = false;
        btn.innerHTML = oldHtml;
        if (window.lucide) lucide.createIcons({ root: btn });
    }

    if (r.ok) {
        toast(selesai ? 'Maintenance ditandai selesai.' : 'Progres pekerjaan disimpan.', 'success');
        closeModal('modalProses');
        hapusProsesFoto();
        loadMaint();
    } else {
        showModalError('modalProses', r.msg);
    }
}

loadMaint();
setInterval(loadMaint, 10000); // Polling real-time data
<?php if ($canKelola || $canVerifikasi || $canMonitoring): ?>
renderExportToolbar('#toolbarMaintenance', 'tblMaintenance', 'maintenance_fasilitas', 'Pengajuan Maintenance Fasilitas');
<?php endif; ?>
</script>
