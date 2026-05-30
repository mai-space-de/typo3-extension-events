<?php

declare(strict_types=1);

return [
    \Maispace\MaiEvents\Domain\Model\Event::class => [
        'tableName' => 'tx_maievents_event',
    ],
    \Maispace\MaiEvents\Domain\Model\EventRecord::class => [
        'tableName' => 'tx_maievents_event',
    ],
    \Maispace\MaiEvents\Domain\Model\Registration::class => [
        'tableName' => 'tx_maievents_registration',
    ],
];
