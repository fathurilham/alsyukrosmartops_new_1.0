<?php
$modules = getRoleModules($role);
$canKelola = hasAccess($role, 'laporan', 'kelola');

// Label status yang lebih ramah (sama dengan yang digunakan modul lain)
$STATUS_LABELS = [
    // inventaris / maintenance
    'menunggu_approval_atasan' => 'Menunggu Persetujuan Atasan',
    'diajukan'                 => 'Diajukan',
    'disetujui_atasan'         => 'Disetujui Atasan',
    'diverifikasi'             => 'Diverifikasi',
    'disetujui'                => 'Disetujui',
    'ditolak'                  => 'Ditolak',
    'ditolak_atasan'           => 'Ditolak Atasan',
    'ditolak_staff'            => 'Ditolak Staff',
    'dikembalikan'             => 'Dikembalikan',
    'dibatalkan'               => 'Dibatalkan',
    'selesai'                  => 'Selesai',
    // event
    'berlangsung'              => 'Berlangsung',
    'batal'                    => 'Dibatalkan',
    // kegiatan karyawan
    'ditugaskan'               => 'Ditugaskan',
    'dikerjakan'               => 'Sedang Dikerjakan',
    // arsip
    'umum'                     => 'Umum',
    'terbatas'                 => 'Terbatas',
    'rahasia'                  => 'Rahasia',
];

$STATUS_BADGE = [
    'menunggu_approval_atasan' => '#a97c22',
    'diajukan'                 => '#2f6690',
    'disetujui_atasan'         => '#3b7a57',
    'diverifikasi'             => '#6a4fa0',
    'disetujui'                => '#145c3f',
    'ditolak'                  => '#c0392b',
    'ditolak_atasan'           => '#c0392b',
    'ditolak_staff'            => '#c0392b',
    'dikembalikan'             => '#6a4fa0',
    'dibatalkan'               => '#888',
    'selesai'                  => '#145c3f',
    'berlangsung'              => '#1a7a4a',
    'batal'                    => '#888',
    'ditugaskan'               => '#2f6690',
    'dikerjakan'               => '#a97c22',
    'umum'                     => '#145c3f',
    'terbatas'                 => '#a97c22',
    'rahasia'                  => '#c0392b',
];
?>

<div class="card">
    <div class="card-head" style="flex-wrap:wrap;gap:12px">
        <div>
            <h2>Dashboard Analitik &amp; Laporan Operasional</h2>
            <p>Rekap lintas modul sesuai hak akses peran Anda. Gunakan filter untuk mempersempit periode dan unit.</p>
        </div>
        <div class="hgroup no-print" style="flex-wrap:wrap;gap:8px;align-items:center">
            <button type="button" class="btn btn-outline btn-sm" onclick="cetakLaporan()">
                <i data-lucide="printer"></i> Cetak
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="eksporExcelLaporan()">
                <i data-lucide="file-spreadsheet"></i> Excel
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="eksporPdfLaporan()">
                <i data-lucide="file-text"></i> PDF
            </button>
        </div>
    </div>

    <!-- Panel Filter -->
    <div class="filter-row no-print" id="laporanFilterPanel" style="padding:12px 0 4px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <div>
            <label class="form-label" for="laporanTglMulai" style="font-size:12px;margin-bottom:3px;display:block;color:var(--muted)">Dari Tanggal</label>
            <input type="date" id="laporanTglMulai" class="form-control form-control-sm" style="font-size:13px" onchange="loadLaporan()">
        </div>
        <div>
            <label class="form-label" for="laporanTglSampai" style="font-size:12px;margin-bottom:3px;display:block;color:var(--muted)">Sampai Tanggal</label>
            <input type="date" id="laporanTglSampai" class="form-control form-control-sm" style="font-size:13px" onchange="loadLaporan()">
        </div>
        <div>
            <label class="form-label" for="laporanUnit" style="font-size:12px;margin-bottom:3px;display:block;color:var(--muted)">Unit / Divisi</label>
            <select id="laporanUnit" class="form-select form-select-sm" style="font-size:13px;min-width:180px" onchange="loadLaporan()">
                <option value="">— Semua Unit —</option>
            </select>
        </div>
        <div>
            <button type="button" class="btn btn-outline btn-sm" onclick="resetFilterLaporan()" title="Reset Filter">
                <i data-lucide="x-circle"></i> Reset
            </button>
        </div>
        <div id="laporanPeriodBadge" style="display:none;align-items:center;gap:6px;font-size:12px;color:var(--muted);padding-bottom:4px">
            <i data-lucide="filter" style="width:13px;height:13px"></i>
            <span id="laporanPeriodText"></span>
        </div>
    </div>
</div>

<!-- Area rekap grid -->
<div class="stat-grid" id="laporanGrid">
    <div class="card" style="margin-bottom:0;grid-column:1/-1">
        <div style="display:flex;align-items:center;gap:10px;padding:24px;color:var(--muted)">
            <i data-lucide="loader-2" style="animation:spin 1s linear infinite;width:18px;height:18px"></i>
            <span>Memuat data laporan...</span>
        </div>
    </div>
</div>

<!-- Tabel tersembunyi sumber ekspor (diisi JS, tidak dirender langsung) -->
<table id="tblLaporanRekap" class="d-none" style="display:none">
    <thead>
        <tr>
            <th>Modul</th>
            <th>Status</th>
            <th>Jumlah</th>
        </tr>
    </thead>
    <tbody id="tblLaporanRekapBody"></tbody>
</table>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
#laporanGrid .modul-card { border-radius:10px;overflow:hidden }
#laporanGrid .modul-card .card-head { padding-bottom:8px }
.laporan-row { display:flex;justify-content:space-between;align-items:center;padding:7px 0;font-size:13px;border-bottom:1px solid var(--border) }
.laporan-row:last-child { border-bottom:none }
.laporan-badge { display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;flex-shrink:0 }
.laporan-empty { color:var(--muted);font-size:13px;padding:8px 0 }
</style>

<script>
/* ═══════════════════════════════════════════════════════
   LAPORAN & MONITORING — Filter Periode + Unit (A4)
   ═══════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const STATUS_LABELS = <?= json_encode($STATUS_LABELS, JSON_UNESCAPED_UNICODE) ?>;
    const STATUS_BADGE  = <?= json_encode($STATUS_BADGE,  JSON_UNESCAPED_UNICODE) ?>;

    function labelStatus(s) {
        return STATUS_LABELS[s] || s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }
    function badgeColor(s) {
        return STATUS_BADGE[s] || '#888';
    }

    // ── Muat daftar unit ────────────────────────────────────────────────────
    async function loadUnits() {
        const sel = document.getElementById('laporanUnit');
        if (!sel) return;
        try {
            const r = await apiFetch('laporan', 'list_units', {}, 'GET');
            if (!r.ok || !r.data?.units) return;
            r.data.units.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.nama_unit;
                sel.appendChild(opt);
            });
        } catch (_) {}
    }

    // ── Render rekap grid ───────────────────────────────────────────────────
    function renderGrid(rekap, tglMulai, tglSampai, unitId, unitLabel) {
        const grid = document.getElementById('laporanGrid');
        const tbodyExp = document.getElementById('tblLaporanRekapBody');
        if (!grid || !tbodyExp) return;

        // Update label periode
        const badge  = document.getElementById('laporanPeriodBadge');
        const pText  = document.getElementById('laporanPeriodText');
        if (tglMulai || tglSampai || unitId) {
            let parts = [];
            if (tglMulai && tglSampai)  parts.push(`${fmtDate(tglMulai)} – ${fmtDate(tglSampai)}`);
            else if (tglMulai)          parts.push(`mulai ${fmtDate(tglMulai)}`);
            else if (tglSampai)         parts.push(`sampai ${fmtDate(tglSampai)}`);
            if (unitLabel)              parts.push(unitLabel);
            pText.textContent = parts.join(' · ');
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }

        // Jika rekap kosong (semua modul)
        const modulKeys = Object.keys(rekap);
        if (!modulKeys.length) {
            grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
                <i data-lucide="bar-chart-2"></i>
                <h3>Belum ada data laporan</h3>
                <p>Tidak ada modul yang dapat diakses atau data tidak tersedia dengan filter yang dipilih.</p>
            </div>`;
            if (window.lucide) lucide.createIcons({ root: grid });
            tbodyExp.innerHTML = '';
            return;
        }

        // Susun grid kartu per modul
        grid.innerHTML = '';
        tbodyExp.innerHTML = '';

        modulKeys.forEach(modul => {
            const rows = rekap[modul] || [];

            const card = document.createElement('div');
            card.className = 'card modul-card';
            card.style.marginBottom = '0';

            // Header kartu
            const totalN = rows.reduce((s, r) => s + parseInt(r.n, 10), 0);
            card.innerHTML = `
                <div class="card-head">
                    <div>
                        <h2>${esc(modul)}</h2>
                        <p style="font-size:12px;color:var(--muted)">${totalN} data</p>
                    </div>
                </div>`;

            const body = document.createElement('div');
            body.style.padding = '0 16px 12px';

            if (!rows.length) {
                body.innerHTML = `<p class="laporan-empty">Belum ada data${tglMulai || tglSampai ? ' pada periode ini' : ''}.</p>`;
            } else {
                rows.forEach(r => {
                    const statusLabel = labelStatus(r.status);
                    const color       = badgeColor(r.status);
                    const div = document.createElement('div');
                    div.className = 'laporan-row';
                    div.innerHTML = `
                        <span>
                            <span class="laporan-badge" style="background:${color}"></span>
                            ${esc(statusLabel)}
                        </span>
                        <b>${esc(String(r.n))}</b>`;
                    body.appendChild(div);

                    // Isi tabel ekspor tersembunyi
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td>${esc(modul)}</td><td>${esc(statusLabel)}</td><td>${esc(String(r.n))}</td>`;
                    tbodyExp.appendChild(tr);
                });
            }

            card.appendChild(body);
            grid.appendChild(card);
        });

        if (window.lucide) lucide.createIcons({ root: grid });
    }

    // ── Format tanggal ──────────────────────────────────────────────────────
    function fmtDate(ymd) {
        if (!ymd) return '';
        const [y, m, d] = ymd.split('-');
        const bulan = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        return `${parseInt(d, 10)} ${bulan[parseInt(m, 10)] || m} ${y}`;
    }

    // ── Muat rekap dengan filter aktif ──────────────────────────────────────
    window.loadLaporan = async function () {
        const tglMulai  = document.getElementById('laporanTglMulai')?.value  || '';
        const tglSampai = document.getElementById('laporanTglSampai')?.value || '';
        const unitSel   = document.getElementById('laporanUnit');
        const unitId    = unitSel?.value || '';
        const unitLabel = unitId ? (unitSel.options[unitSel.selectedIndex]?.text || '') : '';

        // Tampilkan loading
        const grid = document.getElementById('laporanGrid');
        if (grid) {
            grid.innerHTML = `<div class="card" style="margin-bottom:0;grid-column:1/-1">
                <div style="display:flex;align-items:center;gap:10px;padding:24px;color:var(--muted)">
                    <i data-lucide="loader-2" style="animation:spin 1s linear infinite;width:18px;height:18px"></i>
                    <span>Memuat data laporan...</span>
                </div>
            </div>`;
            if (window.lucide) lucide.createIcons({ root: grid });
        }

        const params = { action: 'rekap' };
        if (tglMulai)  params.tgl_mulai  = tglMulai;
        if (tglSampai) params.tgl_sampai = tglSampai;
        if (unitId)    params.unit_id    = unitId;

        try {
            const r = await apiFetch('laporan', 'rekap', params, 'GET');
            if (!r.ok) {
                toast(r.msg || 'Gagal memuat laporan.', 'error');
                if (grid) grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
                    <i data-lucide="alert-circle"></i>
                    <h3>Gagal memuat data</h3>
                    <p>${esc(r.msg || 'Terjadi kesalahan. Silakan coba lagi.')}</p>
                </div>`;
                return;
            }
            renderGrid(
                r.data.rekap     || {},
                r.data.tgl_mulai  || '',
                r.data.tgl_sampai || '',
                r.data.unit_id    || 0,
                unitLabel
            );
        } catch (e) {
            toast('Gagal memuat laporan: ' + e.message, 'error');
        }
    };

    // ── Reset filter ────────────────────────────────────────────────────────
    window.resetFilterLaporan = function () {
        const m = document.getElementById('laporanTglMulai');
        const s = document.getElementById('laporanTglSampai');
        const u = document.getElementById('laporanUnit');
        if (m) m.value = '';
        if (s) s.value = '';
        if (u) u.value = '';
        loadLaporan();
    };

    // ── Ekspor — isi tblLaporanRekap dulu (dari JS), baru panggil export.js ─
    function ensureRekapTable(cb) {
        const tbody = document.getElementById('tblLaporanRekapBody');
        if (!tbody || !tbody.children.length) {
            toast('Tidak ada data untuk diekspor. Pastikan rekap sudah dimuat.', 'warning');
            return;
        }
        // Pastikan tabel ekspor terlihat sementara untuk XLSX/jsPDF
        const tbl = document.getElementById('tblLaporanRekap');
        if (tbl) tbl.style.display = 'table';
        cb();
        // Sembunyikan kembali setelah ekspor dimulai (timeout 0 agar tidak visible di UI)
        setTimeout(() => { if (tbl) tbl.style.display = 'none'; }, 0);
    }

    function getExportJudul() {
        const tglM = document.getElementById('laporanTglMulai')?.value || '';
        const tglS = document.getElementById('laporanTglSampai')?.value || '';
        const unitSel = document.getElementById('laporanUnit');
        const unitLabel = unitSel?.value ? (unitSel.options[unitSel.selectedIndex]?.text || '') : '';

        let judul = 'Laporan Operasional';
        const parts = [];
        if (tglM && tglS)  parts.push(`${fmtDate(tglM)} – ${fmtDate(tglS)}`);
        else if (tglM)     parts.push(`mulai ${fmtDate(tglM)}`);
        else if (tglS)     parts.push(`sampai ${fmtDate(tglS)}`);
        if (unitLabel)     parts.push(unitLabel);
        if (parts.length)  judul += ' (' + parts.join(', ') + ')';
        return judul;
    }

    function getExportFilename() {
        const tglM = document.getElementById('laporanTglMulai')?.value || '';
        const tglS = document.getElementById('laporanTglSampai')?.value || '';
        let name = 'laporan_operasional';
        if (tglM) name += '_' + tglM;
        if (tglS) name += '_sd_' + tglS;
        return name;
    }

    window.cetakLaporan = function () {
        ensureRekapTable(() => printTable('tblLaporanRekap', getExportJudul()));
    };
    window.eksporExcelLaporan = function () {
        ensureRekapTable(() => exportExcel('tblLaporanRekap', getExportFilename(), getExportJudul()));
    };
    window.eksporPdfLaporan = function () {
        ensureRekapTable(() => exportPDF('tblLaporanRekap', getExportFilename(), getExportJudul()));
    };

    // ── Init ────────────────────────────────────────────────────────────────
    (async function init() {
        await loadUnits();
        await loadLaporan();
        if (window.lucide) lucide.createIcons();
    })();
})();
</script>
