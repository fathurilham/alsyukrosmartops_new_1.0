    <?php
    $canAjukan = hasAccess($role, 'inventaris', 'ajukan');
    $canTambah = hasAccess($role, 'inventaris', 'tambah');
    $canKelola = hasAccess($role, 'inventaris', 'kelola');
    $canVerifikasi = hasAccess($role, 'inventaris', 'verifikasi');
    $canApprove = hasAccess($role, 'inventaris', 'approve');
    $canApproveAtasan = hasAccess($role, 'inventaris', 'approve_atasan');
    $canMonitoring = hasAccess($role, 'inventaris', 'monitoring');

    $barangList = $conn->query("SELECT * FROM barang ORDER BY kode_barang ASC")->fetch_all(MYSQLI_ASSOC);
    $stokTotal = array_sum(array_column($barangList, 'stok'));
    $pinjamAktif = $conn->query("SELECT COUNT(*) n FROM peminjaman WHERE status='disetujui'")->fetch_assoc()['n'];
    $menunggu = $conn->query("SELECT COUNT(*) n FROM peminjaman WHERE status IN ('diajukan','diverifikasi')")->fetch_assoc()['n'];

    $activeTab = $_GET['tab'] ?? 'barang';
    ?>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <div class="stat-grid">
        <div class="stat-card"><div class="stat-icon" style="background:#145c3f"><i data-lucide="package"></i></div><h3><?= count($barangList) ?></h3><p>Jenis Barang</p></div>
        <div class="stat-card"><div class="stat-icon" style="background:#2f6690"><i data-lucide="layers"></i></div><h3><?= $stokTotal ?></h3><p>Total Stok</p></div>
        <div class="stat-card"><div class="stat-icon" style="background:#a97c22"><i data-lucide="clock"></i></div><h3><?= $menunggu ?></h3><p>Menunggu Verifikasi/Approval</p></div>
        <div class="stat-card"><div class="stat-icon" style="background:#6a4fa0"><i data-lucide="handshake"></i></div><h3><?= $pinjamAktif ?></h3><p>Peminjaman Aktif</p></div>
    </div>

    <div class="tabs" id="invTabs">
        <button class="tab-btn <?= $activeTab === 'barang' ? 'active' : '' ?>" data-tab="barang">Data Barang</button>
        <button class="tab-btn <?= $activeTab === 'pinjam' ? 'active' : '' ?>" data-tab="pinjam">Peminjaman & Persetujuan</button>
        <?php if ($canKelola || $canVerifikasi): ?><button class="tab-btn <?= $activeTab === 'pengembalian' ? 'active' : '' ?>" data-tab="pengembalian">Pengembalian</button><?php endif; ?>
        <?php if ($canKelola): ?>
        <button class="tab-btn <?= $activeTab === 'pemeriksaan' ? 'active' : '' ?>" data-tab="pemeriksaan">Pemeriksaan SPV</button>
        <button class="tab-btn <?= $activeTab === 'riwayat' ? 'active' : '' ?>" data-tab="riwayat">Riwayat Perubahan</button>

        <?php endif; ?>
    </div>

    <div class="tab-panel" id="panel-barang" <?= $activeTab === 'barang' ? '' : 'style="display:none"' ?>>
        <div class="card">
            <div class="card-head">
                <div><h2>Daftar Barang Inventaris</h2><p>Kode, kategori, lokasi, stok, dan status barang.</p></div>
                <div class="hgroup">
                    <div class="search-bar"><i data-lucide="search"></i><input id="searchBarang" placeholder="Cari barang..."></div>
                    <div id="toolbarBarang"></div>
                    <?php if ($canTambah): ?><button class="btn btn-primary" onclick="openModal('modalBarang')"><i data-lucide="plus"></i> Tambah Barang</button><?php endif; ?>
                </div>
            </div>
            <div class="table-wrap">
                <table class="dtable table table-hover align-middle mb-0" id="tblBarang">
                    <thead><tr><th>Kode</th><th>Nama Barang</th><th>Kategori</th><th>Lokasi</th><th>Stok</th><th>Status</th><?php if ($canKelola): ?><th>Aksi</th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php if (!$barangList): ?><tr><td colspan="7" class="empty-row">Belum ada data barang.</td></tr><?php endif; ?>
                    <?php foreach ($barangList as $b):
                        $statusBadge = ['tersedia' => 'b-green', 'habis' => 'b-yellow', 'rusak' => 'b-red'][$b['status']] ?? 'b-gray'; ?>
                        <tr>
                            <td><b><?= htmlspecialchars($b['kode_barang']) ?></b></td>
                            <td><?= htmlspecialchars($b['nama_barang']) ?></td>
                            <td><?= htmlspecialchars($b['kategori']) ?></td>
                            <td><?= htmlspecialchars($b['lokasi']) ?></td>
                            <td><?= $b['stok'] ?></td>
                            <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($b['status']) ?></span></td>
                            <?php if ($canKelola): ?>
                            <td class="actions-cell"><button class="btn btn-outline btn-sm btn-icon" onclick='editBarang(<?= json_encode($b) ?>)'><i data-lucide="pencil"></i></button></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-panel" id="panel-pinjam" <?= $activeTab === 'pinjam' ? '' : 'style="display:none"' ?>>
        <div class="card">
            <div class="card-head">
                <div><h2>Pengajuan & Persetujuan Peminjaman</h2><p>Alur: Ajukan → Verifikasi (Staff Inventaris) → Approve (SPV Inventaris).</p></div>
                <div class="hgroup">
                    <div id="toolbarPinjam"></div>
                    <?php if ($canAjukan): ?>
                    <button class="btn btn-primary" onclick="bukaFormPinjam()"><i data-lucide="plus"></i> Ajukan Peminjaman</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-wrap">
                <table class="dtable table table-hover align-middle mb-0" id="tblPinjam">
                    <thead><tr><th>Barang</th><th>Jml</th><th>Peminjam</th><th>Tgl Pinjam</th><th>Rencana Kembali</th><th>Keperluan</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody id="pinjamBody"><tr><td colspan="8" class="empty-row">Memuat data...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($canKelola || $canVerifikasi): ?>
    <div class="tab-panel" id="panel-pengembalian" <?= $activeTab === 'pengembalian' ? '' : 'style="display:none"' ?>>
        <div class="card">
            <div class="card-head"><div><h2>Konfirmasi Pengembalian</h2><p>Peminjaman berstatus "disetujui" siap dikembalikan.</p></div><div id="toolbarKembali"></div></div>
            <div class="table-wrap">
                <table class="dtable table table-hover align-middle mb-0" id="tblKembali">
                    <thead><tr><th>Barang</th><th>Jml</th><th>Peminjam</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody id="kembaliBody"><tr><td colspan="5" class="empty-row">Memuat data...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <?php if ($canKelola): ?>
    <div class="tab-panel" id="panel-pemeriksaan" <?= $activeTab === 'pemeriksaan' ? '' : 'style="display:none"' ?>>
        <div class="card">
            <div class="card-head"><div><h2>Pemeriksaan Kerusakan & Kehilangan</h2><p>Laporan barang bermasalah dari Staff yang menunggu tindak lanjut Anda.</p></div></div>
            <div class="table-wrap">
                <table class="dtable table table-hover align-middle mb-0" id="tblPeriksa">
                    <thead><tr><th>Tgl Kembali</th><th>Peminjam</th><th>Barang</th><th>Kondisi</th><th>Catatan Staff</th><th>Aksi</th></tr></thead>
                    <tbody id="periksaBody"><tr><td colspan="6" class="empty-row">Memuat data...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-panel" id="panel-riwayat" <?= $activeTab === 'riwayat' ? '' : 'style="display:none"' ?>>
        <div class="card">
            <div class="card-head"><div><h2>Riwayat Perubahan Inventaris</h2><p>Log audit perubahan stok dan kondisi barang.</p></div></div>
            <div class="table-wrap">
                <table class="dtable table table-hover align-middle mb-0" id="tblRiwayat">
                    <thead><tr><th>Tanggal</th><th>Barang</th><th>Jenis</th><th>Perubahan Stok</th><th>Kondisi</th><th>Alasan</th><th>Oleh</th></tr></thead>
                    <tbody id="riwayatBody"><tr><td colspan="7" class="empty-row">Memuat data...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL KEMBALIKAN BARANG -->
    <div class="modal-overlay" id="modalKembali">
        <div class="modal">
            <div class="modal-head"><h3>Konfirmasi Pengembalian</h3><button class="modal-close" onclick="closeModal('modalKembali')"><i data-lucide="x"></i></button></div>
            <div class="modal-body">
                <input type="hidden" id="kPinjamId">
                <div id="kError" style="display:none; color: white; background: #dc3545; padding: 10px; border-radius: 5px; margin-bottom: 15px;"></div>
                <div class="fld">
                    <label>Kondisi Barang Saat Dikembalikan</label>
                    <select id="kKondisi" onchange="document.getElementById('kCatatanWrap').style.display = this.value === 'baik' ? 'none' : 'block'">
                        <option value="baik">Baik</option>
                        <option value="rusak_ringan">Rusak Ringan</option>
                        <option value="rusak_berat">Rusak Berat</option>
                        <option value="hilang">Hilang</option>
                    </select>
                </div>
                <div class="fld" id="kCatatanWrap" style="display:none">
                    <label>Catatan Kerusakan / Kehilangan (Wajib)</label>
                    <textarea id="kCatatan"></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-outline" onclick="closeModal('modalKembali')">Batal</button>
                <button class="btn btn-primary" type="button" onclick="submitKembali(this)">Simpan Pengembalian</button>
            </div>
        </div>
    </div>

    <!-- MODAL PEMERIKSAAN SPV -->
    <div class="modal-overlay" id="modalPeriksa">
        <div class="modal">
            <div class="modal-head"><h3>Tindak Lanjut Laporan SPV</h3><button class="modal-close" onclick="closeModal('modalPeriksa')"><i data-lucide="x"></i></button></div>
            <div class="modal-body">
                <input type="hidden" id="pxKembaliId">
                <p style="margin-bottom:15px; font-size:14px; color:var(--muted)">Pilih keputusan akhir untuk barang ini. Keputusan ini akan memengaruhi total stok inventaris.</p>
                <div class="fld">
                    <label>Tindakan Akhir</label>
                    <select id="pxTindakan">
                        <option value="tetap_digunakan">Dikembalikan ke Stok (Tetap Digunakan)</option>
                        <option value="perlu_perbaikan">Diperbaiki lalu Dikembalikan ke Stok</option>
                        <option value="tidak_layak">Barang Rusak Permanen (Stok Dihapus)</option>
                        <option value="hilang">Dinyatakan Hilang (Stok Dihapus)</option>
                    </select>
                </div>
                <div class="fld">
                    <label>Alasan / Keterangan (Wajib)</label>
                    <textarea id="pxCatatan"></textarea>
                </div>
            </div>
            <div class="modal-foot">
                <button class="btn btn-outline" onclick="closeModal('modalPeriksa')">Batal</button>
                <button class="btn btn-primary" type="button" onclick="submitPeriksa(this)">Simpan Keputusan</button>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH/EDIT BARANG -->
    <?php if ($canTambah || $canKelola): ?>
    <div class="modal-overlay" id="modalBarang">
        <div class="modal">
            <div class="modal-head"><h3 id="modalBarangTitle">Tambah Barang</h3><button class="modal-close" onclick="closeModal('modalBarang')"><i data-lucide="x"></i></button></div>
            <div class="modal-body">
                <input type="hidden" id="fBarangId">
                <div class="form-grid">
                    <div class="fld"><label>Nama Barang</label><input id="fNamaBarang"></div>
                    <div class="fld"><label>Kategori</label><input id="fKategori"></div>
                    <div class="fld"><label>Lokasi</label><input id="fLokasi"></div>
                    <div class="fld"><label>Stok</label><input type="number" id="fStok" min="0"></div>
                    <div class="fld" id="fStatusWrap" style="display:none"><label>Status</label>
                        <select id="fStatus"><option value="tersedia">Tersedia</option><option value="habis">Habis</option><option value="rusak">Rusak</option></select>
                    </div>
                    <div class="fld"><label>Keterangan</label><input id="fKeterangan"></div>
                </div>
            </div>
            <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalBarang')">Batal</button><button class="btn btn-primary" onclick="simpanBarang()">Simpan</button></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL AJUKAN PEMINJAMAN -->
    <div class="modal-overlay" id="modalPinjam">
        <div class="modal">
            <div class="modal-head"><h3>Ajukan Peminjaman Barang</h3><button class="modal-close" onclick="closeModal('modalPinjam')"><i data-lucide="x"></i></button></div>
            <div class="modal-body">
                <div class="fld"><label>Barang</label>
                    <select id="pBarang"><?php foreach ($barangList as $b) echo '<option value="' . $b['id'] . '">' . htmlspecialchars($b['nama_barang']) . ' (stok: ' . $b['stok'] . ')</option>'; ?></select>
                </div>
                <div class="form-grid">
                    <div class="fld"><label>Jumlah</label><input type="number" id="pJumlah" value="1" min="1"></div>
                    <div class="fld"><label>Tanggal Pinjam</label><input type="date" id="pTglPinjam" value="<?= date('Y-m-d') ?>"></div>
                    <div class="fld"><label>Rencana Kembali</label><input type="date" id="pRencana"></div>
                </div>
                <div class="fld"><label>Keperluan</label><textarea id="pKeperluan"></textarea></div>
            </div>
            <div class="modal-foot"><button class="btn btn-outline" onclick="closeModal('modalPinjam')">Batal</button><button class="btn btn-primary" onclick="ajukanPinjam()">Ajukan</button></div>
        </div>
    </div>

    <script>
    const CAN_VERIFIKASI = <?= $canVerifikasi ? 'true' : 'false' ?>;
    const CAN_APPROVE = <?= $canApprove ? 'true' : 'false' ?>;
    const CAN_APPROVE_ATASAN = <?= $canApproveAtasan ? 'true' : 'false' ?>;
    const CAN_KELOLA = <?= $canKelola ? 'true' : 'false' ?>;

    document.querySelectorAll('#invTabs .tab-btn').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#invTabs .tab-btn').forEach(x => x.classList.remove('active'));
        b.classList.add('active');
        document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
        document.getElementById('panel-' + b.dataset.tab).style.display = 'block';
        if (b.dataset.tab === 'pinjam') loadPinjaman();
        if (b.dataset.tab === 'pengembalian') loadPinjaman();
    }));

    document.getElementById('searchBarang')?.addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        document.querySelectorAll('#tblBarang tbody tr').forEach(tr => tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none');
    });

    function editBarang(b) {
        document.getElementById('modalBarangTitle').textContent = 'Edit Barang';
        document.getElementById('fBarangId').value = b.id;
        document.getElementById('fNamaBarang').value = b.nama_barang;
        document.getElementById('fKategori').value = b.kategori || '';
        document.getElementById('fLokasi').value = b.lokasi || '';
        document.getElementById('fStok').value = b.stok;
        document.getElementById('fKeterangan').value = b.keterangan || '';
        document.getElementById('fStatusWrap').style.display = 'block';
        document.getElementById('fStatus').value = b.status;
        openModal('modalBarang');
    }
    async function simpanBarang() {
        const id = document.getElementById('fBarangId').value;
        const payload = {
            nama_barang: document.getElementById('fNamaBarang').value,
            kategori: document.getElementById('fKategori').value,
            lokasi: document.getElementById('fLokasi').value,
            stok: document.getElementById('fStok').value,
            keterangan: document.getElementById('fKeterangan').value,
        };
        const action = id ? 'update_barang' : 'tambah_barang';
        if (id) { payload.id = id; payload.status = document.getElementById('fStatus').value; }
        const r = await apiFetch('inventaris', action, payload);
        if (r.ok) { toast('Data barang tersimpan.', 'success'); closeModal('modalBarang'); loadBarang(); }
        else showModalError('modalBarang', r.msg);
    }

    async function ajukanPinjam() {
        const r = await apiFetch('inventaris', 'ajukan_pinjam', {
            barang_id: document.getElementById('pBarang').value,
            jumlah: document.getElementById('pJumlah').value,
            tanggal_pinjam: document.getElementById('pTglPinjam').value,
            rencana_kembali: document.getElementById('pRencana').value,
            keperluan: document.getElementById('pKeperluan').value,
        });
        if (r.ok) { toast('Pengajuan peminjaman terkirim.', 'success'); closeModal('modalPinjam'); loadPinjaman(); }
        else showModalError('modalPinjam', r.msg);
    }

    const statusBadge = { diajukan: 'b-gray', menunggu_approval_atasan: 'b-yellow', disetujui_atasan: 'b-teal', ditolak_atasan: 'b-red', diverifikasi: 'b-blue', disetujui: 'b-green', ditolak: 'b-red', ditolak_staff: 'b-red', dikembalikan: 'b-purple' };
    const statusLabel = { diajukan: 'Diajukan', menunggu_approval_atasan: 'Menunggu Atasan', disetujui_atasan: 'Disetujui Atasan', ditolak_atasan: 'Ditolak Atasan', diverifikasi: 'Diverifikasi', disetujui: 'Disetujui', ditolak: 'Ditolak', ditolak_staff: 'Ditolak Staff', dikembalikan: 'Dikembalikan' };

    function bukaFormPinjam() {
        if (document.getElementById('pBarang').options.length > 0) {
            document.getElementById('pBarang').selectedIndex = 0;
        }
        document.getElementById('pJumlah').value = 1;
        document.getElementById('pTglPinjam').value = new Date().toISOString().split('T')[0];
        document.getElementById('pRencana').value = '';
        document.getElementById('pKeperluan').value = '';
        openModal('modalPinjam');
    }

    async function loadPinjaman() {
        const r = await apiFetch('inventaris', 'list_peminjaman', {}, 'GET');
        if (!r.ok) return;
        
        const menunggu = r.data.filter(p => ['diajukan','diverifikasi'].includes(p.status)).length;
        const aktif = r.data.filter(p => p.status === 'disetujui').length;
        const statCards = document.querySelectorAll('.stat-grid h3');
        if (statCards.length >= 4) {
            statCards[2].textContent = menunggu;
            statCards[3].textContent = aktif;
        }

        if (document.getElementById('pinjamBody')) updateDOM('pinjamBody', r.data.length ? r.data.map(p => {
            let aksi = '';
            if (p.status === 'menunggu_approval_atasan' && CAN_APPROVE_ATASAN) aksi = btnPairAtasan('approve_atasan', p.id);
            else if ((p.status === 'diajukan' || p.status === 'disetujui_atasan') && CAN_VERIFIKASI) aksi = btnPair('verifikasi_pinjam', p.id);
            else if (p.status === 'diverifikasi' && CAN_APPROVE) aksi = btnPair('approve_pinjam', p.id);
            else if (p.status.includes('ditolak')) aksi = '<i data-lucide="x" style="color:var(--danger);width:20px;height:20px;margin-top:4px"></i>';
            else if (['disetujui_atasan', 'diverifikasi', 'disetujui', 'dikembalikan'].includes(p.status)) aksi = '<i data-lucide="check" style="color:var(--primary);width:20px;height:20px;margin-top:4px"></i>';
            else aksi = '<span style="color:#9ca3af;font-size:12px;margin-top:4px">—</span>';
            return `<tr><td>${esc(p.nama_barang)}</td><td>${p.jumlah}</td><td><div>${esc(p.peminjam_nama)}</div><div style="font-size:12px;color:var(--muted)">${esc(p.peminjam_jabatan) || '-'}</div></td><td>${p.tanggal_pinjam}</td><td>${p.rencana_kembali||'-'}</td><td>${esc(p.keperluan)||'-'}</td>
            <td><span class="badge ${statusBadge[p.status]}">${statusLabel[p.status]}</span></td><td class="actions-cell">${aksi}</td></tr>`;
        }).join('') : '<tr><td colspan="8" class="empty-row">Belum ada pengajuan peminjaman.</td></tr>');

        if (document.getElementById('kembaliBody')) {
            const siap = r.data.filter(p => p.status === 'disetujui');
                updateDOM('kembaliBody', siap.length ? siap.map(p => `<tr><td>${esc(p.nama_barang)}</td><td>${p.jumlah}</td><td><div>${esc(p.peminjam_nama)}</div><div style="font-size:12px;color:var(--muted)">${esc(p.peminjam_jabatan) || '-'}</div></td>
                <td><span class="badge b-green">Dipinjam</span></td>
                <td><button class="btn btn-outline btn-sm" onclick="kembalikan(${p.id})"><i data-lucide="corner-down-left"></i> Konfirmasi Kembali</button></td></tr>`).join('')
                : '<tr><td colspan="5" class="empty-row">Tidak ada barang yang sedang dipinjam.</td></tr>');
        }
        
        if (CAN_KELOLA) {
            const rPeriksa = await apiFetch('inventaris', 'list_pemeriksaan', {}, 'GET');
            if (rPeriksa.ok && document.getElementById('periksaBody')) {
                updateDOM('periksaBody', rPeriksa.data.length ? rPeriksa.data.map(p => `<tr>
                    <td>${p.tanggal_kembali}</td><td>${esc(p.peminjam_nama)}</td><td>${esc(p.nama_barang)} (${p.jumlah})</td>
                    <td><span class="badge b-red">${p.kondisi_barang.replace('_', ' ').toUpperCase()}</span></td>
                    <td>${esc(p.catatan)}</td>
                    <td class="actions-cell">
                        ${p.status_pemeriksaan === 'menunggu_spv' 
                            ? `<button class="btn btn-primary btn-sm" onclick="bukaPeriksa(${p.id})">Tindak Lanjut</button>`
                            : `<span class="badge b-green">Selesai (${(p.tindakan_spv||'').replace(/_/g, ' ')})</span>`}
                    </td>
                </tr>`).join('') : '<tr><td colspan="6" class="empty-row">Belum ada laporan kerusakan/kehilangan.</td></tr>');
            }
            
            const rRiwayat = await apiFetch('inventaris', 'list_riwayat', {}, 'GET');
            if (rRiwayat.ok && document.getElementById('riwayatBody')) {
                updateDOM('riwayatBody', rRiwayat.data.length ? rRiwayat.data.map(r => `<tr>
                    <td>${r.created_at}</td><td>${esc(r.nama_barang)}</td><td>${r.jenis_perubahan.replace(/_/g, ' ').toUpperCase()}</td>
                    <td>${r.jumlah_sebelum} &rarr; ${r.jumlah_sesudah}</td>
                    <td>${r.kondisi_sebelum.replace('_',' ')} &rarr; ${r.kondisi_sesudah.replace('_',' ')}</td>
                    <td>${esc(r.alasan)}</td><td>${esc(r.nama_user)}</td>
                </tr>`).join('') : '<tr><td colspan="7" class="empty-row">Belum ada riwayat perubahan stok.</td></tr>');
            }
        }
    }
    function btnPair(action, id) {
        return `<button class="btn btn-primary btn-sm" onclick="prosesPinjam('${action}',${id},1)"><i data-lucide="check"></i></button>
                <button class="btn btn-danger btn-sm" onclick="prosesPinjam('${action}',${id},0)"><i data-lucide="x"></i></button>`;
    }
    function btnPairAtasan(action, id) {
        return `<button class="btn btn-primary btn-sm" onclick="prosesPinjamAtasan('${action}',${id},1)">Setuju</button>
                <button class="btn btn-danger btn-sm" onclick="prosesPinjamAtasan('${action}',${id},0)">Tolak</button>`;
    }
    async function prosesPinjam(action, id, setuju) {
        let catatan = '';
        if (!setuju) {
            catatan = await customPrompt('Masukkan alasan penolakan:');
            if (catatan === null) return;
        }
        const r = await apiFetch('inventaris', action, { id, setuju, catatan });
        if (r.ok) { 
            toast('Berhasil diproses.', 'success'); 
            loadPinjaman(); 
            loadBarang(); 
        } else toast(r.msg, 'error');
    }
    async function prosesPinjamAtasan(action, id, setuju) {
        let catatan = '';
        if (!setuju) {
            catatan = await customPrompt('Masukkan alasan penolakan:');
            if (catatan === null) return; // cancelled
        }
        const r = await apiFetch('inventaris', action, { id, setuju, catatan_atasan: catatan });
        if (r.ok) { 
            toast('Berhasil diproses.', 'success'); 
            loadPinjaman(); 
            loadBarang(); 
        } else toast(r.msg, 'error');
    }
    function kembalikan(id) {
        document.getElementById('kPinjamId').value = id;
        document.getElementById('kKondisi').value = 'baik';
        document.getElementById('kCatatan').value = '';
        document.getElementById('kCatatanWrap').style.display = 'none';
        openModal('modalKembali');
    }
    async function submitKembali(btn) {
        if (btn) { btn.disabled = true; btn.innerHTML = 'Menyimpan...'; }
        const errDiv = document.getElementById('kError');
        if (errDiv) errDiv.style.display = 'none';

        try {
            const kondisi = document.getElementById('kKondisi').value;
            const catatan = document.getElementById('kCatatan').value;
            if (kondisi !== 'baik' && !catatan.trim()) {
                if (btn) { btn.disabled = false; btn.innerHTML = 'Simpan Pengembalian'; }
                if (errDiv) { errDiv.innerText = 'Catatan wajib diisi jika barang bermasalah.'; errDiv.style.display = 'block'; }
                return;
            }
            
            const r = await apiFetch('inventaris', 'kembalikan', { 
                id: document.getElementById('kPinjamId').value, 
                kondisi_barang: kondisi,
                catatan: catatan
            });
            
            if (r.ok) { 
                toast('Pengembalian dikonfirmasi.', 'success'); 
                closeModal('modalKembali'); 
                loadPinjaman(); 
                loadBarang();
            } else {
                showModalError('modalKembali', r.msg);
            }
        } catch (e) {
            if (errDiv) { errDiv.innerText = 'Terjadi kesalahan sistem: ' + e.message; errDiv.style.display = 'block'; }
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = 'Simpan Pengembalian'; }
        }
    }
    function bukaPeriksa(id) {
        document.getElementById('pxKembaliId').value = id;
        document.getElementById('pxTindakan').value = 'tetap_digunakan';
        document.getElementById('pxCatatan').value = '';
        openModal('modalPeriksa');
    }
    async function submitPeriksa(btn) {
        if (btn) { btn.disabled = true; btn.innerHTML = 'Menyimpan...'; }
        try {
            const catatan = document.getElementById('pxCatatan').value;
            if (!catatan.trim()) {
                if (btn) { btn.disabled = false; btn.innerHTML = 'Simpan Keputusan'; }
                return toast('Alasan wajib diisi.', 'error');
            }
            const r = await apiFetch('inventaris', 'periksa_pengembalian', {
                id: document.getElementById('pxKembaliId').value,
                tindakan: document.getElementById('pxTindakan').value,
                catatan_spv: catatan
            });
            if (r.ok) { 
                toast('Keputusan SPV diterapkan.', 'success'); 
                closeModal('modalPeriksa'); 
                loadPinjaman(); 
                loadInventarisData(); 
            } else {
                showModalError('modalPeriksa', r.msg);
            }
        } catch (e) {
            alert('Terjadi kesalahan sistem: ' + e.message);
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = 'Simpan Keputusan'; }
        }
    }
    async function loadInventarisData() {
        loadBarang();
    }

    async function loadBarang() {
        const r = await apiFetch('inventaris', 'list_barang', {}, 'GET');
        if (!r.ok) return;
        const tbody = document.querySelector('#tblBarang tbody');
        if (tbody) {
            tbody.innerHTML = r.data.length ? r.data.map(b => {
                const statusBadge = { 'tersedia': 'b-green', 'habis': 'b-yellow', 'rusak': 'b-red' }[b.status] || 'b-gray';
                const statusUc = b.status.charAt(0).toUpperCase() + b.status.slice(1);
                let aksi = '';
                if (CAN_KELOLA) {
                    const bStr = JSON.stringify(b).replace(/'/g, "&apos;").replace(/"/g, "&quot;");
                    aksi = `<td class="actions-cell"><button class="btn btn-outline btn-sm btn-icon" onclick="editBarang(${bStr})"><i data-lucide="pencil"></i></button></td>`;
                }
                return `<tr>
                    <td><b>${esc(b.kode_barang)}</b></td>
                    <td>${esc(b.nama_barang)}</td>
                    <td>${esc(b.kategori) || ''}</td>
                    <td>${esc(b.lokasi) || ''}</td>
                    <td>${b.stok}</td>
                    <td><span class="badge ${statusBadge}">${statusUc}</span></td>
                    ${CAN_KELOLA ? aksi : ''}
                </tr>`;
            }).join('') : '<tr><td colspan="7" class="empty-row">Belum ada data barang.</td></tr>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            
            const q = document.getElementById('searchBarang')?.value.toLowerCase();
            if (q) document.querySelectorAll('#tblBarang tbody tr').forEach(tr => tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none');
        }
        
        const pBarang = document.getElementById('pBarang');
        if (pBarang) {
            const val = pBarang.value;
            pBarang.innerHTML = r.data.map(b => `<option value="${b.id}">${esc(b.nama_barang)} (stok: ${b.stok})</option>`).join('');
            if (val) pBarang.value = val;
        }
        
        const statCards = document.querySelectorAll('.stat-grid h3');
        if(statCards.length >= 4) {
            statCards[0].textContent = r.data.length;
            statCards[1].textContent = r.data.reduce((acc, curr) => acc + parseInt(curr.stok), 0);
        }
    }
    loadPinjaman();
    loadBarang();
    setInterval(() => {
        loadPinjaman();
        loadBarang();
    }, 10000); // Polling real-time data
    <?php if ($canKelola || $canVerifikasi || $canMonitoring): ?>
    renderExportToolbar('#toolbarBarang', 'tblBarang', 'data_barang', 'Daftar Barang Inventaris');
    renderExportToolbar('#toolbarPinjam', 'tblPinjam', 'peminjaman', 'Peminjaman & Persetujuan');
    renderExportToolbar('#toolbarKembali', 'tblKembali', 'pengembalian', 'Konfirmasi Pengembalian');
    <?php endif; ?>
    </script>
