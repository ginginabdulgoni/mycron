// Kontrol worker dari panel Laravel (menu Update System).
//
// Laravel (PHP-FPM, user www) tidak bisa menjalankan git sebagai root atau me-restart
// pm2 milik root. Karena itu panel hanya menulis permintaan ke tabel `system_tasks`,
// dan worker ini (berjalan sebagai root di bawah pm2) yang mengeksekusinya:
//   - update  : git fetch + reset ke origin/<branch>, composer/migrate/npm bila perlu,
//               lalu restart worker bila ada perubahan di node-worker/
//   - restart : keluar dengan kode 0, pm2 otomatis menyalakan ulang
//   - reload  : muat ulang daftar cronjob tanpa restart
// Worker juga menulis heartbeat ke tabel `settings` supaya panel tahu status worker.

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const db = require('./db');

const REPO_ROOT = path.resolve(__dirname, '..');
const LARAVEL_PATH = path.join(REPO_ROOT, 'laravel');
const WORKER_PATH = __dirname;
const BRANCH = process.env.DEPLOY_BRANCH || 'main';
const WEB_OWNER = process.env.DEPLOY_WEB_OWNER || 'www:www';
const PHP_BIN = process.env.PHP_BIN
  || ['/www/server/php/84/bin/php', '/usr/bin/php'].find(p => fs.existsSync(p))
  || 'php';
const COMPOSER_BIN = process.env.COMPOSER_BIN || '/usr/bin/composer';
const NPM_BIN = path.join(path.dirname(process.execPath), 'npm');
const STARTED_AT = Math.floor(Date.now() / 1000);

let reloadCronJobs = async () => {};
let jobCountProvider = () => 0;
let busy = false;

function log(...args) {
  // Prefix ✅/❌ supaya lolos filter console.log senyap di cron.js
  console.log(...args);
}

async function ensureTable() {
  await db.query(`
    CREATE TABLE IF NOT EXISTS system_tasks (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      type VARCHAR(20) NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      requested_by VARCHAR(255) NULL,
      log MEDIUMTEXT NULL,
      started_at TIMESTAMP NULL,
      finished_at TIMESTAMP NULL,
      created_at TIMESTAMP NULL,
      updated_at TIMESTAMP NULL,
      INDEX system_tasks_status_index (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  `);
}

async function setSetting(key, value) {
  await db.query(
    `INSERT INTO settings (\`key\`, value, created_at, updated_at) VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = UTC_TIMESTAMP()`,
    [key, value == null ? null : String(value)]
  );
}

function run(cmd, args, opts = {}) {
  return new Promise(resolve => {
    let output = '';
    let child;
    try {
      child = spawn(cmd, args, {
        cwd: opts.cwd || REPO_ROOT,
        env: { ...process.env, COMPOSER_ALLOW_SUPERUSER: '1', GIT_TERMINAL_PROMPT: '0', ...(opts.env || {}) },
      });
    } catch (err) {
      return resolve({ ok: false, code: -1, output: err.message });
    }
    const timer = setTimeout(() => {
      output += `\n[timeout ${opts.timeout || 300000}ms, proses dihentikan]`;
      child.kill('SIGKILL');
    }, opts.timeout || 300000);
    child.stdout.on('data', d => { output += d; });
    child.stderr.on('data', d => { output += d; });
    child.on('error', err => { output += `\n${err.message}`; });
    child.on('close', code => {
      clearTimeout(timer);
      resolve({ ok: code === 0, code, output: output.trim() });
    });
  });
}

function git(args, opts) {
  return run('git', ['-c', 'safe.directory=*', '-c', 'core.fileMode=false', ...args], opts);
}

async function gitHead() {
  const r = await git(['log', '-1', '--pretty=format:%H|%s|%ci']);
  if (!r.ok || !r.output) return null;
  const [hash, subject, date] = r.output.split('|');
  return { hash, subject, date };
}

async function heartbeat() {
  try {
    await setSetting('worker_heartbeat', Math.floor(Date.now() / 1000));
    await setSetting('worker_started_at', STARTED_AT);
    await setSetting('worker_pid', process.pid);
    await setSetting('worker_jobs_count', jobCountProvider());
  } catch (err) {
    console.error('❌ Heartbeat gagal:', err.message);
  }
}

async function publishVersion() {
  const head = await gitHead();
  if (head) {
    await setSetting('worker_git_head', head.hash);
    await setSetting('worker_git_subject', head.subject);
    await setSetting('worker_git_date', head.date);
  }
  await setSetting('worker_branch', BRANCH);
  await setSetting('worker_node_version', process.version);
}

async function appendLog(taskId, text) {
  const line = `[${new Date().toISOString().replace('T', ' ').slice(0, 19)} UTC] ${text}\n`;
  await db.query(
    'UPDATE system_tasks SET log = CONCAT(COALESCE(log, \'\'), ?), updated_at = UTC_TIMESTAMP() WHERE id = ?',
    [line, taskId]
  );
}

async function finish(taskId, status) {
  await db.query(
    'UPDATE system_tasks SET status = ?, finished_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ?',
    [status, taskId]
  );
}

async function step(taskId, label, cmd, args, opts) {
  await appendLog(taskId, `▶ ${label}`);
  const r = await run(cmd, args, opts);
  if (r.output) await appendLog(taskId, r.output.slice(-8000));
  await appendLog(taskId, r.ok ? `✔ ${label} selesai` : `✖ ${label} GAGAL (exit ${r.code})`);
  return r;
}

function restartSoon(taskId) {
  // Status 'restarting' diselesaikan oleh worker baru saat start (lihat recoverTasks).
  return db.query(
    'UPDATE system_tasks SET status = \'restarting\', updated_at = UTC_TIMESTAMP() WHERE id = ?',
    [taskId]
  ).then(() => {
    log(`✅ Restart worker diminta (task #${taskId}), keluar agar pm2 menyalakan ulang...`);
    setTimeout(() => process.exit(0), 1500);
  });
}

async function runUpdate(task) {
  const id = task.id;
  process.umask(0o002); // file/objek git baru tetap bisa ditulis grup www

  let r = await step(id, `Ambil kode terbaru (git fetch origin ${BRANCH})`, 'git',
    ['-c', 'safe.directory=*', 'fetch', 'origin', BRANCH], { timeout: 120000 });
  if (!r.ok) return finish(id, 'failed');

  const before = (await git(['rev-parse', 'HEAD'])).output;
  const target = (await git(['rev-parse', `origin/${BRANCH}`])).output;

  if (before === target) {
    await appendLog(id, `Sudah versi terbaru (${before.slice(0, 7)}). Tidak ada yang diubah.`);
    await publishVersion();
    return finish(id, 'success');
  }

  const diff = await git(['diff', '--name-only', before, target]);
  const changed = diff.ok ? diff.output.split('\n').filter(Boolean) : [];
  const commits = await git(['log', '--oneline', `${before}..${target}`]);
  await appendLog(id, `Versi ${before.slice(0, 7)} → ${target.slice(0, 7)}, ${changed.length} file berubah:\n${commits.output}`);

  r = await step(id, 'Terapkan kode (git reset --hard)', 'git',
    ['-c', 'safe.directory=*', 'reset', '--hard', target]);
  if (!r.ok) return finish(id, 'failed');

  const touched = prefix => changed.some(f => f.startsWith(prefix));

  if (changed.some(f => /^laravel\/composer\.(json|lock)$/.test(f))) {
    r = await step(id, 'Composer install', PHP_BIN,
      [COMPOSER_BIN, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
      { cwd: LARAVEL_PATH, timeout: 600000 });
    if (!r.ok) return finish(id, 'failed');
  }

  if (touched('laravel/')) {
    r = await step(id, 'Migrasi database', PHP_BIN, ['artisan', 'migrate', '--force'], { cwd: LARAVEL_PATH });
    if (!r.ok) return finish(id, 'failed');
    await step(id, 'Bersihkan cache Laravel', PHP_BIN, ['artisan', 'optimize:clear'], { cwd: LARAVEL_PATH });
  }

  if (changed.some(f => /^node-worker\/package(-lock)?\.json$/.test(f))) {
    r = await step(id, 'npm install (node-worker)', NPM_BIN, ['install', '--omit=dev', '--no-audit', '--no-fund'],
      { cwd: WORKER_PATH, timeout: 600000 });
    if (!r.ok) return finish(id, 'failed');
  }

  await step(id, `Perbaiki kepemilikan file (${WEB_OWNER})`, 'chown',
    ['-R', WEB_OWNER, LARAVEL_PATH, WORKER_PATH]);

  await publishVersion();

  if (touched('node-worker/')) {
    await appendLog(id, 'Ada perubahan di node-worker → worker di-restart otomatis.');
    return restartSoon(id);
  }

  await appendLog(id, 'Update selesai. Worker tidak perlu restart.');
  return finish(id, 'success');
}

async function runTask(task) {
  log(`✅ Menjalankan system task #${task.id} (${task.type})`);
  if (task.type === 'update') return runUpdate(task);

  if (task.type === 'restart') {
    await appendLog(task.id, `Restart diminta. PID lama ${process.pid}.`);
    return restartSoon(task.id);
  }

  if (task.type === 'reload') {
    await reloadCronJobs();
    await appendLog(task.id, `Daftar cronjob dimuat ulang (${jobCountProvider()} job aktif terjadwal).`);
    return finish(task.id, 'success');
  }

  await appendLog(task.id, `Tipe task tidak dikenal: ${task.type}`);
  return finish(task.id, 'failed');
}

async function pollTasks() {
  if (busy) return;
  busy = true;
  try {
    const [rows] = await db.query(
      'SELECT * FROM system_tasks WHERE status = \'pending\' ORDER BY id LIMIT 1'
    );
    if (!rows.length) return;
    const task = rows[0];
    const [claim] = await db.query(
      'UPDATE system_tasks SET status = \'running\', started_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = ? AND status = \'pending\'',
      [task.id]
    );
    if (!claim.affectedRows) return;
    try {
      await runTask(task);
    } catch (err) {
      console.error(`❌ System task #${task.id} error:`, err.message);
      await appendLog(task.id, `Error: ${err.message}`).catch(() => {});
      await finish(task.id, 'failed').catch(() => {});
    }
  } catch (err) {
    console.error('❌ Poll system task gagal:', err.message);
  } finally {
    busy = false;
  }
}

async function recoverTasks() {
  const [rows] = await db.query('SELECT id, status FROM system_tasks WHERE status IN (\'restarting\', \'running\')');
  for (const row of rows) {
    if (row.status === 'restarting') {
      await appendLog(row.id, `Worker menyala kembali (PID ${process.pid}, Node ${process.version}).`);
      await finish(row.id, 'success');
    } else {
      await appendLog(row.id, 'Worker berhenti saat task berjalan; task dianggap gagal.');
      await finish(row.id, 'failed');
    }
  }
}

async function start(options = {}) {
  reloadCronJobs = options.reloadCronJobs || reloadCronJobs;
  jobCountProvider = options.jobCount || jobCountProvider;
  try {
    await ensureTable();
    await recoverTasks();
    await publishVersion();
    await heartbeat();
  } catch (err) {
    console.error('❌ Inisialisasi kontrol sistem gagal:', err.message);
  }
  setInterval(heartbeat, 15000);
  setInterval(pollTasks, 5000);
}

module.exports = { start };
