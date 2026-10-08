/* AL-SYUKROSMART OPS — export.js
   Fitur ekspor Excel/PDF & cetak, dipakai oleh semua modul.
   Tidak mengubah alur/logic aplikasi — murni fungsi tambahan di atas tabel yang sudah ada. */
'use strict';

function _exportMeta() {
    const nameEl = document.querySelector('meta[name="app-title"]');
    return {
        appName: nameEl ? nameEl.content : 'AL-SYUKROSMART OPS',
        dateStr: new Date().toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }),
    };
}

function _tableTitle(tableEl, fallback) {
    const card = tableEl.closest('.card');
    const h2 = card ? card.querySelector('.card-head h2') : null;
    return (h2 ? h2.textContent.trim() : null) || fallback || 'Data';
}

/* ───────────────────────── CETAK (PRINT) ───────────────────────── */
function printTable(tableId, judul) {
    const table = document.getElementById(tableId);
    if (!table) { toast('Tabel tidak ditemukan untuk dicetak.', 'error'); return; }
    const title = judul || _tableTitle(table);
    const meta = _exportMeta();
    const win = window.open('', '_blank', 'width=1000,height=700');
    if (!win) { toast('Popup diblokir browser. Izinkan popup untuk mencetak.', 'warning'); return; }
    win.document.write(`<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
    <title>${title} — ${meta.appName}</title>
    <link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
    <style>
        body{font-family:'Inter',ui-sans-serif,sans-serif;padding:28px;color:#1c2a22}
        .print-head{display:flex;align-items:center;gap:14px;border-bottom:3px solid #145c3f;padding-bottom:14px;margin-bottom:18px}
        .print-head h1{font-size:18px;margin:0;color:#0c3f2b}
        .print-head p{margin:2px 0 0;font-size:12px;color:#6f7669}
        table{width:100%;font-size:12.5px}
        thead th{background:#e7f1ec !important;color:#0c3f2b}
        .btn-outline,.btn-icon,.actions-cell,.btn{display:none !important}
        @media print{.no-print{display:none !important}}
    </style></head><body>
    <div class="print-head">
        <div><h1>${meta.appName}</h1><p>${title} — dicetak pada ${meta.dateStr}</p></div>
    </div>
    <table class="table table-bordered table-sm">${table.innerHTML}</table>
    <script>window.onload = () => { window.print(); }<\/script>
    </body></html>`);
    win.document.close();
}

/* ───────────────────────── EKSPOR EXCEL (.xlsx) ───────────────────────── */
let _xlsxPromise = null;
function _ensureSheetJS(cb) {
    if (window.XLSX) return cb();
    if (!_xlsxPromise) {
        _xlsxPromise = new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'assets/vendor/xlsx.full.min.js';
            s.onload = resolve;
            s.onerror = () => { _xlsxPromise = null; reject(new Error('Gagal memuat pustaka Excel. Periksa koneksi internet.')); };
            document.head.appendChild(s);
        });
    }
    _xlsxPromise.then(cb).catch(e => toast(e.message, 'error'));
}
function exportExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) { toast('Tabel tidak ditemukan untuk diekspor.', 'error'); return; }
    _ensureSheetJS(() => {
        try {
            const clone = table.cloneNode(true);
            clone.style.position = 'absolute';
            clone.style.left = '-9999px';
            clone.style.display = 'table';
            clone.classList.remove('d-none');
            clone.querySelectorAll('.actions-cell, .no-print').forEach(el => el.remove());
            document.body.appendChild(clone);
            
            const wb = XLSX.utils.table_to_book(clone, { raw: false });
            XLSX.writeFile(wb, (filename || tableId) + '.xlsx');
            clone.remove();
            toast('Data berhasil diekspor ke Excel.', 'success');
        } catch (e) { toast('Gagal mengekspor Excel: ' + e.message, 'error'); }
    });
}

/* ───────────────────────── EKSPOR PDF ───────────────────────── */
let _pdfPromise = null;
function _ensureJsPDF(cb) {
    if (window.jspdf && window.jspdf.jsPDF) return cb();
    if (!_pdfPromise) {
        _pdfPromise = new Promise((resolve, reject) => {
            const s1 = document.createElement('script');
            s1.src = 'assets/vendor/jspdf.umd.min.js';
            s1.onload = () => {
                const s2 = document.createElement('script');
                s2.src = 'assets/vendor/jspdf.plugin.autotable.min.js';
                s2.onload = resolve;
                s2.onerror = () => { _pdfPromise = null; reject(new Error('Gagal memuat pustaka PDF (autotable).')); };
                document.head.appendChild(s2);
            };
            s1.onerror = () => { _pdfPromise = null; reject(new Error('Gagal memuat pustaka PDF.')); };
            document.head.appendChild(s1);
        });
    }
    _pdfPromise.then(cb).catch(e => toast(e.message, 'error'));
}
function exportPDF(tableId, filename, judul) {
    const table = document.getElementById(tableId);
    if (!table) { toast('Tabel tidak ditemukan untuk diekspor.', 'error'); return; }
    const title = judul || _tableTitle(table);
    const meta = _exportMeta();
    _ensureJsPDF(() => {
        try {
            const clone = table.cloneNode(true);
            clone.style.display = 'table';
            clone.classList.remove('d-none');
            clone.querySelectorAll('.actions-cell, .no-print').forEach(el => el.remove());
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape' });
            doc.setFontSize(13);
            doc.setTextColor(12, 63, 43);
            doc.text(meta.appName, 14, 14);
            doc.setFontSize(10);
            doc.setTextColor(90, 90, 90);
            doc.text(title + ' — dicetak ' + meta.dateStr, 14, 20);
            doc.autoTable({
                html: clone,
                startY: 26,
                styles: { fontSize: 8, cellPadding: 2.5 },
                headStyles: { fillColor: [20, 92, 63], textColor: 255 },
                margin: { left: 14, right: 14 },
            });
            doc.save((filename || tableId) + '.pdf');
            toast('Data berhasil diekspor ke PDF.', 'success');
        } catch (e) { toast('Gagal mengekspor PDF: ' + e.message, 'error'); }
    });
}

/* ───────────────────────── RENDER TOOLBAR TOMBOL ─────────────────────────
   Dipanggil dari tiap halaman modul: renderExportToolbar('#elId', 'tblXxx', 'Nama File', 'Judul Laporan') */
function renderExportToolbar(mountSelector, tableId, filename, judul) {
    const mount = document.querySelector(mountSelector);
    if (!mount) return;
    mount.innerHTML = `
        <div class="btn-group export-toolbar no-print" role="group" aria-label="Ekspor & cetak">
            <button type="button" class="btn btn-outline btn-sm" onclick="printTable('${tableId}', '${(judul || '').replace(/'/g, "\\'")}')" title="Cetak">
                <i data-lucide="printer"></i><span class="d-none d-lg-inline"> Cetak</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="exportExcel('${tableId}', '${filename}')" title="Ekspor Excel">
                <i data-lucide="file-spreadsheet"></i><span class="d-none d-lg-inline"> Excel</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="exportPDF('${tableId}', '${filename}', '${(judul || '').replace(/'/g, "\\'")}')" title="Ekspor PDF">
                <i data-lucide="file-text"></i><span class="d-none d-lg-inline"> PDF</span>
            </button>
        </div>`;
    if (window.lucide) lucide.createIcons({ root: mount });
}
