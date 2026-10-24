<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Maispace\MaiBase\Backend\CustomPageModule\DoktypeIds;
use Maispace\MaiBase\TableConfigurationArray\Field;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\CheckboxConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\DatetimeConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\FileConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\InputConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\NumberConfig;
use Maispace\MaiBase\TableConfigurationArray\FieldConfig\SelectSingleConfig;
use Maispace\MaiBase\TableConfigurationArray\Helper;
use Maispace\MaiBase\TableConfigurationArray\Palette;

$lang = Helper::localLangHelperFactory('mai_events', 'Default/locallang_tca.xlf');
$eventDoktype = (string) DoktypeIds::EVENT;

(new Field('pages', 'tx_maievents_location', $lang('tx_maievents_event.location'), true))
    ->setConfig((new InputConfig())->setSize(50)->setMax(255)->setEval('trim'))
    ->registerField();

(new Field('pages', 'tx_maievents_start_date', $lang('tx_maievents_event.start_date'), true))
    ->setConfig((new DatetimeConfig())->setFormat('datetime')->setRequired())
    ->registerField();

(new Field('pages', 'tx_maievents_end_date', $lang('tx_maievents_event.end_date'), true))
    ->setConfig((new DatetimeConfig())->setFormat('datetime'))
    ->registerField();

(new Field('pages', 'tx_maievents_registration_deadline', $lang('tx_maievents_event.registration_deadline'), true))
    ->setConfig((new DatetimeConfig())->setFormat('datetime'))
    ->registerField();

(new Field('pages', 'tx_maievents_recurrence_frequency', $lang('tx_maievents_event.recurrence_frequency'), true))
    ->setOnChange('reload')
    ->setConfig(
        (new SelectSingleConfig())
            ->setItems([
                ['label' => $lang('tx_maievents_event.recurrence_frequency.none'), 'value' => ''],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.daily'), 'value' => 'daily'],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.weekly'), 'value' => 'weekly'],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.monthly'), 'value' => 'monthly'],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.monthly_weekday'), 'value' => 'monthly_weekday'],
                ['label' => $lang('tx_maievents_event.recurrence_frequency.yearly'), 'value' => 'yearly'],
            ])
            ->setDefault('')
    )
    ->registerField();

(new Field('pages', 'tx_maievents_recurrence_month_weekday', $lang('tx_maievents_event.recurrence_month_weekday'), true))
    ->setDisplayCondition('FIELD:tx_maievents_recurrence_frequency:=:monthly_weekday')
    ->setConfig(
        (new SelectSingleConfig())
            ->setItems([
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.1'), 'value' => 1],
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.2'), 'value' => 2],
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.3'), 'value' => 3],
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.4'), 'value' => 4],
                ['label' => $lang('tx_maievents_event.recurrence_month_weekday.last'), 'value' => -1],
            ])
            ->setDefault(1)
    )
    ->registerField();

(new Field('pages', 'tx_maievents_recurrence_until', $lang('tx_maievents_event.recurrence_until'), true))
    ->setDisplayCondition('FIELD:tx_maievents_recurrence_frequency:!=:')
    ->setConfig((new DatetimeConfig())->setFormat('datetime'))
    ->registerField();

(new Field('pages', 'tx_maievents_max_attendees', $lang('tx_maievents_event.max_attendees'), true))
    ->setConfig((new NumberConfig())->setFormat('integer')->setDefault(0))
    ->registerField();

(new Field('pages', 'tx_maievents_has_waiting_list', $lang('tx_maievents_event.has_waiting_list'), true))
    ->setConfig((new CheckboxConfig())->setRenderType('checkboxToggle')->setDefault(0))
    ->registerField();

(new Field('pages', 'tx_maievents_image', $lang('tx_maievents_event.image'), true))
    ->setConfig(
        (new FileConfig())
            ->setAllowed('common-image-types')
            ->setMaxItems(1)
            ->setAppearance([
                'createNewRelationLinkTitle' => $lang('tx_maievents_event.image.addFile'),
            ])
    )
    ->registerField();

// Migration idempotency marker (hidden from editors).
(new Field('pages', 'tx_maievents_legacy_event_uid', $lang('tx_maievents_event.legacy_event_uid'), true))
    ->setConfig((new NumberConfig())->setFormat('integer')->setDefault(0)->setReadOnly())
    ->registerField();

(new Palette('pages', 'tx_maievents_dates', $lang('palette.dates')))
    ->override('tx_maievents_start_date, tx_maievents_end_date, tx_maievents_registration_deadline');

(new Palette('pages', 'tx_maievents_recurrence', $lang('palette.recurrence')))
    ->override('tx_maievents_recurrence_frequency, tx_maievents_recurrence_month_weekday, tx_maievents_recurrence_until');

(new Palette('pages', 'tx_maievents_registration', $lang('palette.registration')))
    ->override('tx_maievents_max_attendees, tx_maievents_has_waiting_list');

$eventTabs = ',--div--;' . $lang('tab.dates')
    . ',tx_maievents_location,tx_maievents_image,'
    . '--palette--;;tx_maievents_dates,'
    . '--palette--;;tx_maievents_recurrence,'
    . '--div--;' . $lang('tab.registration')
    . ',--palette--;;tx_maievents_registration';

$showitem = (string) ($GLOBALS['TCA']['pages']['types'][$eventDoktype]['showitem'] ?? '');
if ($showitem !== '' && !str_contains($showitem, 'tx_maievents_start_date')) {
    $GLOBALS['TCA']['pages']['types'][$eventDoktype]['showitem'] = preg_replace(
        '/(--palette--;;title)/',
        '$1' . $eventTabs,
        $showitem,
        1,
    ) ?? ($showitem . $eventTabs);
}

Helper::overrideColumns('pages', $eventDoktype, 'nav_hide', [
    'config' => [
        'default' => 1,
    ],
]);
