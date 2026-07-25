<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\EventProvider;

use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\Service\RecurrenceExpander;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TxEventProvider implements EventProviderInterface
{
    public function __construct(
        private readonly RecurrenceExpander $recurrenceExpander = new RecurrenceExpander(),
    ) {}

    public function getEvents(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rangeStartTs = $start->getTimestamp();
        $rangeEndTs = $end->getTimestamp();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_maievents_event');
        $queryBuilder->getRestrictions()->removeAll();

        // Series that can produce occurrences overlapping the window:
        // - first start before range end
        // - and either non-recurring with start in window, or recurring with until >= range start
        //   (until=0 treated as non-recurring fallback handled by expander)
        $rows = $queryBuilder
            ->select(
                'uid',
                'title',
                'start_date',
                'end_date',
                'description',
                'location',
                'recurrence_frequency',
                'recurrence_until',
            )
            ->from('tx_maievents_event')
            ->where(
                $queryBuilder->expr()->lt('start_date', $queryBuilder->createNamedParameter($rangeEndTs, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter(27, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->eq('recurrence_frequency', $queryBuilder->createNamedParameter('')),
                        $queryBuilder->expr()->gte('start_date', $queryBuilder->createNamedParameter($rangeStartTs, Connection::PARAM_INT)),
                    ),
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->neq('recurrence_frequency', $queryBuilder->createNamedParameter('')),
                        $queryBuilder->expr()->or(
                            $queryBuilder->expr()->eq('recurrence_until', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                            $queryBuilder->expr()->gte('recurrence_until', $queryBuilder->createNamedParameter($rangeStartTs, Connection::PARAM_INT)),
                        ),
                    ),
                ),
            )
            ->orderBy('start_date', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $rangeStart = \DateTimeImmutable::createFromInterface($start);
        $rangeEnd = \DateTimeImmutable::createFromInterface($end);

        $events = [];
        foreach ($rows as $row) {
            $seriesStartTs = (int) $row['start_date'];
            if ($seriesStartTs <= 0) {
                continue;
            }

            $seriesStart = (new \DateTimeImmutable())->setTimestamp($seriesStartTs);
            $seriesEndTs = (int) $row['end_date'];
            $seriesEnd = $seriesEndTs > 0
                ? (new \DateTimeImmutable())->setTimestamp($seriesEndTs)
                : $seriesStart;

            $frequency = (string) ($row['recurrence_frequency'] ?? '');
            $untilTs = (int) ($row['recurrence_until'] ?? 0);
            $until = $untilTs > 0
                ? (new \DateTimeImmutable())->setTimestamp($untilTs)
                : null;

            $occurrences = $this->recurrenceExpander->expand(
                $seriesStart,
                $seriesEnd,
                $frequency,
                $until,
                $rangeStart,
                $rangeEnd,
            );

            $seriesUid = (int) $row['uid'];
            foreach ($occurrences as $occurrence) {
                $occurrenceStart = $occurrence['start']->getTimestamp();
                $events[] = new Event(
                    uid: 'tx_maievents_event_' . $seriesUid . '_' . $occurrenceStart,
                    title: (string) $row['title'],
                    start: $occurrence['start'],
                    end: $occurrence['end'],
                    description: (string) ($row['description'] ?? ''),
                    location: (string) $row['location'],
                    source: 'tx_maievents',
                    seriesUid: $seriesUid,
                    occurrenceStart: $occurrenceStart,
                );
            }
        }

        usort(
            $events,
            static fn(Event $a, Event $b): int => $a->getStart() <=> $b->getStart(),
        );

        return $events;
    }
}
