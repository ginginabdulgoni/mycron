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

// Concurrency limiter for per-minute jobs
const minuteLimit = pLimit(20); // Max 20 concurrent jobs for per-minute schedules

// DNS cache untuk request job.
// dns.lookup() default memakai threadpool libuv (4 thread) + resolv.conf glibc. Saat
// nameserver pertama sering timeout, ribuan lookup antre di 4 thread itu dan SEMUA job
// kena "timeout of 15000ms exceeded" tanpa pernah membuka koneksi TCP.
// Di sini pakai c-ares (dns.Resolver, async, tidak memakai threadpool) + cache per host.
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
      const addrs = await resolver.resolve4(hostname);
      const entry = { address: addrs[0], family: 4, expires: Date.now() + DNS_TTL_MS };
      dnsCache.set(hostname, entry);
      return entry;
    } catch (err) {
      // Fallback ke resolver sistem (mis. /etc/hosts atau host lokal)
      try {
        const r = await dns.promises.lookup(hostname, { family: 4 });
        const entry = { address: r.address, family: 4, expires: Date.now() + DNS_TTL_MS };
        dnsCache.set(hostname, entry);
        return entry;
      } catch (lookupErr) {
        dnsCache.set(hostname, { error: lookupErr, expires: Date.now() + DNS_NEGATIVE_TTL_MS });
        throw lookupErr;
      }
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

const jobHttpAgent = new http.Agent({ lookup: cachedLookup });
const jobHttpsAgent = new https.Agent({ lookup: cachedLookup });

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
    // Try both HTTP and HTTPS
    const protocols = ['https://', 'http://'];

    for (const protocol of protocols) {
      try {
        const response = await axios.head(`${protocol}${domain}`, {
          timeout: 3000,
          httpAgent: jobHttpAgent,
          httpsAgent: jobHttpsAgent,
          maxRedirects: 3,
          validateStatus: function (status) {
            return status < 500; // Accept anything except 5xx errors
          }
        });
        return true; // Domain is reachable
      } catch (err) {
        // Try next protocol
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
    // First try ICMP ping
    const res = await ping.promise.probe(domain, {
      timeout: 2,
      extra: ['-c', '2']
    });

    if (res.alive) {
      return true;
    }

    // If ping fails, try HTTP HEAD request (for same-server domains)
    console.log(`⚠️ Ping failed for ${domain}, trying HTTP check...`);
    const httpAlive = await checkDomainHTTP(domain);

    if (httpAlive) {
      console.log(`✅ ${domain} is reachable via HTTP (same-server/local domain)`);
      return true;
    }

    return false;
  } catch (err) {
    console.error(`❌ Ping error for ${domain}:`, err.message);

    // Last attempt: try HTTP check
    return await checkDomainHTTP(domain);
  }
}

// Check and update RTO domains
async function checkAndUpdateRTODomains() {
  try {
    await setTimezoneFromDatabase(); // Refresh timezone so last_checked_at uses latest setting
    console.log('🔍 Checking domain health...');

    // Get excluded domains from settings
    const [excludedSettings] = await db.query(`
      SELECT value FROM settings WHERE \`key\` = 'rto_excluded_domains' LIMIT 1
    `);
    const excludedDomainsStr = excludedSettings[0]?.value || '';
    const excludedDomains = excludedDomainsStr.split('\n')
      .map(d => d.trim().toLowerCase())
      .filter(d => d.length > 0);

    if (excludedDomains.length > 0) {
      console.log(`📝 Excluded domains: ${excludedDomains.join(', ')}`);
    }

    // Get all unique domains from active cronjobs
    const [jobs] = await db.query(`
      SELECT DISTINCT url FROM cronjobs WHERE active = 1
    `);

    const domains = new Set();
    jobs.forEach(job => {
      const domain = extractDomain(job.url);
      if (domain) domains.add(domain);
    });

    console.log(`📊 Found ${domains.size} unique domains to check`);

    for (const domain of domains) {
      // Skip excluded domains
      if (excludedDomains.includes(domain.toLowerCase())) {
        console.log(`⏭️ Skipping excluded domain: ${domain}`);
        // Remove from RTO if it was there
        await db.query('DELETE FROM rto_domains WHERE domain = ?', [domain]);
        continue;
      }

      const isAlive = await pingDomain(domain);

      if (!isAlive) {
        console.log(`❌ RTO detected: ${domain}`);

        // Check if domain already exists in rto_domains
        const [existing] = await db.query(
          'SELECT id, rto_count FROM rto_domains WHERE domain = ?',
          [domain]
        );

        if (existing.length > 0) {
          // Update existing record
          await withTimezoneConnection((connection) =>
            connection.query(
              'UPDATE rto_domains SET rto_count = rto_count + 1, last_checked_at = NOW(), updated_at = NOW() WHERE domain = ?',
              [domain]
            )
          );
          console.log(`📝 Updated RTO count for ${domain}`);
        } else {
          // Insert new record
          await withTimezoneConnection((connection) =>
            connection.query(
              'INSERT INTO rto_domains (domain, rto_count, first_detected_at, last_checked_at, created_at, updated_at) VALUES (?, 1, NOW(), NOW(), NOW(), NOW())',
              [domain]
            )
          );
          console.log(`✅ Added ${domain} to RTO domains`);
        }
      } else {
        console.log(`✅ ${domain} is alive`);

        // Remove from RTO if it was there (domain recovered)
        await db.query('DELETE FROM rto_domains WHERE domain = ?', [domain]);
      }
    }
  } catch (err) {
    console.error('❌ Error checking RTO domains:', err.message);
  }
}

// Auto-delete cronjobs for RTO domains that exceed the age limit
async function autoDeleteRTOCronjobs() {
  try {
    // Check if auto-delete is enabled
    const [settings] = await db.query(`
      SELECT
        MAX(CASE WHEN \`key\` = 'auto_delete_rto_active' THEN value ELSE NULL END) AS active,
        MAX(CASE WHEN \`key\` = 'auto_delete_rto_days' THEN value ELSE NULL END) AS days
      FROM settings
    `);

    const autoDeleteActive = settings[0]?.active == 1 || settings[0]?.active == '1' || settings[0]?.active === true;
    const days = parseInt(settings[0]?.days || 3);

    if (!autoDeleteActive) {
      console.log('🗑️ Auto-delete RTO cronjobs not active');
      return;
    }

    console.log(`🗑️ Checking RTO domains older than ${days} days...`);

    // Get RTO domains that are older than the specified days
    const [rtoDomains] = await db.query(`
      SELECT domain, DATEDIFF(NOW(), first_detected_at) as age_days
      FROM rto_domains
      WHERE first_detected_at IS NOT NULL
        AND DATEDIFF(NOW(), first_detected_at) >= ?
    `, [days]);

    if (rtoDomains.length === 0) {
      console.log('✅ No old RTO domains found');
      return;
    }

    let totalDeleted = 0;

    for (const rto of rtoDomains) {
      console.log(`🗑️ Deleting cronjobs for domain: ${rto.domain} (${rto.age_days} days old)`);

      // Delete all cronjobs for this domain
      const [result] = await db.query(
        'DELETE FROM cronjobs WHERE url LIKE ?',
        [`%${rto.domain}%`]
      );

      totalDeleted += result.affectedRows;

      // Delete the RTO domain record
      await db.query('DELETE FROM rto_domains WHERE domain = ?', [rto.domain]);

      console.log(`✅ Deleted ${result.affectedRows} cronjob(s) for ${rto.domain}`);
    }

    console.log(`🗑️ Total cronjobs deleted: ${totalDeleted}`);
  } catch (err) {
    console.error('❌ Error auto-deleting RTO cronjobs:', err.message);
  }
}

const scheduledTasks = {};
const runningJobs = new Set();

// Tanda tangan job: kalau salah satu field ini berubah, job dijadwalkan ulang.
function jobSignature(job) {
  return [job.schedule, job.url, job.name, job.save_logs ? 1 : 0].join('|');
}

async function loadCronJobs() {
 const [rows] = await db.query(`
  SELECT * FROM cronjobs 
  WHERE active = 1
  ORDER BY (last_run_at IS NULL) DESC, RAND()
`);


  for (const job of rows) {
    const existing = scheduledTasks[job.id];

    if (existing) {
      if (existing.signature !== jobSignature(job)) {
        console.log(`🔁 Rescheduling ${job.name}`);
        existing.task.stop();
        scheduleCron(job);
      }
    } else {
      scheduleCron(job);
    }
  }

  // Hapus yang tidak aktif lagi
  Object.keys(scheduledTasks).forEach(id => {
    if (!rows.find(j => j.id == id)) {
      console.log(`⛔ Unscheduling job ID ${id}`);
      scheduledTasks[id].task.stop();
      delete scheduledTasks[id];
    }
  });
}

function scheduleCron(job) {
  const task = cron.schedule(job.schedule, async () => {
    const lockKey = String(job.id);
    if (runningJobs.has(lockKey)) {
      console.log(`Skipping overlap: ${job.name}`);
      return;
    }
    runningJobs.add(lockKey);

    try {
      const currentTime = new Intl.DateTimeFormat('id-ID', {
      timeZone: process.env.TZ,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    }).format(new Date());

    console.log(`[⏱ ${currentTime}] Running: ${job.name}`);

    // Execute job with concurrency control for per-minute schedules
    const executeJob = async () => {
      const utcNow = new Date().toISOString().slice(0, 19).replace('T', ' ');
      try {
        // Add timeout to axios request (30 seconds)
        const res = await axios.get(job.url, {
          timeout: 15000,
          httpAgent: jobHttpAgent,
          httpsAgent: jobHttpsAgent,
          maxRedirects: 5,
          validateStatus: function (status) {
            return status >= 200 && status < 500; // Accept 2xx, 3xx, 4xx
          }
        });

        // Update last_run_at in DB (using UTC)
        await db.query(
          'UPDATE cronjobs SET last_run_at = ? WHERE id = ?',
          [utcNow, job.id]
        );

        if (job.save_logs) {
          await db.query(
            'INSERT INTO cron_logs (cronjob_id, status, response, response_body, run_at) VALUES (?, ?, ?, ?, ?)',
            [job.id, 'success', res.status, JSON.stringify(res.data).substring(0, 10000), utcNow]
          );
        }
      } catch (err) {
        console.error(`❌ Error executing ${job.name}:`, err.message);

        // Update last_run_at even on error so the UI reflects the latest attempt
        try {
          await db.query(
            'UPDATE cronjobs SET last_run_at = ? WHERE id = ?',
            [utcNow, job.id]
          );
        } catch (updateErr) {
          console.error(`❌ Failed to update last_run_at for ${job.name}:`, updateErr.message);
        }

        if (job.save_logs) {
          await db.query(
            'INSERT INTO cron_logs (cronjob_id, status, response, response_body, run_at) VALUES (?, ?, ?, ?, ?)',
            [job.id, 'error', err.message, err.response ? JSON.stringify(err.response.data).substring(0, 10000) : null, utcNow]
          );
        }
      }
    };

    // Use concurrency limiter for per-minute jobs
      if (job.schedule === '* * * * *' || job.schedule === '*/1 * * * *') {
        await minuteLimit(executeJob);
      } else {
        await executeJob();
      }
    } finally {
      runningJobs.delete(lockKey);
    }
  });

  scheduledTasks[job.id] = {
    task,
    schedule: job.schedule,
    signature: jobSignature(job)
  };

  console.log(`✅ Scheduled: ${job.name} [${job.schedule}]`);
}


(async () => {
  console.log('🚀 Starting Cron Worker...');
  await setTimezoneFromDatabase();     // 🔁 Ambil timezone dari DB
  await loadCronJobs();                // ⏱️ Load cronjob
  await clearOldLogsIfEnabled();       // 🧹 Jalankan clear logs jika aktif
  await checkAndUpdateRTODomains();    // 🔍 Check domain health
  await autoDeleteRTOCronjobs();       // 🗑️ Auto-delete old RTO cronjobs
  console.log('✅ Cron Worker initialized successfully');
  await system.start({
    reloadCronJobs: loadCronJobs,
    jobCount: () => Object.keys(scheduledTasks).length,
  });
})();

// Deteksi cronjob baru/diubah/dihapus tanpa perlu restart worker.
// Query ringan tiap 15 detik; daftar job hanya dimuat ulang bila sidik jarinya berubah.
// (last_run_at diupdate via SQL mentah sehingga tidak menyentuh updated_at.)
let cronFingerprint = null;
setInterval(async () => {
  try {
    const [rows] = await db.query(
      'SELECT COUNT(*) AS n, COALESCE(SUM(id), 0) AS ids, MAX(updated_at) AS updated FROM cronjobs WHERE active = 1'
    );
    const fp = `${rows[0].n}|${rows[0].ids}|${rows[0].updated ? new Date(rows[0].updated).getTime() : 0}`;
    if (cronFingerprint !== null && fp !== cronFingerprint) {
      console.log('✅ Perubahan cronjob terdeteksi, memuat ulang daftar job...');
      await loadCronJobs();
    }
    cronFingerprint = fp;
  } catch (err) {
    console.error('❌ Cek perubahan cronjob gagal:', err.message);
  }
}, 15000);

// Reload penuh tiap jam sebagai jaring pengaman + clear logs
setInterval(async () => {
  await loadCronJobs();
  await clearOldLogsIfEnabled();
}, 3600000);

// Check domain health every 10 minutes
setInterval(async () => {
  await checkAndUpdateRTODomains();
}, 3600000);

// Auto-delete RTO cronjobs every hour
setInterval(async () => {
  await autoDeleteRTOCronjobs();
}, 3600000);
