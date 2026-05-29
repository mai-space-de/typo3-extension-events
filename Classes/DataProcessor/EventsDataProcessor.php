<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\DataProcessor;

use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\EventProvider\EventProviderInterface;
use Maispace\MaiEvents\EventProvider\TxEventProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
    public function __construct()
    {
        // No-op constructor - DataProcessors are instantiated via GeneralUtility::makeInstance() without arguments
    }

    /**
     * @return iterable<EventProviderInterface>
     */
    private function getEventProviders(): iterable
    {
        try {
            $container = GeneralUtility::getContainer();
            if ($container->has(TxEventProvider::class)) {
                yield $container->get(TxEventProvider::class);
            }
        } catch (\Throwable) {
            // Container unavailable or error — continue with empty result
        }
    }

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $targetVariable = (string) ($processorConfiguration['targetVariable'] ?? 'calendar');
        $viewMode = $this->resolveViewMode($processorConfiguration, $processedData);
        $currentDate = $this->resolveCurrentDate($processorConfiguration, $processedData);

        [$start, $end] = $this->calculateDateRange($viewMode, $currentDate);
        $events = $this->aggregateEvents($start, $end);

        $calendarData = [
            'viewMode' => $viewMode,
            'currentDate' => $currentDate,
            'start' => $start,
            'end' => $end,
            'events' => $events,
            'navigation' => $this->buildNavigation($viewMode, $currentDate),
        ];

        if ($viewMode === 'month') {
            $calendarData['weeks'] = $this->buildMonthGrid($currentDate, $events);
        } elseif ($viewMode === 'week') {
            $calendarData['weeks'] = $this->buildWeekGrid($currentDate, $events);
        } elseif ($viewMode === 'list') {
            $limit = (int) ($processorConfiguration['listLimit'] ?? 10);
            $calendarData['events'] = array_slice($events, 0, $limit > 0 ? $limit : count($events));
        }

        $processedData[$targetVariable] = $calendarData;

        return $processedData;
    }

    /**
     * Resolves the view mode from processor configuration or request parameters.
     */
    private function resolveViewMode(array $processorConfiguration, array $processedData): string
    {
        $allowed = ['month', 'week', 'list'];

        // Allow override via GET parameter (e.g. ?tx_maievents_view=week)
        $requestMode = $_GET['tx_maievents_view'] ?? '';
        if (in_array($requestMode, $allowed, true)) {
            return $requestMode;
        }

        $configMode = (string) ($processorConfiguration['viewMode'] ?? 'month');
        if (in_array($configMode, $allowed, true)) {
            return $configMode;
        }

        return 'month';
    }

    /**
     * Resolves the reference date from processor configuration or request parameters.
     */
    private function resolveCurrentDate(array $processorConfiguration, array $processedData): \DateTimeImmutable
    {
        // Allow navigation via GET parameter (e.g. ?tx_maievents_date=2024-06-01)
        $requestDate = $_GET['tx_maievents_date'] ?? '';
        if ($requestDate !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $requestDate);
            if ($parsed !== false) {
                return $parsed->setTime(0, 0, 0);
            }
        }

        $configDate = (string) ($processorConfiguration['date'] ?? '');
        if ($configDate !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $configDate);
            if ($parsed !== false) {
                return $parsed->setTime(0, 0, 0);
            }
        }

        return new \DateTimeImmutable('today');
    }

    /**
     * Calculates the inclusive date range for the given view mode and reference date.
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function calculateDateRange(string $viewMode, \DateTimeImmutable $currentDate): array
    {
        switch ($viewMode) {
            case 'week':
                // ISO week: Monday to Sunday
                $start = $currentDate->modify('Monday this week')->setTime(0, 0, 0);
                $end = $start->modify('+6 days')->setTime(23, 59, 59);
                break;

            case 'list':
                $start = $currentDate->setTime(0, 0, 0);
                $end = $start->modify('+1 year')->setTime(23, 59, 59);
                break;

            case 'month':
            default:
                $start = $currentDate->modify('first day of this month')->setTime(0, 0, 0);
                $end = $currentDate->modify('last day of this month')->setTime(23, 59, 59);
                break;
        }

        return [$start, $end];
    }

    /**
     * Aggregates and sorts events from all registered event providers.
     *
     * @return Event[]
     */
    private function aggregateEvents(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $events = [];
        foreach ($this->getEventProviders() as $provider) {
            $providerEvents = $provider->getEvents($start, $end);
            foreach ($providerEvents as $event) {
                $events[] = $event;
            }
        }

        usort($events, static fn(Event $a, Event $b) => $a->getStart() <=> $b->getStart());

        return $events;
    }

    /**
     * Builds a week-based grid for the entire month, including leading/trailing days
     * from adjacent months to always produce full ISO weeks.
     *
     * Each week is an array of 7 day entries:
     *   [date, isCurrentMonth, isToday, events]
     *
     * @param Event[] $events
     */
    private function buildMonthGrid(\DateTimeImmutable $currentDate, array $events): array
    {
        $firstOfMonth = $currentDate->modify('first day of this month')->setTime(0, 0, 0);
        $lastOfMonth = $currentDate->modify('last day of this month')->setTime(0, 0, 0);

        // Start grid on the Monday of the week containing the 1st of the month
        $gridStart = $firstOfMonth->modify('Monday this week');
        if ($gridStart > $firstOfMonth) {
            $gridStart = $gridStart->modify('-7 days');
        }

        // End grid on the Sunday of the week containing the last day of the month
        $gridEnd = $lastOfMonth->modify('Sunday this week');
        if ($gridEnd < $lastOfMonth) {
            $gridEnd = $gridEnd->modify('+7 days');
        }

        return $this->buildGrid($gridStart, $gridEnd, $firstOfMonth, $events);
    }

    /**
     * Builds a grid for a single week (Monday to Sunday).
     *
     * @param Event[] $events
     */
    private function buildWeekGrid(\DateTimeImmutable $currentDate, array $events): array
    {
        $weekStart = $currentDate->modify('Monday this week')->setTime(0, 0, 0);
        $weekEnd = $weekStart->modify('+6 days');

        return $this->buildGrid($weekStart, $weekEnd, $currentDate, $events);
    }

    /**
     * @param Event[] $events
     */
    private function buildGrid(
        \DateTimeImmutable $gridStart,
        \DateTimeImmutable $gridEnd,
        \DateTimeImmutable $referenceMonth,
        array $events,
    ): array {
        $today = new \DateTimeImmutable('today');
        $weeks = [];
        $week = [];
        $day = $gridStart;

        while ($day <= $gridEnd) {
            $dayEnd = $day->setTime(23, 59, 59);
            $dayEvents = array_values(array_filter(
                $events,
                static fn(Event $e) => $e->getStart() <= $dayEnd && $e->getEnd() >= $day,
            ));

            $week[] = [
                'date' => $day,
                'isCurrentMonth' => $day->format('Ym') === $referenceMonth->format('Ym'),
                'isToday' => $day->format('Ymd') === $today->format('Ymd'),
                'events' => $dayEvents,
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $day = $day->modify('+1 day');
        }

        if ($week !== []) {
            $weeks[] = $week;
        }

        return $weeks;
    }

    /**
     * Builds previous/next navigation dates for the given view mode.
     */
    private function buildNavigation(string $viewMode, \DateTimeImmutable $currentDate): array
    {
        switch ($viewMode) {
            case 'week':
                $prev = $currentDate->modify('-1 week');
                $next = $currentDate->modify('+1 week');
                break;

            case 'list':
                $prev = $currentDate->modify('-1 month');
                $next = $currentDate->modify('+1 month');
                break;

            case 'month':
            default:
                $prev = $currentDate->modify('first day of last month');
                $next = $currentDate->modify('first day of next month');
                break;
        }

        return [
            'prev' => $prev,
            'next' => $next,
        ];
    }
}
