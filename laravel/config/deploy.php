<?php

return [
    'repo_url' => env('DEPLOY_REPO_URL', 'https://github.com/ginginabdulgoni/mycron.git'),
    'branch' => env('DEPLOY_BRANCH', 'main'),
    'github_repo' => env('DEPLOY_GITHUB_REPO', 'ginginabdulgoni/mycron'),
    'mirror_path' => storage_path('app/deploy-mirror'),
    'node_worker_path' => base_path('node-worker'),
];
