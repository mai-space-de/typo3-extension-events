<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Maispace\MaiBase\TableConfigurationArray\CType;
use Maispace\MaiBase\TableConfigurationArray\Helper;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

$lang = Helper::localLangHelperFactory('mai_events', 'Default/locallang_tca.xlf');

// Register the "Events View" content element (DataProcessor-based, not Extbase)
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPlugin(
    [
        'label' => 'LLL:EXT:mai_events/Resources/Private/Language/locallang_db.xlf:tt_content.CType.mai_events_view',
        'value' => 'mai_events_view',
        'icon' => 'mai-content',
        'group' => 'default',
    ],
    'CType',
    'mai_events'
);

// Show FlexForm field and hide unused standard fields
$GLOBALS['TCA']['tt_content']['types']['mai_events_view'] = [
    'showitem' => '
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
            --palette--;;general,
            header,
            pi_flexform,
        --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
            --palette--;;hidden,
            --palette--;;access,
    ',
    'columnsOverrides' => [
        'pi_flexform' => [
            'label' => 'LLL:EXT:mai_events/Resources/Private/Language/locallang_db.xlf:tt_content.pi_flexform.mai_events_view',
            'config' => [
                'ds' => 'FILE:EXT:mai_events/Configuration/FlexForms/Events.xml',
            ],
        ],
    ],
];

ExtensionUtility::registerPlugin(
    'MaiEvents',
    'List',
    $lang('plugin.list.title'),
    'mai-content',
    'maispace_feature',
);

(new CType('maispace_events_list', $lang('ctype.events_list'), 'mai-content'))
    ->addDefaultHeaderPalette()
    ->addCustomFields('pi_flexform')
    ->addDefaultLanguageTab()
    ->addDefaultAccessTab()
    ->setGroup('maispace_feature')
    ->register();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue(
    '*',
    'FILE:EXT:mai_events/Configuration/FlexForms/Events.xml',
    'maispace_events_list',
);

// Register the "Event Registration" Extbase plugin content element
ExtensionUtility::registerPlugin(
    'MaiEvents',
    'Registration',
    'LLL:EXT:mai_events/Resources/Private/Language/locallang_db.xlf:plugin.registration.title',
    'mai-content',
    'default',
);
