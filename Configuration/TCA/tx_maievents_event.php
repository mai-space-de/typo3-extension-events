<?php

declare(strict_types=1);

use Maispace\MaiBase\TableConfigurationArray\FieldConfig\CategoryConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\CheckboxConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\DatetimeConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\FileConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\InputConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\LinkConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\NumberConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\SelectSingleConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\TextConfig;
use Maispace\MaiBase\TableConfigurationArray\Helper;
use Maispace\MaiBase\TableConfigurationArray\Table;

$lang = Helper::localLangHelperFactory('mai_events', 'Default/locallang_tca.xlf');

return (new Table($lang('table.tx_maievents_event')))
    ->setDefaultConfig()
    ->setLabel('title')
    ->setAlternativeLabelFields('start_date')
    ->setIconFile('EXT:mai_base/Resources/Public/Icons/generic_table.svg')
    ->setDefaultSorting('ORDER BY start_date ASC')
    ->setThumbnailField('image')
    ->addColumn(
        'title',
        $lang('tx_maievents_event.title'),
        (new InputConfig())->setSize(50)->setMax(255)->setEval('trim')->setRequired()
    )
    ->addColumn(
        'description',
        $lang('tx_maievents_event.description'),
        (new TextConfig())->setRows(10)->setCols(50)->enableRte()->setRichtextConfiguration('default')
    )
    ->addColumn(
        'location',
        $lang('tx_maievents_event.location'),
        (new InputConfig())->setSize(50)->setMax(255)->setEval('trim')
    )
    ->addColumn(
        'link',
        $lang('tx_maievents_event.link'),
        (new LinkConfig())->setAllowedTypes(['page', 'url', 'file'])
    )
    ->addColumn(
        'start_date',
        $lang('tx_maievents_event.start_date'),
        (new DatetimeConfig())->setFormat('datetime')->setRequired()
    )
    ->addColumn(
        'end_date',
        $lang('tx_maievents_event.end_date'),
        (new DatetimeConfig())->setFormat('datetime')
    )
    ->addColumn(
        'registration_deadline',
        $lang('tx_maievents_event.registration_deadline'),
        (new DatetimeConfig())->setFormat('datetime')
    )
    ->addColumn(
        'recurrence_frequency',
        $lang('tx_maievents_event.recurrence_frequency'),
        (new SelectSingleConfig())
            ->setItems([
                ['label' => $lang('tx_maievents_event.recurrence_frequency.none'), 'value' => ''],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.daily'), 'value' => 'daily'],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.weekly'), 'value' => 'weekly'],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.monthly'), 'value' => 'monthly'],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.monthly_weekday'), 'value' => 'monthly_weekday'],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.yearly'), 'value' => 'yearly'],
            ])
            ->setDefault(''),
        '',
        '',
        false,
        '',
        '',
        'reload',
    )
    ->addColumn(
        'recurrence_month_weekday',
        $lang('tx_maievents_event.recurrence_month_weekday'),
        (new SelectSingleConfig())
            ->setItems([
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.1'), 'value' => 1],
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.2'), 'value' => 2],
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.3'), 'value' => 3],
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.4'), 'value' => 4],
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.last'), 'value' => -1],
            ])
            ->setDefault(1),
        '',
        'FIELD:recurrence_frequency:=:monthly_weekday',
    )
    ->addColumn(
        'recurrence_until',
        $lang('tx_maievents_event.recurrence_until'),
        (new DatetimeConfig())->setFormat('datetime'),
        '',
        'FIELD:recurrence_frequency:!=:',
    )
    ->addColumn(
        'max_attendees',
        $lang('tx_maievents_event.max_attendees'),
        (new NumberConfig())->setFormat('integer')->setDefault(0)
    )
    ->addColumn(
        'has_waiting_list',
        $lang('tx_maievents_event.has_waiting_list'),
        (new CheckboxConfig())->setRenderType('checkboxToggle')->setDefault(0)
    )
    ->addColumn(
        'image',
        $lang('tx_maievents_event.image'),
        (new FileConfig())
            ->setAllowed('common-image-types')
            ->setMaxItems(1)
            ->setAppearance([
                'createNewRelationLinkTitle' => $lang('tx_maievents_event.image.addFile'),
            ])
    )
    ->addColumn(
        'categories',
        $lang('tx_maievents_event.categories'),
        new CategoryConfig()
    )
    ->addPalette(
        'dates',
        $lang('palette.dates'),
        'start_date, end_date, registration_deadline'
    )
    ->addPalette(
        'recurrence',
        $lang('palette.recurrence'),
        'recurrence_frequency, recurrence_month_weekday, recurrence_until'
    )
    ->addPalette(
        'registration',
        $lang('palette.registration'),
        'max_attendees, has_waiting_list'
    )
    ->addTypeShowItem(
        '0',
        'title, description, location, link, image, categories,
        --div--;' . $lang('tab.dates') . ', --palette--;;dates, --palette--;;recurrence,
        --div--;' . $lang('tab.registration') . ', --palette--;;registration,
        --div--;' . $lang('tab.language') . ', --palette--;;language,
        --div--;' . $lang('tab.access') . ', --palette--;;hidden, --palette--;;access'
    )
    ->getConfig();
