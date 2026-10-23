const cron = require('node-cron');
const db = require('./db');
const axios = require('axios');
const ping = require('ping');
const { URL } = require('url');
const dns = require('dns');
const http = require('http');
const https = require('https');
// p-limit is ESM-only; require returns the module namespace, so grab the default export
const pLimit = require('p-limit').default;
const system = require('./system');
const VERBOSE_LOGS = process.env.VERBOSE_CRON_LOGS === '1';
const originalLog = console.log;
console.log = (...args) => {
  const msg = String(args[0] || '');
  if (VERBOSE_LOGS || /^([❌⚠️🚀✅ Cron Worker|🌍|🧹|🗑️]|Skipping overlap)/.test(msg)) {
    originalLog(...args);
  }
};

// Default fallback
process.env.TZ = 'UTC';
let appTimezone = 'UTC';
let timezoneFallbackWarned = false;

// Concurrency & Priority Queue untuk eksekusi cronjob
// Mengutamakan cronjob yang belum pernah tereksekusi (last_run_at IS NULL),
// kemudian cronjob yang paling lama belum jalan (oldest last_run_at, misal dari tgl 18).
const MAX_CONCURRENCY = parseInt(process.env.CRON_CONCURRENCY || '100', 10);
const JOB_HTTP_TIMEOUT_MS = parseInt(process.env.CRON_HTTP_TIMEOUT_MS || '10000', 10);
const JOB_HARD_TIMEOUT_MS = JOB_HTTP_TIMEOUT_MS + 3000;

let activeWorkers = 0;
const pendingQueue = [];
const queuedJobIds = new Set();

function withHardTimeout(promise, ms, label = 'Operation timeout') {
  let timer;
  const timeoutPromise = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error(`${label} (${ms}ms)`)), ms);
  });
  return Promise.race([promise, timeoutPromise]).finally(() => clearTimeout(timer));
}

function parseLastRunMs(val) {
  if (!val) return 0;
  if (val instanceof Date) return val.getTime() || 0;
  const iso = String(val).includes('T') ? String(val) : String(val).replace(' ', 'T') + 'Z';
  const t = Date.parse(iso);
  return Number.isNaN(t) ? 0 : t;
}

// Estimasi interval jadwal cron (dalam milidetik) untuk deteksi job yang terlambat/stuck
function getExpectedIntervalMs(schedule) {
  const parts = String(schedule || '* * * * *').trim().split(/\s+/);
  if (parts.length < 5) return 60 * 1000;
  const [min, hour, dom, month, dow] = parts;

  if (min.startsWith('*/')) {
    const n = parseInt(min.slice(2), 10) || 1;
    return n * 60 * 1000;
  }
  if (hour.startsWith('*/')) {
    const n = parseInt(hour.slice(2), 10) || 1;
    return n * 60 * 60 * 1000;
  }
  if (dom.startsWith('*/')) {
    const n = parseInt(dom.slice(2), 10) || 1;
    return n * 24 * 60 * 60 * 1000;
  }
  if (dow !== '*') {
    return 7 * 24 * 60 * 60 * 1000;
  }
  if (dom !== '*') {
    return 30 * 24 * 60 * 60 * 1000;
  }
  if (hour !== '*') {
    return 24 * 60 * 60 * 1000;
  }
  if (min !== '*') {
    return 60 * 60 * 1000;
  }
  return 60 * 1000;
}

function drainPriorityQueue() {
  while (activeWorkers < MAX_CONCURRENCY && pendingQueue.length > 0) {
    // Urutkan antrean:
    // 1. Belum pernah tereksekusi (lastRunAtMs === 0) selalu paling depan
    // 2. Yang paling lama belum dieksekusi (lastRunAtMs terkecil, misal tgl 18) berikutnya
    pendingQueue.sort((a, b) => {
      const aNever = a.getLastRunMs() === 0 ? 0 : 1;
      const bNever = b.getLastRunMs() === 0 ? 0 : 1;
      if (aNever !== bNever) return aNever - bNever;
      return a.getLastRunMs() - b.getLastRunMs();
    });

    const nextItem = pendingQueue.shift();
    if (!nextItem) break;

    queuedJobIds.delete(nextItem.jobId);
    activeWorkers++;

    // Hard timeout di level worker slot agar TIDAK ADA slot yang bisa nyangkut selamanya
    withHardTimeout(
      Promise.resolve().then(() => nextItem.run()),
      JOB_HARD_TIMEOUT_MS + 5000,
      `Worker slot hard timeout for job #${nextItem.jobId}`
    )
      .catch(err => {
        console.error(`❌ Queue execution error for job #${nextItem.jobId}:`, err.message);
      })
      .finally(() => {
        activeWorkers--;
        drainPriorityQueue();
      });
  }
}

function enqueueJobExecution(jobId, getLastRunMs, runFn) {
  const key = String(jobId);
  if (queuedJobIds.has(key)) {
    return;
  }
  queuedJobIds.add(key);
  pendingQueue.push({
    jobId: key,
    getLastRunMs,
    run: runFn,
  });
  drainPriorityQueue();
}

// DNS cache untuk request job.
const DNS_TTL_MS = 5 * 60 * 1000;
const DNS_NEGATIVE_TTL_MS = 60 * 1000;
const dnsCache = new Map(); // host -> { address, family, error, expires }
const dnsInflight = new Map(); // host -> Promise
const resolver = new dns.promises.Resolver({ timeout: 2000, tries: 2 });
const dnsServers = (process.env.CRON_DNS_SERVERS || '1.1.1.1,8.8.8.8,8.8.4.4')
  .split(',').map(s => s.trim()).filter(Boolean);
resolver.setServers(dnsServers);

async function resolveHost(hostname) {
  const cached = dnsCache.get(hostname);
  if (cached && cached.expires > Date.now()) {
    if (cached.error) throw cached.error;
    return cached;
  }
  if (dnsInflight.has(hostname)) return dnsInflight.get(hostname);

  const p = (async () => {
    try {
      const addrs = await withHardTimeout(
        resolver.resolve4(hostname),
        3500,
        `DNS resolve4 timeout for ${hostname}`
      );
      const entry = { address: addrs[0], family: 4, expires: Date.now() + DNS_TTL_MS };
      dnsCache.set(hostname, entry);
      return entry;
    } catch (err) {
      // Hanya fallback ke dns.promises.lookup untuk host lokal (tanpa titik / .local / .localhost)
      // supaya domain publik yang mati tidak mengantre di getaddrinfo glibc yang lambat.
      const isLocalHost = !hostname.includes('.') || hostname.endsWith('.local') || hostname.endsWith('.localhost');
      if (isLocalHost) {
        try {
          const r = await withHardTimeout(
            dns.promises.lookup(hostname, { family: 4 }),
            2000,
            `DNS lookup timeout for ${hostname}`
          );
          const entry = { address: r.address, family: 4, expires: Date.now() + DNS_TTL_MS };
          dnsCache.set(hostname, entry);
          return entry;
        } catch (lookupErr) {
          dnsCache.set(hostname, { error: lookupErr, expires: Date.now() + DNS_NEGATIVE_TTL_MS });
          throw lookupErr;
        }
      }
      dnsCache.set(hostname, { error: err, expires: Date.now() + DNS_NEGATIVE_TTL_MS });
      throw err;
    } finally {
      dnsInflight.delete(hostname);
    }
  })();
  dnsInflight.set(hostname, p);
  return p;
}

function cachedLookup(hostname, options, callback) {
  if (typeof options === 'function') {
    callback = options;
    options = {};
  }
  resolveHost(hostname)
    .then(({ address, family }) => {
      if (options && options.all) return callback(null, [{ address, family }]);
      callback(null, address, family);
    })
    .catch(err => callback(err));
}

const jobHttpAgent = new http.Agent({
  lookup: cachedLookup,
  keepAlive: true,
  maxSockets: MAX_CONCURRENCY,
  timeout: JOB_HTTP_TIMEOUT_MS
});
const jobHttpsAgent = new https.Agent({
  lookup: cachedLookup,
  keepAlive: true,
  maxSockets: MAX_CONCURRENCY,
  timeout: JOB_HTTP_TIMEOUT_MS,
  rejectUnauthorized: false
});

async function setTimezoneFromDatabase() {
  try {
    const [rows] = await db.query("SELECT value FROM settings WHERE `key` = 'timezone' LIMIT 1");
    const timezone = rows[0]?.value || 'UTC';

    appTimezone = timezone;
    process.env.TZ = timezone;
    console.log(`🌍 Timezone set to: ${timezone}`);
  } catch (err) {
    console.warn('⚠️ Gagal mengambil timezone dari database, menggunakan UTC');
  }
}

async function withTimezoneConnection(callback) {
  const connection = await db.getConnection();
  try {
    try {
      await connection.query('SET time_zone = ?', [appTimezone]);
    } catch (err) {
      if (!timezoneFallbackWarned) {
        console.warn(`⚠️ Gagal set time_zone ke ${appTimezone}, fallback ke UTC (${err.message})`);
        timezoneFallbackWarned = true;
      }
      await connection.query("SET time_zone = 'UTC'");
    }
    return await callback(connection);
  } finally {
    connection.release();
  }
}

async function clearOldLogsIfEnabled() {
  try {
    const [rows] = await db.query(`
      SELECT
        MAX(CASE WHEN \`key\` = 'clear_logs_active' THEN value ELSE NULL END) AS active,
        MAX(CASE WHEN \`key\` = 'clear_logs_schedule' THEN value ELSE NULL END) AS schedule
      FROM settings
    `);

    const clearLogsActive = rows[0]?.active == 1 || rows[0]?.active == '1' || rows[0]?.active === true;
    const schedule = rows[0]?.schedule;

    if (!clearLogsActive) {
      console.log('🧹 Clear logs not active');
      return;
    }

    let interval;
    switch (schedule) {
      case '1_day':
        interval = 'INTERVAL 1 DAY';
        break;
      case '1_week':
        interval = 'INTERVAL 1 WEEK';
        break;
      case '1_month':
        interval = 'INTERVAL 1 MONTH';
        break;
      default:
        console.warn('⚠️ Invalid clear_logs_schedule, skipping');
        return;
    }

    const [result] = await db.query(`
      DELETE FROM cron_logs WHERE run_at < NOW() - ${interval}
    `);

    console.log(`🧹 Old logs cleared: ${result.affectedRows} entries older than ${schedule}`);
  } catch (err) {
    console.error('❌ Error clearing old logs:', err.message);
  }
}

// Extract domain from URL
function extractDomain(url) {
  try {
    const urlObj = new URL(url);
    return urlObj.hostname;
  } catch (err) {
    return null;
  }
}

// Check if domain is reachable via HTTP HEAD request
async function checkDomainHTTP(domain) {
  try {
    const protocols = ['https://', 'http://'];

    for (const protocol of protocols) {
      try {
        await withHardTimeout(
          axios.head(`${protocol}${domain}`, {
            timeout: 3000,
            httpAgent: jobHttpAgent,
            httpsAgent: jobHttpsAgent,
            maxRedirects: 3,
            validateStatus: function (status) {
              return status < 500;
            }
          }),
          4000,
          'HTTP HEAD timeout'
        );
        return true;
      } catch (err) {
        continue;
      }
    }
    return false;
  } catch (err) {
    return false;
  }
}

// Ping domain to check if it's reachable
async function pingDomain(domain) {
  try {
    const res = await withHardTimeout(
      ping.promise.probe(domain, {
        timeout: 2,
        extra: ['-c', '1']
      }),
      3500,
      'ICMP ping timeout'
    );

    if (res.alive) {
      return true;
    }

    const httpAlive = await checkDomainHTTP(domain);
    if (httpAlive) {
      return true;
    }

    return false;
  } catch (err) {
    return await checkDomainHTTP(domain);
  }
}

let rtoCheckRunning = false;
const rtoCheckLimit = pLimit(10);

// Check and update RTO domains (non-blocking, parallel 10 domains at a time)
async function checkAndUpdateRTODomains() {
  if (rtoCheckRunning) return;
  rtoCheckRunning = true;
  try {
    await setTimezoneFromDatabase();
    console.log('🔍 Checking domain health...');

    const [excludedSettings] = await db.query(`
      SELECT value FROM settings WHERE \`key\` = 'rto_excluded_domains' LIMIT 1
    `);
    const excludedDomainsStr = excludedSettings[0]?.value || '';
    const excludedDomains = excludedDomainsStr.split('\n')
      .map(d => d.trim().toLowerCase())
      .filter(d => d.length > 0);

    const [jobs] = await db.query(`
      SELECT DISTINCT url FROM cronjobs WHERE active = 1
    `);

    const domains = new Set();
    jobs.forEach(job => {
      const domain = extractDomain(job.url);
      if (domain) domains.add(domain);
    });

    console.log(`📊 Found ${domains.size} unique domains to check`);

    await Promise.all(
      Array.from(domains).map(domain =>
        rtoCheckLimit(async () => {
          if (excludedDomains.includes(domain.toLowerCase())) {
            await db.query('DELETE FROM rto_domains WHERE domain = ?', [domain]);
            return;
          }

          const isAlive = await pingDomain(domain);

          if (!isAlive) {
            const [existing] = await db.query(
              'SELECT id, rto_count FROM rto_domains WHERE domain = ?',
              [domain]
            );

            if (existing.length > 0) {
              await withTimezoneConnection((connection) =>
                connection.query(
                  'UPDATE rto_domains SET rto_count = rto_count + 1, last_checked_at = NOW(), updated_at = NOW() WHERE domain = ?',
                  [domain]
                )
              );
            } else {
              await withTimezoneConnection((connection) =>
                connection.query(
                  'INSERT INTO rto_domains (domain, rto_count, first_detected_at, last_checked_at, created_at, updated_at) VALUES (?, 1, NOW(), NOW(), NOW(), NOW())',
                  [domain]
                )
              );
            }
          } else {
            await db.query('DELETE FROM rto_domains WHERE domain = ?', [domain]);
          }
        })
      )
    );
  } catch (err) {
    console.error('❌ Error checking RTO domains:', err.message);
  } finally {
    rtoCheckRunning = false;
  }
}

// Auto-delete cronjobs for RTO domains that exceed the age limit
async function autoDeleteRTOCronjobs() {
  try {
    const [settings] = await db.query(`
      SELECT
        MAX(CASE WHEN \`key\` = 'auto_delete_rto_active' THEN value ELSE NULL END) AS active,
        MAX(CASE WHEN \`key\` = 'auto_delete_rto_days' THEN value ELSE NULL END) AS days
      FROM settings
    `);

    const autoDeleteActive = settings[0]?.active == 1 || settings[0]?.active == '1' || settings[0]?.active === true;
    const days = parseInt(settings[0]?.days || 3);

    if (!autoDeleteActive) {
      return;
    }

    const [rtoDomains] = await db.query(`
      SELECT domain, DATEDIFF(NOW(), first_detected_at) as age_days
      FROM rto_domains
      WHERE first_detected_at IS NOT NULL
        AND DATEDIFF(NOW(), first_detected_at) >= ?
    `, [days]);

    if (rtoDomains.length === 0) {
      return;
    }

    let totalDeleted = 0;

    for (const rto of rtoDomains) {
      const [result] = await db.query(
        'DELETE FROM cronjobs WHERE url LIKE ?',
        [`%${rto.domain}%`]
      );
      totalDeleted += result.affectedRows;
      await db.query('DELETE FROM rto_domains WHERE domain = ?', [rto.domain]);
    }

    console.log(`🗑️ Total cronjobs deleted: ${totalDeleted}`);
  } catch (err) {
    console.error('❌ Error auto-deleting RTO cronjobs:', err.message);
  }
}

const scheduledTasks = {};
const runningJobs = new Set();

function jobSignature(job) {
  return [job.schedule, job.url, job.name, job.save_logs ? 1 : 0].join('|');
}

async function loadCronJobs() {
  const [rows] = await db.query(`
    SELECT * FROM cronjobs 
    WHERE active = 1
    ORDER BY (last_run_at IS NULL) DESC, last_run_at ASC
  `);

  const now = Date.now();

  for (const job of rows) {
    const existing = scheduledTasks[job.id];

    if (existing) {
      const dbMs = parseLastRunMs(job.last_run_at);
      if (dbMs > existing.lastRunAtMs) {
        existing.lastRunAtMs = dbMs;
      }
      existing.job = job;

      if (existing.signature !== jobSignature(job)) {
        console.log(`🔁 Rescheduling ${job.name}`);
        if (existing.task) existing.task.stop();
        scheduleCron(job);
      }
    } else {
      scheduleCron(job);
    }

    // Langsung masukkan ke antrean prioritas bila belum pernah jalan (last_run_at IS NULL)
    // atau sudah melewati batas jadwal seharusnya (misal nyangkut di tgl 18).
    const entry = scheduledTasks[job.id];
    if (entry) {
      const intervalMs = getExpectedIntervalMs(job.schedule);
      const isNeverRun = entry.lastRunAtMs === 0;
      const isOverdue = !isNeverRun && (now - entry.lastRunAtMs) > (intervalMs + 30000);
      if (isNeverRun || isOverdue) {
        triggerJobExecution(job.id);
      }
    }
  }

  // Hapus yang tidak aktif lagi
  const activeIds = new Set(rows.map(r => String(r.id)));
  Object.keys(scheduledTasks).forEach(id => {
    if (!activeIds.has(String(id))) {
      console.log(`⛔ Unscheduling job ID ${id}`);
      if (scheduledTasks[id].task) scheduledTasks[id].task.stop();
      delete scheduledTasks[id];
    }
  });
}

function triggerJobExecution(jobId) {
  const lockKey = String(jobId);
  const entry = scheduledTasks[jobId];
  if (!entry) return;

  if (runningJobs.has(lockKey) || queuedJobIds.has(lockKey)) {
    return;
  }

  enqueueJobExecution(
    lockKey,
    () => scheduledTasks[jobId]?.lastRunAtMs ?? 0,
    async () => {
      const currentEntry = scheduledTasks[jobId];
      if (!currentEntry) return;
      const job = currentEntry.job;

      if (runningJobs.has(lockKey)) return;
      runningJobs.add(lockKey);

      const nowMs = Date.now();
      const utcNow = new Date(nowMs).toISOString().slice(0, 19).replace('T', ' ');
      const controller = new AbortController();
      const abortTimer = setTimeout(() => controller.abort(), JOB_HTTP_TIMEOUT_MS + 500);

      try {
        const res = await withHardTimeout(
          axios.get(job.url, {
            timeout: JOB_HTTP_TIMEOUT_MS,
            signal: controller.signal,
            httpAgent: jobHttpAgent,
            httpsAgent: jobHttpsAgent,
            maxRedirects: 5,
            maxContentLength: 512 * 1024,
            maxBodyLength: 512 * 1024,
            validateStatus: function (status) {
              return status >= 200 && status < 500;
            }
          }),
          JOB_HARD_TIMEOUT_MS,
          `Request hard timeout (${job.url})`
        );

        currentEntry.lastRunAtMs = Date.now();

        await db.query(
          'UPDATE cronjobs SET last_run_at = ? WHERE id = ?',
          [utcNow, job.id]
        );

        if (job.save_logs) {
          const bodyStr = typeof res.data === 'string'
            ? res.data.substring(0, 10000)
            : JSON.stringify(res.data ?? '').substring(0, 10000);
          await db.query(
            'INSERT INTO cron_logs (cronjob_id, status, response, response_body, run_at) VALUES (?, ?, ?, ?, ?)',
            [job.id, 'success', res.status, bodyStr, utcNow]
          );
        }
      } catch (err) {
        controller.abort();
        console.error(`❌ Error executing ${job.name}:`, err.message);
        currentEntry.lastRunAtMs = Date.now();

        try {
          await db.query(
            'UPDATE cronjobs SET last_run_at = ? WHERE id = ?',
            [utcNow, job.id]
          );
        } catch (updateErr) {
          console.error(`❌ Failed to update last_run_at for ${job.name}:`, updateErr.message);
        }

        if (job.save_logs) {
          let errBody = null;
          try {
            if (err.response && err.response.data !== undefined) {
              errBody = typeof err.response.data === 'string'
                ? err.response.data.substring(0, 10000)
                : JSON.stringify(err.response.data).substring(0, 10000);
            }
          } catch (_) {}
          await db.query(
            'INSERT INTO cron_logs (cronjob_id, status, response, response_body, run_at) VALUES (?, ?, ?, ?, ?)',
            [job.id, 'error', String(err.message || 'Error').substring(0, 250), errBody, utcNow]
          ).catch(() => {});
        }
      } finally {
        clearTimeout(abortTimer);
        runningJobs.delete(lockKey);
      }
    }
  );
}

function scheduleCron(job) {
  let task = null;
  if (cron.validate(job.schedule)) {
    task = cron.schedule(job.schedule, () => {
      triggerJobExecution(job.id);
    });
  } else {
    console.warn(`⚠️ Jadwal cron tidak standar untuk ${job.name} ("${job.schedule}"), tetap dijalankan via watchdog.`);
  }

  // Tetap daftarkan di scheduledTasks walaupun format cron tidak standar,
  // supaya watchdog tetap bisa mengeksekusi job yang belum jalan / terlambat!
  scheduledTasks[job.id] = {
    task,
    job,
    schedule: job.schedule,
    signature: jobSignature(job),
    lastRunAtMs: parseLastRunMs(job.last_run_at)
  };
}

// Watchdog tiap 15 detik: pastikan cronjob yang belum pernah dieksekusi (last_run_at IS NULL)
// atau yang stuck/terlambat (mis. masih tgl 18) langsung masuk antrean prioritas tertinggi.
async function sweepUnexecutedAndOverdueJobs() {
  try {
    const [rows] = await db.query(`
      SELECT id, schedule, last_run_at
      FROM cronjobs
      WHERE active = 1
      ORDER BY (last_run_at IS NULL) DESC, last_run_at ASC
    `);

    const now = Date.now();
    for (const row of rows) {
      const entry = scheduledTasks[row.id];
      if (!entry) continue;

      const dbMs = parseLastRunMs(row.last_run_at);
      if (dbMs > entry.lastRunAtMs) {
        entry.lastRunAtMs = dbMs;
      }

      const intervalMs = getExpectedIntervalMs(row.schedule);
      const isNeverRun = entry.lastRunAtMs === 0;
      const isOverdue = !isNeverRun && (now - entry.lastRunAtMs) > (intervalMs + 30000);

      if (isNeverRun || isOverdue) {
        triggerJobExecution(row.id);
      }
    }
  } catch (err) {
    console.error('❌ Sweep unexecuted/overdue jobs error:', err.message);
  }
}

(async () => {
  console.log('🚀 Starting Cron Worker...');
  await setTimezoneFromDatabase();
  await loadCronJobs();

  // Nyalakan kontrol sistem (heartbeat + poll task update/restart/reload) SEGERA
  // tanpa menunggu pengecekan ping domain RTO yang butuh waktu lama!
  await system.start({
    reloadCronJobs: loadCronJobs,
    jobCount: () => Object.keys(scheduledTasks).length,
  });
  console.log('✅ Cron Worker initialized successfully');

  // Jalankan pembersihan log dan pengecekan domain RTO di background (non-blocking)
  clearOldLogsIfEnabled().catch(() => {});
  checkAndUpdateRTODomains().catch(() => {});
  autoDeleteRTOCronjobs().catch(() => {});
})();

// Deteksi cronjob baru/diubah/dihapus tanpa perlu restart worker.
let cronFingerprint = null;
setInterval(async () => {
  try {
    const [rows] = await db.query(`
      SELECT COUNT(*) AS n,
             COALESCE(SUM(CRC32(CONCAT_WS('|', id, url, schedule, name, COALESCE(save_logs, 0)))), 0) AS crc
      FROM cronjobs WHERE active = 1
    `);
    const fp = `${rows[0].n}|${rows[0].crc}`;
    if (cronFingerprint !== null && fp !== cronFingerprint) {
      console.log('✅ Perubahan cronjob terdeteksi, memuat ulang daftar job...');
      await loadCronJobs();
    }
    cronFingerprint = fp;
  } catch (err) {
    console.error('❌ Cek perubahan cronjob gagal:', err.message);
  }
}, 15000);

// Watchdog tiap 15 detik: pastikan cronjob yang belum pernah dieksekusi (last_run_at IS NULL)
// atau yang stuck/terlambat langsung diprioritaskan untuk jalan
setInterval(() => {
  sweepUnexecutedAndOverdueJobs();
}, 15000);

// Reload penuh tiap jam sebagai jaring pengaman + clear logs
setInterval(async () => {
  await loadCronJobs();
  await clearOldLogsIfEnabled();
}, 3600000);

// Check domain health every hour
setInterval(() => {
  checkAndUpdateRTODomains();
}, 3600000);

// Auto-delete RTO cronjobs every hour
setInterval(() => {
  autoDeleteRTOCronjobs();
}, 3600000);
