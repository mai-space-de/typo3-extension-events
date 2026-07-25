<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Service;

use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\EventProvider\EventProviderInterface;
use Maispace\MaiEvents\EventProvider\TxEventProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds the calendar data structure shared by the Calendar FLUIDTEMPLATE
 * content element (via EventsDataProcessor) and the Extbase Events plugin
 * (via EventsController), so both surfaces support the same month/week/list
 * view modes and date navigation.
 *
 * Supported view modes: month (default), week, list.
 */
final class CalendarService
{
    private const ALLOWED_VIEW_MODES = ['month', 'week', 'list'];

    /**
     * @param iterable<EventProviderInterface>|null $providers Explicit providers (mainly for tests);
     *                                                          null resolves providers from the container.
     */
    public function __construct(private readonly ?iterable $providers = null) {}

    /**
     * @return array{
     *     viewMode: string,
     *     currentDate: \DateTimeImmutable,
     *     start: \DateTimeImmutable,
     *     end: \DateTimeImmutable,
     *     events: Event[],
     *     navigation: array{prev: \DateTimeImmutable, next: \DateTimeImmutable},
     *     contentUid: int,
     *     weeks?: array,
     * }
     */
    public function buildCalendar(
        ?string $requestedViewMode,
        ?string $requestedDate,
        string $configuredViewMode = 'month',
        string $configuredDate = '',
        int $listLimit = 10,
        int $categoryUid = 0,
        int $contentUid = 0,
    ): array {
        $viewMode = $this->resolveViewMode($requestedViewMode, $configuredViewMode);
        $currentDate = $this->resolveCurrentDate($requestedDate, $configuredDate);

        [$start, $end] = $this->calculateDateRange($viewMode, $currentDate);
        $events = $this->aggregateEvents($start, $end, $categoryUid);

        $calendar = [
            'viewMode' => $viewMode,
            'currentDate' => $currentDate,
            'start' => $start,
            'end' => $end,
            'events' => $events,
            'navigation' => $this->buildNavigation($viewMode, $currentDate),
            'contentUid' => $contentUid,
        ];

        if ($viewMode === 'month') {
            $calendar['weeks'] = $this->buildMonthGrid($currentDate, $events);
        } elseif ($viewMode === 'week') {
            $calendar['weeks'] = $this->buildWeekGrid($currentDate, $events);
        } elseif ($viewMode === 'list') {
            $calendar['events'] = array_slice($events, 0, $listLimit > 0 ? $listLimit : count($events));
        }

        return $calendar;
    }

    private function resolveViewMode(?string $requested, string $configured): string
    {
        if ($requested !== null && in_array($requested, self::ALLOWED_VIEW_MODES, true)) {
            return $requested;
        }

        if (in_array($configured, self::ALLOWED_VIEW_MODES, true)) {
            return $configured;
        }

        return 'month';
    }

    private function resolveCurrentDate(?string $requested, string $configuredDate): \DateTimeImmutable
    {
        if ($requested !== null && $requested !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $requested);
            if ($parsed !== false) {
                return $parsed->setTime(0, 0, 0);
            }
        }

        if ($configuredDate !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $configuredDate);
            if ($parsed !== false) {
                return $parsed->setTime(0, 0, 0);
            }
        }

        return new \DateTimeImmutable('today');
    }

    /**
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
     * @return Event[]
     */
    public function aggregateEvents(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $categoryUid = 0,
    ): array {
        $events = [];
        foreach ($this->getEventProviders() as $provider) {
            foreach ($provider->getEvents($start, $end, $categoryUid) as $event) {
                $events[] = $event;
            }
        }

        usort($events, static fn(Event $a, Event $b) => $a->getStart() <=> $b->getStart());

        return $events;
    }

    /**
     * @return iterable<EventProviderInterface>
     */
    private function getEventProviders(): iterable
    {
        if ($this->providers !== null) {
            yield from $this->providers;

            return;
        }

        try {
            $container = GeneralUtility::getContainer();
            if ($container->has(TxEventProvider::class)) {
                yield $container->get(TxEventProvider::class);
            }
        } catch (\Throwable) {
            // Container unavailable or error — continue with empty result
        }
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
