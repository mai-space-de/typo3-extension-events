<?php

declare(strict_types=1);

return [
    \Maispace\MaiEvents\Domain\Model\Event::class => [
        // Calendar DTO — not persisted; mapping kept for historical Extbase config.
        'tableName' => 'pages',
    ],
    \Maispace\MaiEvents\Domain\Model\EventRecord::class => [
        'tableName' => 'pages',
        'properties' => [
            'title' => [
                'fieldName' => 'title',
            ],
            'description' => [
                'fieldName' => 'abstract',
            ],
            'location' => [
                'fieldName' => 'tx_maievents_location',
            ],
            'startDate' => [
                'fieldName' => 'tx_maievents_start_date',
            ],
            'endDate' => [
                'fieldName' => 'tx_maievents_end_date',
            ],
            'registrationDeadline' => [
                'fieldName' => 'tx_maievents_registration_deadline',
            ],
            'recurrenceFrequency' => [
                'fieldName' => 'tx_maievents_recurrence_frequency',
            ],
            'recurrenceUntil' => [
                'fieldName' => 'tx_maievents_recurrence_until',
            ],
            'recurrenceMonthWeekday' => [
                'fieldName' => 'tx_maievents_recurrence_month_weekday',
            ],
            'maxAttendees' => [
                'fieldName' => 'tx_maievents_max_attendees',
            ],
            'hasWaitingList' => [
                'fieldName' => 'tx_maievents_has_waiting_list',
            ],
            'image' => [
                'fieldName' => 'tx_maievents_image',
            ],
            'doktype' => [
                'fieldName' => 'doktype',
            ],
            // link is no longer persisted (page IS the target); kept on the model for unit tests / BC.
        ],
    ],
    \Maispace\MaiEvents\Domain\Model\Registration::class => [
        'tableName' => 'tx_maievents_registration',
    ],
];
