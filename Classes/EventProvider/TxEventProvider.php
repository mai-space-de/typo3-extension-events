<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\EventProvider;

use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\Domain\Repository\EventRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TxEventProvider implements EventProviderInterface
{
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {}

    public function getEvents(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_maievents_event');
        $queryBuilder->getRestrictions()->removeAll();

        $rows = $queryBuilder
            ->select('uid', 'title', 'start_date', 'end_date', 'description', 'location')
            ->from('tx_maievents_event')
            ->where(
                $queryBuilder->expr()->gte('start_date', $queryBuilder->createNamedParameter($start->getTimestamp())),
                $queryBuilder->expr()->lt('start_date', $queryBuilder->createNamedParameter($end->getTimestamp())),
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter(27)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0)),
            )
            ->orderBy('start_date', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $events = [];
        foreach ($rows as $row) {
            $startDt = (new \DateTimeImmutable())->setTimestamp((int) $row['start_date']);
            $endDt = (int) $row['end_date'] > 0
                ? (new \DateTimeImmutable())->setTimestamp((int) $row['end_date'])
                : $startDt;

            $events[] = new Event(
                uid: 'tx_maievents_event_' . $row['uid'],
                title: (string) $row['title'],
                start: $startDt,
                end: $endDt,
                description: (string) ($row['description'] ?? ''),
                location: (string) $row['location'],
                source: 'tx_maievents',
            );
        }

        return $events;
    }
}
