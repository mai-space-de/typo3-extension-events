<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\DataProcessor;

use Maispace\MaiEvents\EventProvider\EventProviderInterface;
use Maispace\MaiEvents\Service\CalendarService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * EventsDataProcessor aggregates events from all registered EventProviderInterface
 * implementations and structures them for use in Fluid templates.
 *
 * Supported view modes:
 *   - month  : A full month grid (default)
 *   - week   : A single week view
 *   - list   : A flat, date-sorted list of upcoming events
 *
 * TypoScript / FlexForm options (all optional):
 *   viewMode        = month | week | list      (default: month)
 *   targetVariable  = calendar                 (default: calendar)
 *   date            = Y-m-d                    (default: today)
 *   listLimit       = 10                        (default: 10, only for list mode)
 *
 * The processed variable structure passed to the template:
 *   {calendar.viewMode}      string
 *   {calendar.currentDate}   \DateTimeImmutable
 *   {calendar.start}         \DateTimeImmutable – range start
 *   {calendar.end}           \DateTimeImmutable – range end
 *   {calendar.events}        Event[]            – all events in range
 *   {calendar.weeks}         array[]            – only in month/week mode
 *   {calendar.navigation}    array              – prev/next navigation dates
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

        $processedData[$targetVariable] = $this->calendarService->buildCalendar(
            requestedViewMode: $requestedViewMode,
            requestedDate: $requestedDate,
            configuredViewMode: (string) ($processorConfiguration['viewMode'] ?? 'month'),
            configuredDate: (string) ($processorConfiguration['date'] ?? ''),
            listLimit: (int) ($processorConfiguration['listLimit'] ?? 10),
        );

        return $processedData;
    }
}
