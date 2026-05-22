<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\EventProvider;

use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\Domain\Repository\EventRepository;

class TxEventProvider implements EventProviderInterface
{
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {}

    public function getEvents(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $records = $this->eventRepository->findByDateRange($start, $end);
        $events = [];

        foreach ($records as $record) {
            $startDt = $record->getStartDateAsDateTime();
            if ($startDt === null) {
                continue;
            }
            $endDt = $record->getEndDateAsDateTime() ?? $startDt;

            $events[] = new Event(
                uid: 'tx_maievents_event_' . $record->getUid(),
                title: $record->getTitle(),
                start: $startDt,
                end: $endDt,
                description: $record->getDescription(),
                location: $record->getLocation(),
                source: 'tx_maievents',
            );
        }

        return $events;
    }
}
