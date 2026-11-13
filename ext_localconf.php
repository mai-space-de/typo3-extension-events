<?php

defined('TYPO3') or die();

(static function (): void {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'MaiEvents',
        'ICalExport',
        [
            \Maispace\MaiEvents\Controller\EventsController::class => 'icalExport',
        ],
        [
            \Maispace\MaiEvents\Controller\EventsController::class => 'icalExport',
        ],
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'MaiEvents',
        'List',
        [
            \Maispace\MaiEvents\Controller\EventsController::class => 'list',
        ],
        [
            // Cacheable in its default state only. Requests carrying the cHash-excluded
            // view/date parameters disable caching at runtime (see
            // EventsController::applyCachePolicy), so a crafted variant is never stored.
        ],
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
    );

    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'MaiEvents',
        'Registration',
        [
            \Maispace\MaiEvents\Controller\RegistrationController::class => 'show, register, confirm',
        ],
        [
            \Maispace\MaiEvents\Controller\RegistrationController::class => 'register',
        ],
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['mai_events']
        = \Maispace\MaiEvents\Hook\EventCacheInvalidationHook::class;

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['mai_events']
        = \Maispace\MaiEvents\Hook\EventCacheInvalidationHook::class;

    // The Calendar view/date navigation reads these directly from $_GET
    // (see EventsDataProcessor) instead of via a route enhancer, so they
    // must be excluded from cHash validation or every navigation link 404s.
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'tx_maievents_view';
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'tx_maievents_date';
})();
