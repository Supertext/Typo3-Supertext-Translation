<?php

// Public ping endpoint for the Supertext Commando Center (GET /supertext/ping?challenge=…).
// Before the site resolver: no login, no session, no page cache.
return [
    'frontend' => [
        'supertext/translation/ping' => [
            'target' => \Supertext\Typo3Translation\Middleware\PingMiddleware::class,
            'after' => [
                'typo3/cms-core/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/eid',
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
