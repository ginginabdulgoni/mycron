<?php

return [
    'branch' => env('DEPLOY_BRANCH', 'main'),
    'github_repo' => env('DEPLOY_GITHUB_REPO', 'ginginabdulgoni/mycron'),
    // Token opsional (repo private / hindari rate limit GitHub API 60 req/jam)
    'github_token' => env('DEPLOY_GITHUB_TOKEN'),
    // Worker dianggap offline bila heartbeat lebih tua dari ini (detik)
    'heartbeat_timeout' => 60,
];
