<?php

declare(strict_types=1);

use Maispace\MaiBase\TableConfigurationArray\FieldConfig\DatetimeConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\EmailConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\InputConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\SelectSingleConfig;
use Maispace\MaiBase\TableConfigurationArray\Helper;
use Maispace\MaiBase\TableConfigurationArray\Table;

$lang = Helper::localLangHelperFactory('mai_events', 'Default/locallang_tca.xlf');

return (new Table($lang('table.tx_maievents_registration')))
    ->setDefaultConfig()
    ->setLabel('last_name')
    ->setAlternativeLabelFields('first_name, email')
    ->appendAlternativeLabelToLabel()
    ->setIconFile('EXT:mai_base/Resources/Public/Icons/all-icons/interface-calendar-check--approve-calendar-check-date-day-month-success--Streamline-Core.svg')

    ->setDefaultSorting('ORDER BY registered_at DESC')
    ->addColumn(
        'event',
        $lang('tx_maievents_registration.event'),
        (new SelectSingleConfig())
            ->setForeignTable('pages')
            ->setForeignTableWhere(
                'AND {#pages}.{#doktype} = 121 AND {#pages}.{#sys_language_uid} = 0'
                . ' AND {#pages}.{#deleted} = 0 ORDER BY {#pages}.{#tx_maievents_start_date} DESC'
            )
            ->setMinItems(1)
            ->setMaxItems(1)
    )
    ->addColumn(
        'occurrence_start',
        $lang('tx_maievents_registration.occurrence_start'),
        (new DatetimeConfig())->setFormat('datetime')
    )
    ->addColumn(
        'first_name',
        $lang('tx_maievents_registration.first_name'),
        (new InputConfig())->setSize(30)->setMax(100)->setEval('trim')->setRequired()
    )
    ->addColumn(
        'last_name',
        $lang('tx_maievents_registration.last_name'),
        (new InputConfig())->setSize(30)->setMax(100)->setEval('trim')->setRequired()
    )
    ->addColumn(
        'email',
        $lang('tx_maievents_registration.email'),
        (new EmailConfig())->setRequired()
    )
    ->addColumn(
        'status',
        $lang('tx_maievents_registration.status'),
        (new SelectSingleConfig())
            ->setItems([
                ['label' => $lang('tx_maievents_registration.status.registered'), 'value' => 'registered'],
                ['label' => $lang('tx_maievents_registration.status.waiting'), 'value' => 'waiting'],
                ['label' => $lang('tx_maievents_registration.status.cancelled'), 'value' => 'cancelled'],
            ])
            ->setDefault('registered')
    )
    ->addColumn(
        'registered_at',
        $lang('tx_maievents_registration.registered_at'),
        (new DatetimeConfig())->setFormat('datetime')->setReadOnly()
    )
    ->addColumn(
        'confirmed_at',
        $lang('tx_maievents_registration.confirmed_at'),
        (new DatetimeConfig())->setFormat('datetime')->setReadOnly()
    )
    ->addPalette(
        'name',
        $lang('palette.name'),
        'first_name, last_name'
    )
    ->addPalette(
        'dates',
        $lang('palette.dates'),
        'registered_at, confirmed_at'
    )
    ->addTypeShowItem(
        '0',
        'event, occurrence_start, --palette--;;name, email, status, --palette--;;dates'
    )
    ->getConfig();
