// Perbesar threadpool libuv sebelum modul apa pun memakainya (dns.lookup, fs, crypto).
// Default 4 terlalu kecil untuk ribuan job per jam.
process.env.UV_THREADPOOL_SIZE = process.env.UV_THREADPOOL_SIZE || '64';

require('./cron');
console.log('Node cron worker started...');
