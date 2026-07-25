<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\DataProcessor;

use Maispace\MaiEvents\EventProvider\EventProviderInterface;
use Maispace\MaiEvents\Service\CalendarService;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * EventsDataProcessor aggregates events from all registered EventProviderInterface
 * implementations and structures them for the FullCalendar Fluid mount.
 *
 * Supported view modes (FlexForm / GET → FullCalendar initialView):
 *   - month  → dayGridMonth (default)
 *   - week   → timeGridWeek
 *   - list   → listWeek
 *
 * TypoScript / FlexForm options (all optional):
 *   viewMode        = month | week | list      (default: month)
 *   targetVariable  = calendar                 (default: calendar)
 *   date            = Y-m-d                    (default: today)
 *   listLimit       = 10                        (kept for FlexForm compat; unused by FC preload)
 *   categoryUid     = 0                         (default: 0 = all categories)
 *
 * The processed variable structure passed to the template:
 *   {calendar.viewMode}             string
 *   {calendar.initialView}          string (FullCalendar view name)
 *   {calendar.currentDate}          \DateTimeImmutable
 *   {calendar.start} / {calendar.end}  preload window
 *   {calendar.events}               Event[]
 *   {calendar.fullCalendarEvents}   array (JSON-serializable FC events)
 *   {calendar.contentUid}           int – tt_content uid for #c{uid}
 *   {calendar.locale}               string – FC locale (de, en, uk, ar, …)
 */
class EventsDataProcessor implements DataProcessorInterface
{
    private readonly CalendarService $calendarService;

    /**
     * @param iterable<EventProviderInterface>|null $providers Explicit providers (mainly for tests);
     *                                                          null resolves providers from the container.
     */
    public function __construct(?iterable $providers = null)
    {
        $this->calendarService = new CalendarService($providers);
    }

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $targetVariable = (string) ($processorConfiguration['targetVariable'] ?? 'calendar');

        // Allow override via GET parameter, e.g. ?tx_maievents_view=week&tx_maievents_date=2024-06-01
        $requestedViewMode = is_string($_GET['tx_maievents_view'] ?? null) ? $_GET['tx_maievents_view'] : null;
        $requestedDate = is_string($_GET['tx_maievents_date'] ?? null) ? $_GET['tx_maievents_date'] : null;

        $calendar = $this->calendarService->buildCalendar(
            requestedViewMode: $requestedViewMode,
            requestedDate: $requestedDate,
            configuredViewMode: (string) ($processorConfiguration['viewMode'] ?? 'month'),
            configuredDate: (string) ($processorConfiguration['date'] ?? ''),
            listLimit: (int) ($processorConfiguration['listLimit'] ?? 10),
            categoryUid: (int) ($processorConfiguration['categoryUid'] ?? 0),
            contentUid: (int) ($cObj->data['uid'] ?? 0),
        );
        $calendar['locale'] = $this->resolveLocale($cObj);

        $processedData[$targetVariable] = $calendar;

        return $processedData;
    }

    private function resolveLocale(ContentObjectRenderer $cObj): string
    {
        $language = $cObj->getRequest()->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            $hreflang = $language->getHreflang();
            if ($hreflang !== '') {
                return strtolower(substr($hreflang, 0, 2));
            }
            $locale = $language->getLocale()->getName();
            if ($locale !== '') {
                return strtolower(substr($locale, 0, 2));
            }
        }

        return 'de';
    }
}
