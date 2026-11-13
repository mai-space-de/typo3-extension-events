<?php

declare(strict_types=1);

use Maispace\MaiEvents\Middleware\BypassPageCacheForCalendarNavigationMiddleware;

return [
    'frontend' => [
        'maispace/mai-events/bypass-page-cache-for-calendar-navigation' => [
            'target' => BypassPageCacheForCalendarNavigationMiddleware::class,
            'before' => [
                'typo3/cms-frontend/page-argument-validator',
                'typo3/cms-frontend/prepare-tsfe-rendering',
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
