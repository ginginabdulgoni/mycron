const cron = require('node-cron');
const db = require('./db');
const axios = require('axios');
// Default fallback
process.env.TZ = 'UTC';

async function setTimezoneFromDatabase() {
  try {
    const [rows] = await db.query("SELECT value FROM settings WHERE `key` = 'timezone' LIMIT 1");
    const timezone = rows[0]?.value || 'UTC';

    process.env.TZ = timezone;
    console.log(`🌍 Timezone set to: ${timezone}`);
  } catch (err) {
    console.warn('⚠️ Gagal mengambil timezone dari database, menggunakan UTC');
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

const scheduledTasks = {};

async function loadCronJobs() {
  const [rows] = await db.query('SELECT * FROM cronjobs WHERE active = 1');

  for (const job of rows) {
    const existing = scheduledTasks[job.id];

    if (existing) {
      if (existing.schedule !== job.schedule) {
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
try {
  const res = await axios.get(job.url);
   // ✅ Update last_run_at di DB
      await db.query(
        'UPDATE cronjobs SET last_run_at = ? WHERE id = ?',
        [new Date(), job.id]
      );
  if (job.save_logs) {
    await db.query(
      'INSERT INTO cron_logs (cronjob_id, status, response, response_body, run_at) VALUES (?, ?, ?, ?, ?)',
      [job.id, 'success', res.status, res.data, new Date()]
    );
  }
} catch (err) {
  if (job.save_logs) {
    await db.query(
      'INSERT INTO cron_logs (cronjob_id, status, response, response_body, run_at) VALUES (?, ?, ?, ?, ?)',
      [job.id, 'error', err.message, err.response ? err.response.data : null, new Date()]
    );
  }
}

  });

  scheduledTasks[job.id] = {
    task,
    schedule: job.schedule
  };

  console.log(`✅ Scheduled: ${job.name} [${job.schedule}]`);
}


(async () => {
  await setTimezoneFromDatabase();  // 🔁 Ambil timezone dari DB
  await loadCronJobs();             // ⏱️ Load cronjob
    await clearOldLogsIfEnabled();  // ✅ Jalankan clear logs jika aktif
})();

setInterval(async () => {
  await loadCronJobs();
  await clearOldLogsIfEnabled();
}, 30000);



