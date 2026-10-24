<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\EventProvider;

use Maispace\MaiEvents\Domain\Model\Event;

/**
 * Interface for event providers.
 *
 * Implement this interface to supply events to the EventsDataProcessor
 * from any data source (e.g. maispace/project, external APIs, database records).
 *
 * Implementations must be tagged with the service tag
 * `maispace.calendar.event_provider` in Configuration/Services.yaml
 * so that they are automatically discovered and registered.
 */
interface EventProviderInterface
{
    /**
     * Returns all events within the given date range.
     *
     * @param \DateTimeInterface $start Inclusive start of the range
     * @param \DateTimeInterface $end   Inclusive end of the range
     * @param int $categoryUid         Optional sys_category UID filter (0 = all)
     * @param int $seriesPageUid       Optional Event page UID (doktype 121); when > 0,
     *                                 only that series page is returned (category ignored)
     * @return Event[]
     */
    public function getEvents(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $categoryUid = 0,
        int $seriesPageUid = 0,
    ): array;
}
