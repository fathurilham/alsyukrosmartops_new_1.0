/* AL-SYUKROSMART OPS — main.js
   Fungsi inti frontend: modal, notifikasi toast, konfirmasi, sanitasi, dan komunikasi API */
'use strict';

/** Sanitasi string untuk mencegah XSS pada rendering innerHTML */
function esc(str) {
    if (str === null || str === undefined) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(str).replace(/[&<>"']/g, m => map[m]);
}

/** Membuka modal overlay berdasarkan ID elemen */
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const errBox = el.querySelector('.modal-error-box');
    if (errBox) errBox.remove();
    el.classList.add('open');
    if (window.lucide) lucide.createIcons({ root: el });
}

/** Menutup modal overlay */
function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('open');
    const errBox = el.querySelector('.modal-error-box');
    if (errBox) errBox.remove();
}

/** Menampilkan pesan error di dalam modal */
function showModalError(modalId, msg) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) return toast(msg, 'error');
    const modalDialog = modalEl.querySelector('.modal') || modalEl;
    let errBox = modalDialog.querySelector('.modal-error-box');
    if (!errBox) {
        errBox = document.createElement('div');
        errBox.className = 'modal-error-box alert alert-danger';
        errBox.style.margin = '14px 22px 0';
        errBox.style.fontSize = '12.5px';
        const modalBody = modalDialog.querySelector('.modal-body');
        if (modalBody) {
            modalDialog.insertBefore(errBox, modalBody);
        } else {
            modalDialog.prepend(errBox);
        }
    }
    errBox.textContent = msg;
    errBox.style.display = 'block';
}

/** Notifikasi Toast melayang */
function toast(msg, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }
    const t = document.createElement('div');
    t.className = `app-toast app-toast-${type}`;
    t.innerHTML = `<span style="flex:1">${esc(msg)}</span>`;
    t.onclick = () => t.remove();
    container.appendChild(t);
    setTimeout(() => {
        if (t.parentElement) t.remove();
    }, 4000);
}

/** Dialog konfirmasi interaktif (SweetAlert2 atau fallback confirm native) */
function customConfirm(message, confirmText = 'Ya', btnClass = 'btn-primary', iconHtml = '') {
    if (window.Swal) {
        return Swal.fire({
            html: (iconHtml ? `<div style="margin-bottom:12px">${iconHtml}</div>` : '') + `<div style="font-size:14px;font-weight:500;">${esc(message)}</div>`,
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'Batal',
            buttonsStyling: false,
            customClass: {
                confirmButton: `btn ${btnClass} me-2`,
                cancelButton: 'btn btn-outline'
            }
        }).then(res => res.isConfirmed);
    }
    return Promise.resolve(confirm(message));
}

/** Pembungkus pemanggilan API backend terpadu dengan proteksi CSRF */
async function apiFetch(modul, action, payload = {}, method = 'POST') {
    const metaCsrf = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = metaCsrf ? metaCsrf.content : '';
    let url = `api/${modul}.php`;
    const opts = { method: method.toUpperCase(), cache: 'no-store' };

    if (opts.method === 'GET') {
        const params = new URLSearchParams();
        params.append('action', action);
        for (const [k, v] of Object.entries(payload)) {
            if (v !== undefined && v !== null) params.append(k, v);
        }
        params.append('_t', Date.now());
        url += '?' + params.toString();
    } else {
        let fd;
        if (payload instanceof FormData) {
            fd = payload;
            if (!fd.has('action')) fd.append('action', action);
            if (!fd.has('csrf_token')) fd.append('csrf_token', csrfToken);
        } else {
            fd = new FormData();
            fd.append('action', action);
            fd.append('csrf_token', csrfToken);
            for (const [k, v] of Object.entries(payload)) {
                if (v !== undefined && v !== null) {
                    if (Array.isArray(v)) {
                        const key = k.endsWith('[]') ? k : `${k}[]`;
                        for (const item of v) {
                            fd.append(key, item);
                        }
                    } else {
                        fd.append(k, v);
                    }
                }
            }
        }
        opts.body = fd;
    }

    try {
        const res = await fetch(url, opts);
        const data = await res.json();
        if (res.status === 401) {
            window.location.href = 'index.php';
        }
        return data;
    } catch (err) {
        console.error('API Error:', err);
        return { ok: false, msg: 'Terjadi kesalahan sistem atau koneksi.' };
    }
}

/** Update isi elemen DOM dan inisialisasi ulang icon Lucide */
function updateDOM(id, html) {
    const el = document.getElementById(id);
    if (el) {
        el.innerHTML = html;
        if (window.lucide) lucide.createIcons();
    }
}

function customPrompt(message, defaultValue = '') {
    return Promise.resolve(window.prompt(message, defaultValue));
}

// Tutup modal jika overlay (latar belakang luar) diklik
document.addEventListener('click', (e) => {
    if (e.target.classList && e.target.classList.contains('modal-overlay')) {
        closeModal(e.target.id);
    }
});

// Tekan tombol Enter pada input di modal untuk langsung memicu tombol aksi utama (Simpan)
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        const tag = (e.target.tagName || '').toUpperCase();
        if (tag === 'TEXTAREA' || tag === 'BUTTON') return;

        const modalOverlay = e.target.closest('.modal-overlay.open');
        if (modalOverlay) {
            const submitBtn = modalOverlay.querySelector('.modal-foot button.btn-primary:not([disabled]), .modal-foot .btn-primary:not([disabled])')
                           || modalOverlay.querySelector('button.btn-primary:not([disabled])');
            if (submitBtn) {
                e.preventDefault();
                submitBtn.click();
            }
        }
    }
});
