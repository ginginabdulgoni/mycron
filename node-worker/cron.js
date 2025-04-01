const cron = require('node-cron');
const db = require('./db');
const axios = require('axios');

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
    console.log(`[⏱ ${new Date().toISOString()}] Running: ${job.name}`);

    try {
      const res = await axios.get(job.url);
      await db.query(
        'INSERT INTO cron_logs (cronjob_id, status, response, run_at) VALUES (?, ?, ?, NOW())',
        [job.id, 'success', res.status]
      );
    } catch (err) {
      await db.query(
        'INSERT INTO cron_logs (cronjob_id, status, response, run_at) VALUES (?, ?, ?, NOW())',
        [job.id, 'error', err.message]
      );
    }
  });

  scheduledTasks[job.id] = {
    task,
    schedule: job.schedule
  };

  console.log(`✅ Scheduled: ${job.name} [${job.schedule}]`);
}

// Pertama kali load
loadCronJobs();

// Cek ulang setiap 30 detik
setInterval(loadCronJobs, 30000);
