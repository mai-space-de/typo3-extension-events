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
 * (via EventsController) for the FullCalendar frontend mount.
 *
 * Supported view modes: month (default), week, list — mapped to FullCalendar views.
 */
final class CalendarService
{
    private const ALLOWED_VIEW_MODES = ['month', 'week', 'list'];

    private const VIEW_MODE_TO_FULLCALENDAR = [
        'month' => 'dayGridMonth',
        'week' => 'timeGridWeek',
        'list' => 'listWeek',
    ];

    /**
     * @param iterable<EventProviderInterface>|null $providers Explicit providers (mainly for tests);
     *                                                          null resolves providers from the container.
     */
    public function __construct(private readonly ?iterable $providers = null) {}

    /**
     * @return array{
     *     viewMode: string,
     *     initialView: string,
     *     currentDate: \DateTimeImmutable,
     *     start: \DateTimeImmutable,
     *     end: \DateTimeImmutable,
     *     events: Event[],
 *     fullCalendarEvents: list<array<string, mixed>>,
 *     contentUid: int,
 *     locale: string,
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
        unset($listLimit); // retained for call-site BC / FlexForm; FC uses preload window
        $viewMode = $this->resolveViewMode($requestedViewMode, $configuredViewMode);
        $currentDate = $this->resolveCurrentDate($requestedDate, $configuredDate);

        // Preload a wide window so FullCalendar can navigate client-side without reloads.
        [$start, $end] = $this->calculatePreloadRange($currentDate);
        $events = $this->aggregateEvents($start, $end, $categoryUid);

        return [
            'viewMode' => $viewMode,
            'initialView' => self::VIEW_MODE_TO_FULLCALENDAR[$viewMode],
            'currentDate' => $currentDate,
            'start' => $start,
            'end' => $end,
            'events' => $events,
            'fullCalendarEvents' => $this->mapToFullCalendarEvents($events),
            'contentUid' => $contentUid,
            'locale' => 'de',
        ];
    }

    /**
     * @param Event[] $events
     * @return list<array<string, mixed>>
     */
    public function mapToFullCalendarEvents(array $events): array
    {
        $mapped = [];
        foreach ($events as $event) {
            $mapped[] = [
                'id' => $event->getUid(),
                'title' => $event->getTitle(),
                'start' => $event->getStart()->format('c'),
                'end' => $event->getEnd()->format('c'),
                'allDay' => $event->isAllDay(),
                'url' => $event->getUrl(),
                'extendedProps' => [
                    'description' => $this->plainTextDescription($event->getDescription()),
                    'location' => $event->getLocation(),
                ],
            ];
        }

        return $mapped;
    }

    private function plainTextDescription(string $description): string
    {
        if ($description === '') {
            return '';
        }

        $stripped = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $stripped) ?? $stripped);
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
     * Preload window: today −3 months … today +12 months (relative to currentDate).
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function calculatePreloadRange(\DateTimeImmutable $currentDate): array
    {
        $start = $currentDate->modify('-3 months')->modify('first day of this month')->setTime(0, 0, 0);
        $end = $currentDate->modify('+12 months')->modify('last day of this month')->setTime(23, 59, 59);

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
}
