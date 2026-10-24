<?php

declare(strict_types=1);

use Maispace\MaiBase\Icon\StreamlineSvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

$allIcons = 'EXT:mai_base/Resources/Public/Icons/all-icons/';

return [
    'ext-maispace-mai_events' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:mai_events/Resources/Public/Icons/Extension.svg',
    ],
    'mai-events' => [
        'provider' => StreamlineSvgIconProvider::class,
        'source' => $allIcons . 'interface-calendar--blank-calendar-date-day-month--Streamline-Core.svg',
    ],
    'mai-events-registration' => [
        'provider' => StreamlineSvgIconProvider::class,
        'source' => $allIcons . 'interface-calendar-check--approve-calendar-check-date-day-month-success--Streamline-Core.svg',
    ],
];
