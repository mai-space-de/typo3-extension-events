<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\EventProvider;

use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\Service\RecurrenceExpander;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Typolink\LinkFactory;

class TxEventProvider implements EventProviderInterface
{
    public function __construct(
        private readonly RecurrenceExpander $recurrenceExpander = new RecurrenceExpander(),
    ) {}

    public function getEvents(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $categoryUid = 0,
    ): array {
        $rangeStartTs = $start->getTimestamp();
        $rangeEndTs = $end->getTimestamp();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_maievents_event');
        $queryBuilder->getRestrictions()->removeAll();

        // Series that can produce occurrences overlapping the window:
        // - first start before range end
        // - and either non-recurring with start in window, or recurring with until >= range start
        //   (until=0 treated as non-recurring fallback handled by expander)
        $queryBuilder
            ->select(
                'tx_maievents_event.uid',
                'tx_maievents_event.title',
                'tx_maievents_event.start_date',
                'tx_maievents_event.end_date',
                'tx_maievents_event.description',
                'tx_maievents_event.location',
                'tx_maievents_event.link',
                'tx_maievents_event.recurrence_frequency',
                'tx_maievents_event.recurrence_until',
                'tx_maievents_event.recurrence_month_weekday',
            )
            ->from('tx_maievents_event');

        if ($categoryUid > 0) {
            $queryBuilder
                ->join(
                    'tx_maievents_event',
                    'sys_category_record_mm',
                    'mm',
                    (string) $queryBuilder->expr()->and(
                        $queryBuilder->expr()->eq(
                            'mm.uid_foreign',
                            $queryBuilder->quoteIdentifier('tx_maievents_event.uid'),
                        ),
                        $queryBuilder->expr()->eq(
                            'mm.uid_local',
                            $queryBuilder->createNamedParameter($categoryUid, Connection::PARAM_INT),
                        ),
                        $queryBuilder->expr()->eq(
                            'mm.tablenames',
                            $queryBuilder->createNamedParameter('tx_maievents_event'),
                        ),
                        $queryBuilder->expr()->eq(
                            'mm.fieldname',
                            $queryBuilder->createNamedParameter('categories'),
                        ),
                    ),
                );
        }

        $rows = $queryBuilder
            ->where(
                $queryBuilder->expr()->lt('tx_maievents_event.start_date', $queryBuilder->createNamedParameter($rangeEndTs, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('tx_maievents_event.pid', $queryBuilder->createNamedParameter(27, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('tx_maievents_event.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('tx_maievents_event.hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->eq('tx_maievents_event.recurrence_frequency', $queryBuilder->createNamedParameter('')),
                        $queryBuilder->expr()->gte('tx_maievents_event.start_date', $queryBuilder->createNamedParameter($rangeStartTs, Connection::PARAM_INT)),
                    ),
                    $queryBuilder->expr()->and(
                        $queryBuilder->expr()->neq('tx_maievents_event.recurrence_frequency', $queryBuilder->createNamedParameter('')),
                        $queryBuilder->expr()->or(
                            $queryBuilder->expr()->eq('tx_maievents_event.recurrence_until', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                            $queryBuilder->expr()->gte('tx_maievents_event.recurrence_until', $queryBuilder->createNamedParameter($rangeStartTs, Connection::PARAM_INT)),
                        ),
                    ),
                ),
            )
            ->orderBy('tx_maievents_event.start_date', 'ASC')
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
            $monthWeekday = (int) ($row['recurrence_month_weekday'] ?? 0);

            $occurrences = $this->recurrenceExpander->expand(
                $seriesStart,
                $seriesEnd,
                $frequency,
                $until,
                $rangeStart,
                $rangeEnd,
                $monthWeekday,
            );

            $seriesUid = (int) $row['uid'];
            $resolvedUrl = $this->resolveLink((string) ($row['link'] ?? ''));
            foreach ($occurrences as $occurrence) {
                $occurrenceStart = $occurrence['start']->getTimestamp();
                $events[] = new Event(
                    uid: 'tx_maievents_event_' . $seriesUid . '_' . $occurrenceStart,
                    title: (string) $row['title'],
                    start: $occurrence['start'],
                    end: $occurrence['end'],
                    description: (string) ($row['description'] ?? ''),
                    location: (string) $row['location'],
                    url: $resolvedUrl,
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

    /**
     * Resolve a TCA link field value to a frontend URL.
     * Plain http(s)/path values are returned as-is; typolink URNs go through LinkFactory.
     */
    protected function resolveLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $link) === 1 || str_starts_with($link, '/')) {
            return $link;
        }

        try {
            $linkFactory = GeneralUtility::makeInstance(LinkFactory::class);
            $result = $linkFactory->createUri($link);

            return $result->getUrl();
        } catch (\Throwable) {
            return '';
        }
    }
}
