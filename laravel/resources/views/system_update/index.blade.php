@extends('layouts.app')
@section('title', 'Update System')
@section('content')
    <style>
        .su-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; vertical-align:middle; }
        .su-dot.on { background:#16a34a; box-shadow:0 0 0 4px rgba(22,163,74,.2); }
        .su-dot.off { background:#dc2626; box-shadow:0 0 0 4px rgba(220,38,38,.2); }
        .su-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; margin-bottom:2px; }
        .su-value { font-weight:600; color:#0f172a; }
        .su-action { border:1px solid #e2e8f0; background:#f8fafc; border-radius:.85rem; padding:1.25rem; height:100%; display:flex; flex-direction:column; transition:all .2s ease; }
        .su-action:hover { border-color:#cbd5e1; background:#fff; box-shadow:0 8px 20px -8px rgba(15,23,42,.08); }
        .su-action p { font-size:.85rem; color:#64748b; flex-grow:1; margin-bottom:14px; }
        .su-log { background:#0f172a; color:#e2e8f0; font-size:.78rem; max-height:360px; overflow:auto; white-space:pre-wrap; border-radius:.65rem; padding:14px; margin:0; }
        .su-commit { font-size:.875rem; }
        .su-commit code { color:#4f46e5; background:#eef2ff; padding:2px 6px; border-radius:4px; }
    </style>

    <div class="page-header">
        <div>
            <h1 class="page-title">Update System & Worker</h1>
            <p class="page-subtitle">Perbarui aplikasi dari repositori GitHub dan kelola service worker penjalan cronjob</p>
        </div>
    </div>

    @unless ($tableReady)
        <div class="alert alert-warning border-0 rounded-3 shadow-sm mb-4">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <strong>Fitur belum aktif.</strong> Tabel <code>system_tasks</code> belum ada. Aktifkan sekali lewat SSH:
            <code>php artisan migrate --force</code> lalu <code>pm2 restart node-worker</code>.
            Setelah itu semua update bisa dilakukan dari halaman ini.
        </div>
    @endunless

    <div class="row g-3 mb-4">
        {{-- Status worker --}}
        <div class="col-lg-6">
            <div class="card-modern p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cpu text-primary fs-5"></i>
                        <h5 class="fw-bold mb-0">Status Worker</h5>
                    </div>
                    <div id="workerState"><span class="text-muted small">Memuat...</span></div>
                </div>
                <div class="row g-3">
                    <div class="col-6"><div class="su-label">Berjalan Sejak</div><div class="su-value" id="wStarted">-</div></div>
                    <div class="col-6"><div class="su-label">Cronjob Terjadwal</div><div class="su-value" id="wJobs">-</div></div>
                    <div class="col-6"><div class="su-label">Terakhir Lapor</div><div class="su-value" id="wSeen">-</div></div>
                    <div class="col-6"><div class="su-label">PID / Node</div><div class="su-value font-mono small" id="wPid">-</div></div>
                </div>
                <div class="small text-muted mt-3 pt-2 border-top">
                    <i class="bi bi-info-circle me-1"></i>Worker menerapkan perubahan cronjob secara otomatis setiap &plusmn;15 detik tanpa perlu restart.
                </div>
            </div>
        </div>

        {{-- Versi --}}
        <div class="col-lg-6">
            <div class="card-modern p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-git text-primary fs-5"></i>
                            <h5 class="fw-bold mb-0">Versi Aplikasi</h5>
                        </div>
                        <button class="btn btn-light border btn-sm d-inline-flex align-items-center gap-1" id="btnCheck" onclick="checkUpdate()">
                            <i class="bi bi-arrow-clockwise"></i> Cek Update
                        </button>
                    </div>
                    <div class="su-label">Terpasang di Server</div>
                    <div class="mb-3" id="vInstalled"><span class="text-muted">-</span></div>
                    <div class="su-label">Status Repositori GitHub</div>
                    <div id="vLatest" class="mb-2"><span class="text-muted">Belum dicek</span></div>
                </div>
            </div>
        </div>
    </div>

    <div id="commitList" class="card-modern p-4 mb-4" style="display:none;">
        <h6 class="fw-bold mb-3"><i class="bi bi-list-check text-primary me-1"></i>Perubahan yang Akan Dipasang</h6>
        <ul class="list-unstyled mb-0" id="commitItems"></ul>
    </div>

    {{-- Aksi --}}
    <div class="card-modern p-4 mb-4">
        <h5 class="fw-bold mb-3">Tindakan Sistem</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="su-action">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-cloud-download-fill text-primary fs-5"></i>
                        <strong class="text-dark">Update Aplikasi</strong>
                    </div>
                    <p>Ambil kode terbaru dari GitHub, jalankan migrasi database bila perlu, lalu restart worker otomatis jika kode worker ikut berubah.</p>
                    <button class="btn btn-primary" data-action="update" onclick="requestTask('update')">
                        <i class="bi bi-rocket-takeoff me-1"></i>Update Sekarang
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="su-action">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-arrow-repeat text-warning fs-5"></i>
                        <strong class="text-dark">Restart Worker</strong>
                    </div>
                    <p>Matikan dan nyalakan ulang worker (PM2). Gunakan bila cronjob tampak macet atau tidak berjalan. Jeda &plusmn;5 detik.</p>
                    <button class="btn btn-warning text-dark" data-action="restart" onclick="requestTask('restart')">
                        <i class="bi bi-power me-1"></i>Restart Worker
                    </button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="su-action">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-database-check text-info fs-5"></i>
                        <strong class="text-dark">Muat Ulang Cronjob</strong>
                    </div>
                    <p>Baca ulang daftar cronjob dari database sekarang juga. Biasanya tidak perlu karena sudah otomatis tiap 15 detik.</p>
                    <button class="btn btn-outline-primary" data-action="reload" onclick="requestTask('reload')">
                        <i class="bi bi-arrow-clockwise me-1"></i>Muat Ulang
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat --}}
    <div class="card-modern overflow-hidden">
        <div class="p-4 pb-2">
            <h5 class="fw-bold mb-0">Riwayat Proses</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th><th>Aksi</th><th>Status</th><th>Oleh</th><th>Waktu</th><th>Durasi</th><th class="text-end">Log</th>
                    </tr>
                </thead>
                <tbody id="taskRows">
                    <tr><td colspan="7" class="text-muted text-center py-4">Belum ada proses.</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

        const TYPE_LABEL = { update: 'Update aplikasi', restart: 'Restart worker', reload: 'Muat ulang cronjob' };
        const STATUS_BADGE = {
            pending: ['secondary', 'Menunggu worker'],
            running: ['info', 'Sedang berjalan'],
            restarting: ['warning', 'Restart...'],
            success: ['success', 'Berhasil'],
            failed: ['danger', 'Gagal'],
        };
        const openLogs = new Set();
        let pollTimer = null;
        let lastBusy = false;

        function esc(s) { return $('<div>').text(s ?? '').html(); }

        function renderStatus(res) {
            const w = res.worker;
            $('#workerState').html(w.online
                ? '<span class="badge-soft-success"><span class="su-dot on"></span>Online</span>'
                : '<span class="badge-soft-danger"><span class="su-dot off"></span>Offline</span>');
            $('#wStarted').text(w.started_at ? `${w.started_at} (${w.uptime})` : '-');
            $('#wJobs').text(w.jobs ?? '-');
            $('#wSeen').text(w.last_seen ?? '-');
            $('#wPid').text(w.pid ? `${w.pid} / ${w.node ?? '-'}` : '-');

            const v = res.version;
            $('#vInstalled').html(v.hash
                ? `<code class="px-2 py-1 rounded bg-light border text-primary">${esc(v.hash.substring(0, 7))}</code> <span class="fw-medium">${esc(v.subject)}</span><div class="small text-muted mt-1">${esc(v.date)} &middot; branch <strong>${esc(v.branch)}</strong></div>`
                : '<span class="text-muted">Belum diketahui (worker versi lama)</span>');

            renderTasks(res.tasks);
            $('[data-action]').prop('disabled', res.busy);

            if (lastBusy && !res.busy) checkUpdate(true);
            lastBusy = res.busy;
            schedulePoll(res.busy ? 2000 : 10000);
        }

        function renderTasks(tasks) {
            if (!tasks.length) {
                $('#taskRows').html('<tr><td colspan="7" class="text-muted text-center py-4">Belum ada proses.</td></tr>');
                return;
            }
            const rows = tasks.map(t => {
                const [color, label] = STATUS_BADGE[t.status] || ['secondary', t.status];
                const spinner = ['running', 'restarting'].includes(t.status)
                    ? '<span class="spinner-border spinner-border-sm me-1"></span>' : '';
                const isOpen = openLogs.has(t.id) || ['running', 'restarting', 'pending'].includes(t.status);
                return `
                    <tr>
                        <td class="font-mono small text-muted">#${t.id}</td>
                        <td class="fw-semibold">${esc(TYPE_LABEL[t.type] || t.type)}</td>
                        <td><span class="badge-soft-${color}">${spinner}${label}</span></td>
                        <td>${esc(t.requested_by || '-')}</td>
                        <td class="small text-muted">${esc(t.created_at)}</td>
                        <td class="small text-muted">${t.duration !== null ? t.duration + ' dtk' : '-'}</td>
                        <td class="text-end"><button class="btn btn-light border btn-sm" onclick="toggleLog(${t.id})"><i class="bi bi-terminal me-1"></i>${isOpen ? 'Tutup' : 'Log'}</button></td>
                    </tr>
                    <tr class="${isOpen ? '' : 'd-none'}" id="log-${t.id}">
                        <td colspan="7" class="bg-light"><pre class="su-log">${esc(t.log || 'Menunggu worker mengambil tugas ini...')}</pre></td>
                    </tr>`;
            }).join('');
            $('#taskRows').html(rows);
            $('.su-log').each(function () { this.scrollTop = this.scrollHeight; });
        }

        function toggleLog(id) {
            openLogs.has(id) ? openLogs.delete(id) : openLogs.add(id);
            $(`#log-${id}`).toggleClass('d-none');
            loadStatus();
        }

        function schedulePoll(ms) {
            clearTimeout(pollTimer);
            pollTimer = setTimeout(loadStatus, ms);
        }

        function loadStatus() {
            $.get('{{ route('system-update.status') }}')
                .done(renderStatus)
                .fail(() => schedulePoll(10000));
        }

        function checkUpdate(silent = false) {
            const btn = $('#btnCheck');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Mengecek...');
            $.get('{{ route('system-update.check') }}')
                .done(res => {
                    if (res.up_to_date) {
                        $('#vLatest').html('<span class="badge-soft-success"><i class="bi bi-check-circle-fill"></i> Sudah versi terbaru</span>');
                        $('#commitList').hide();
                        return;
                    }
                    $('#vLatest').html(
                        `<code class="px-2 py-1 rounded bg-light border">${esc((res.latest_hash || '').substring(0, 7))}</code> ${esc(res.latest_message)}` +
                        ' <span class="badge-soft-warning ms-1">Update tersedia</span>');
                    if (res.commits.length) {
                        $('#commitItems').html(res.commits.map(c =>
                            `<li class="su-commit mb-2"><code>${esc(c.hash)}</code> ${esc(c.message)} <span class="text-muted small">&middot; ${esc(c.author)}, ${esc(c.date)}</span></li>`
                        ).join(''));
                        $('#commitList').show();
                    } else {
                        $('#commitList').hide();
                    }
                })
                .fail(xhr => {
                    if (!silent) $('#vLatest').html(`<span class="text-danger small">${esc(xhr.responseJSON?.message || 'Gagal cek update.')}</span>`);
                })
                .always(() => btn.prop('disabled', false).html('<i class="bi bi-arrow-clockwise"></i> Cek Update'));
        }

        const CONFIRM = {
            update: ['Update aplikasi sekarang?', 'Kode terbaru dari GitHub akan dipasang ke server ini. Proses biasanya selesai di bawah 1 menit.'],
            restart: ['Restart worker?', 'Semua cronjob berhenti sebentar (±5 detik) lalu berjalan kembali.'],
            reload: ['Muat ulang daftar cronjob?', 'Worker membaca ulang cronjob dari database tanpa restart.'],
        };

        function requestTask(type) {
            const [title, text] = CONFIRM[type];
            Swal.fire({ title, text, icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, jalankan', cancelButtonText: 'Batal', reverseButtons: true })
                .then(r => {
                    if (!r.isConfirmed) return;
                    $('[data-action]').prop('disabled', true);
                    $.post('{{ route('system-update.request') }}', { type })
                        .done(res => {
                            openLogs.add(res.task_id);
                            Swal.fire({ icon: 'info', title: 'Dikirim ke worker', text: res.message, timer: 2500, showConfirmButton: false });
                            loadStatus();
                        })
                        .fail(xhr => {
                            Swal.fire({ icon: 'error', title: 'Tidak bisa diproses', text: xhr.responseJSON?.message || 'Terjadi kesalahan.' });
                            loadStatus();
                        });
                });
        }

        loadStatus();
        checkUpdate(true);
    </script>
@endsection
