@extends('layouts.app')
@section('title', 'Update System')
@section('content')
    <style>
        .su-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; vertical-align:middle; }
        .su-dot.on { background:#198754; box-shadow:0 0 0 3px rgba(25,135,84,.2); }
        .su-dot.off { background:#dc3545; box-shadow:0 0 0 3px rgba(220,53,69,.2); }
        .su-label { font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; color:#6c757d; margin-bottom:2px; }
        .su-value { font-weight:600; }
        .su-action { border:1px solid #dee2e6; border-radius:.5rem; padding:14px; height:100%; display:flex; flex-direction:column; }
        .su-action p { font-size:.875rem; color:#6c757d; flex-grow:1; margin-bottom:12px; }
        .su-log { background:#1e1e1e; color:#d4d4d4; font-size:.8rem; max-height:360px; overflow:auto; white-space:pre-wrap; border-radius:.375rem; padding:12px; margin:0; }
        .su-commit { font-size:.875rem; }
        .su-commit code { color:#6f42c1; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0">Update System</h2>
            <div class="text-muted small">Perbarui aplikasi dari GitHub dan kelola worker penjalan cronjob.</div>
        </div>
    </div>

    @unless ($tableReady)
        <div class="alert alert-warning">
            <strong>Fitur belum aktif.</strong> Tabel <code>system_tasks</code> belum ada. Aktifkan sekali lewat SSH:
            <code>php artisan migrate --force</code> lalu <code>pm2 restart node-worker</code>.
            Setelah itu semua update bisa dilakukan dari halaman ini.
        </div>
    @endunless

    <div class="row g-3 mb-3">
        {{-- Status worker --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Status Worker</h5>
                    <div class="mb-3 fs-5" id="workerState"><span class="text-muted">Memuat...</span></div>
                    <div class="row g-3">
                        <div class="col-6"><div class="su-label">Berjalan sejak</div><div class="su-value" id="wStarted">-</div></div>
                        <div class="col-6"><div class="su-label">Cronjob terjadwal</div><div class="su-value" id="wJobs">-</div></div>
                        <div class="col-6"><div class="su-label">Terakhir lapor</div><div class="su-value" id="wSeen">-</div></div>
                        <div class="col-6"><div class="su-label">PID / Node</div><div class="su-value" id="wPid">-</div></div>
                    </div>
                    <div class="small text-muted mt-3">
                        Worker adalah proses yang menjalankan semua cronjob. Cronjob baru, diubah, atau dihapus
                        otomatis diterapkan dalam &plusmn;15 detik tanpa perlu restart.
                    </div>
                </div>
            </div>
        </div>

        {{-- Versi --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Versi Aplikasi</h5>
                    <div class="su-label">Terpasang di server</div>
                    <div class="mb-3" id="vInstalled"><span class="text-muted">-</span></div>
                    <div class="su-label">Terbaru di GitHub</div>
                    <div id="vLatest" class="mb-2"><span class="text-muted">Belum dicek</span></div>
                    <button class="btn btn-outline-secondary btn-sm" id="btnCheck" onclick="checkUpdate()">Cek Update</button>
                </div>
            </div>
        </div>
    </div>

    <div id="commitList" class="card mb-3" style="display:none;">
        <div class="card-body">
            <h6 class="card-title">Perubahan yang akan dipasang</h6>
            <ul class="list-unstyled mb-0" id="commitItems"></ul>
        </div>
    </div>

    {{-- Aksi --}}
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">Aksi</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="su-action">
                        <strong class="mb-1">Update Aplikasi</strong>
                        <p>Ambil kode terbaru dari GitHub, jalankan migrasi database bila perlu, lalu restart worker otomatis jika kode worker ikut berubah.</p>
                        <button class="btn btn-primary" data-action="update" onclick="requestTask('update')">Update Sekarang</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="su-action">
                        <strong class="mb-1">Restart Worker</strong>
                        <p>Matikan dan nyalakan ulang worker (pm2). Gunakan bila cronjob tampak macet atau tidak berjalan. Jeda &plusmn;5 detik.</p>
                        <button class="btn btn-warning" data-action="restart" onclick="requestTask('restart')">Restart Worker</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="su-action">
                        <strong class="mb-1">Muat Ulang Cronjob</strong>
                        <p>Baca ulang daftar cronjob dari database sekarang juga. Biasanya tidak perlu karena sudah otomatis tiap 15 detik.</p>
                        <button class="btn btn-outline-primary" data-action="reload" onclick="requestTask('reload')">Muat Ulang</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Riwayat --}}
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">Riwayat Proses</h5>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>Aksi</th><th>Status</th><th>Oleh</th><th>Waktu</th><th>Durasi</th><th></th>
                        </tr>
                    </thead>
                    <tbody id="taskRows">
                        <tr><td colspan="7" class="text-muted">Belum ada proses.</td></tr>
                    </tbody>
                </table>
            </div>
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
                ? '<span class="su-dot on"></span><span class="text-success fw-semibold">Online</span>'
                : '<span class="su-dot off"></span><span class="text-danger fw-semibold">Offline</span>' +
                  '<div class="small text-muted mt-1">Worker tidak melapor &gt; 1 menit. Coba Restart Worker, atau cek via SSH: <code>pm2 status</code>.</div>');
            $('#wStarted').text(w.started_at ? `${w.started_at} (${w.uptime})` : '-');
            $('#wJobs').text(w.jobs ?? '-');
            $('#wSeen').text(w.last_seen ?? '-');
            $('#wPid').text(w.pid ? `${w.pid} / ${w.node ?? '-'}` : '-');

            const v = res.version;
            $('#vInstalled').html(v.hash
                ? `<code>${esc(v.hash.substring(0, 7))}</code> ${esc(v.subject)}<div class="small text-muted">${esc(v.date)} &middot; branch ${esc(v.branch)}</div>`
                : '<span class="text-muted">Belum diketahui (worker versi lama)</span>');

            renderTasks(res.tasks);
            $('[data-action]').prop('disabled', res.busy);

            if (lastBusy && !res.busy) checkUpdate(true);
            lastBusy = res.busy;
            schedulePoll(res.busy ? 2000 : 10000);
        }

        function renderTasks(tasks) {
            if (!tasks.length) {
                $('#taskRows').html('<tr><td colspan="7" class="text-muted">Belum ada proses.</td></tr>');
                return;
            }
            const rows = tasks.map(t => {
                const [color, label] = STATUS_BADGE[t.status] || ['secondary', t.status];
                const spinner = ['running', 'restarting'].includes(t.status)
                    ? '<span class="spinner-border spinner-border-sm me-1"></span>' : '';
                const isOpen = openLogs.has(t.id) || ['running', 'restarting', 'pending'].includes(t.status);
                return `
                    <tr>
                        <td>${t.id}</td>
                        <td>${esc(TYPE_LABEL[t.type] || t.type)}</td>
                        <td><span class="badge bg-${color}">${spinner}${label}</span></td>
                        <td>${esc(t.requested_by || '-')}</td>
                        <td class="small">${esc(t.created_at)}</td>
                        <td class="small">${t.duration !== null ? t.duration + ' dtk' : '-'}</td>
                        <td class="text-end"><button class="btn btn-link btn-sm p-0" onclick="toggleLog(${t.id})">${isOpen ? 'Tutup log' : 'Lihat log'}</button></td>
                    </tr>
                    <tr class="${isOpen ? '' : 'd-none'}" id="log-${t.id}">
                        <td colspan="7"><pre class="su-log">${esc(t.log || 'Menunggu worker mengambil tugas ini...')}</pre></td>
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
            btn.prop('disabled', true).text('Mengecek...');
            $.get('{{ route('system-update.check') }}')
                .done(res => {
                    if (res.up_to_date) {
                        $('#vLatest').html('<span class="badge bg-success">Sudah versi terbaru</span>');
                        $('#commitList').hide();
                        return;
                    }
                    $('#vLatest').html(
                        `<code>${esc((res.latest_hash || '').substring(0, 7))}</code> ${esc(res.latest_message)}` +
                        ' <span class="badge bg-warning text-dark ms-1">Update tersedia</span>');
                    if (res.commits.length) {
                        $('#commitItems').html(res.commits.map(c =>
                            `<li class="su-commit mb-1"><code>${esc(c.hash)}</code> ${esc(c.message)} <span class="text-muted small">&middot; ${esc(c.author)}, ${esc(c.date)}</span></li>`
                        ).join(''));
                        $('#commitList').show();
                    } else {
                        $('#commitList').hide();
                    }
                })
                .fail(xhr => {
                    if (!silent) $('#vLatest').html(`<span class="text-danger">${esc(xhr.responseJSON?.message || 'Gagal cek update.')}</span>`);
                })
                .always(() => btn.prop('disabled', false).text('Cek Update'));
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
