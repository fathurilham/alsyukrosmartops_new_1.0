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

function _prepareTableClone(table) {
    const clone = table.cloneNode(true);
    clone.classList.remove('d-none');

    // Identifikasi indeks kolom yang merupakan aksi / kontrol / no-print
    const excludeIndices = new Set();

    // 1. Periksa header kolom di thead (atau tr pertama jika tidak ada thead)
    const headerRows = clone.querySelectorAll('thead tr');
    const firstRow = headerRows.length ? headerRows[0] : clone.querySelector('tr');
    if (firstRow) {
        Array.from(firstRow.children).forEach((cell, idx) => {
            const txt = (cell.textContent || '').trim().toLowerCase();
            if (
                txt === 'aksi' ||
                txt === 'action' ||
                txt === 'tindakan' ||
                cell.classList.contains('actions-cell') ||
                cell.classList.contains('no-print')
            ) {
                excludeIndices.add(idx);
            }
        });
    }

    // 2. Periksa juga baris data di tbody jika ada sel bertanda .actions-cell atau .no-print
    clone.querySelectorAll('tbody tr').forEach(row => {
        Array.from(row.children).forEach((cell, idx) => {
            if ((cell.classList.contains('actions-cell') || cell.classList.contains('no-print')) && !cell.hasAttribute('colspan')) {
                excludeIndices.add(idx);
            }
        });
    });

    // 3. Hapus kolom-kolom yang di-exclude dari seluruh baris (mulai dari indeks terbesar)
    const sortedIndices = Array.from(excludeIndices).sort((a, b) => b - a);
    clone.querySelectorAll('tr').forEach(row => {
        const cells = Array.from(row.children);
        if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
            const currentColspan = parseInt(cells[0].getAttribute('colspan'), 10);
            if (!isNaN(currentColspan) && currentColspan > sortedIndices.length) {
                cells[0].setAttribute('colspan', currentColspan - sortedIndices.length);
            }
            return;
        }

        sortedIndices.forEach(idx => {
            if (row.children[idx]) {
                row.children[idx].remove();
            }
        });
    });

    // 4. Bersihkan sisa elemen tombol atau elemen bertanda no-print / actions-cell
    clone.querySelectorAll('.actions-cell, .no-print, button, .btn').forEach(el => el.remove());

    return clone;
}

/* ───────────────────────── CETAK (PRINT) ───────────────────────── */
function printTable(tableId, judul) {
    const table = document.getElementById(tableId);
    if (!table) { toast('Tabel tidak ditemukan untuk dicetak.', 'error'); return; }
    const title = judul || _tableTitle(table);
    const meta = _exportMeta();
    const win = window.open('', '_blank', 'width=1000,height=700');
    if (!win) { toast('Popup diblokir browser. Izinkan popup untuk mencetak.', 'warning'); return; }
    const clone = _prepareTableClone(table);
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
    <table class="table table-bordered table-sm">${clone.innerHTML}</table>
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
function exportExcel(tableId, filename, judul) {
    const table = document.getElementById(tableId);
    if (!table) { toast('Tabel tidak ditemukan untuk diekspor.', 'error'); return; }
    const title = judul || _tableTitle(table, filename);
    _ensureSheetJS(() => {
        try {
            const clone = _prepareTableClone(table);
            clone.style.position = 'absolute';
            clone.style.left = '-9999px';
            clone.style.display = 'table';
            document.body.appendChild(clone);
            
            const wb = XLSX.utils.table_to_book(clone, { raw: false });
            const sheetName = wb.SheetNames[0];
            const ws = wb.Sheets[sheetName];
            
            if (ws && ws['!ref']) {
                const range = XLSX.utils.decode_range(ws['!ref']);
                
                // 1. Auto-fit lebar kolom (hitung panjang teks terpanjang + padding lega)
                const colWidths = [];
                for (let C = range.s.c; C <= range.e.c; ++C) {
                    let maxLen = 10;
                    for (let R = range.s.r; R <= range.e.r; ++R) {
                        const cellAddr = XLSX.utils.encode_cell({ r: R, c: C });
                        const cell = ws[cellAddr];
                        if (cell && cell.v !== undefined && cell.v !== null) {
                            const valStr = String(cell.v).trim();
                            const lines = valStr.split('\n');
                            for (const line of lines) {
                                if (line.length > maxLen) {
                                    maxLen = line.length;
                                }
                            }
                        }
                    }
                    // Tambahkan ruang padding 4 karakter agar teks tidak mepet batas sel (min 12, max 60)
                    colWidths.push({ wch: Math.min(Math.max(maxLen + 4, 12), 60) });
                }
                ws['!cols'] = colWidths;
                
                // 2. Beri tinggi baris proporsional (header lebih lega, baris data nyaman dibaca)
                const rowHeights = [];
                rowHeights.push({ hpt: 26 }); // Header row
                for (let R = range.s.r + 1; R <= range.e.r; ++R) {
                    rowHeights.push({ hpt: 20 }); // Baris data
                }
                ws['!rows'] = rowHeights;
                
                // 3. Pasang Auto-Filter pada baris Header
                ws['!autofilter'] = { ref: ws['!ref'] };
            }
            
            // 4. Beri nama sheet yang informatif (bukan Sheet1)
            const cleanSheetName = (title || filename || 'Data').substring(0, 31).replace(/[:\\/?*\[\]]/g, ' ');
            if (cleanSheetName && cleanSheetName !== sheetName) {
                wb.SheetNames[0] = cleanSheetName;
                wb.Sheets[cleanSheetName] = ws;
                delete wb.Sheets[sheetName];
            }
            
            XLSX.writeFile(wb, (filename || tableId) + '.xlsx');
            clone.remove();
            toast('Data berhasil diekspor ke Excel dengan format rapi.', 'success');
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
            const clone = _prepareTableClone(table);
            clone.style.display = 'table';
            
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
            <button type="button" class="btn btn-outline btn-sm" onclick="exportExcel('${tableId}', '${filename}', '${(judul || '').replace(/'/g, "\\'")}')" title="Ekspor Excel">
                <i data-lucide="file-spreadsheet"></i><span class="d-none d-lg-inline"> Excel</span>
            </button>
            <button type="button" class="btn btn-outline btn-sm" onclick="exportPDF('${tableId}', '${filename}', '${(judul || '').replace(/'/g, "\\'")}')" title="Ekspor PDF">
                <i data-lucide="file-text"></i><span class="d-none d-lg-inline"> PDF</span>
            </button>
        </div>`;
    if (window.lucide) lucide.createIcons({ root: mount });
}
