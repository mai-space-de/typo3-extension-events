<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\EventProvider;

use Maispace\MaiBase\Backend\CustomPageModule\DoktypeIds;
use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\Service\RecurrenceExpander;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Loads calendar occurrences from Event doktype pages (121).
 */
class TxEventProvider implements EventProviderInterface
{
    public function __construct(
        private readonly RecurrenceExpander $recurrenceExpander = new RecurrenceExpander(),
    ) {}

    public function getEvents(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $categoryUid = 0,
        int $seriesPageUid = 0,
    ): array {
        $rangeStartTs = $start->getTimestamp();
        $rangeEndTs = $end->getTimestamp();
        $languageId = $this->resolveLanguageId();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->select(
                'pages.uid',
                'pages.title',
                'pages.abstract',
                'pages.tx_maievents_start_date',
                'pages.tx_maievents_end_date',
                'pages.tx_maievents_location',
                'pages.tx_maievents_recurrence_frequency',
                'pages.tx_maievents_recurrence_until',
                'pages.tx_maievents_recurrence_month_weekday',
            )
            ->from('pages');

        // Series page binding (Event Angebotsseite): the page *is* the series.
        // Takes precedence over category filters used on hub/list calendars.
        if ($seriesPageUid <= 0 && $categoryUid > 0) {
            // Category MM rows live on the default-language record; overlays keep
            // l10n_source pointing at that UID (0 for default-language rows).
            $categoryForeignUid = $languageId > 0
                ? $queryBuilder->quoteIdentifier('pages.l10n_source')
                : $queryBuilder->quoteIdentifier('pages.uid');

            $queryBuilder
                ->join(
                    'pages',
                    'sys_category_record_mm',
                    'mm',
                    (string) $queryBuilder->expr()->and(
                        $queryBuilder->expr()->eq(
                            'mm.uid_foreign',
                            $categoryForeignUid,
                        ),
                        $queryBuilder->expr()->eq(
                            'mm.uid_local',
                            $queryBuilder->createNamedParameter($categoryUid, Connection::PARAM_INT),
                        ),
                        $queryBuilder->expr()->eq(
                            'mm.tablenames',
                            $queryBuilder->createNamedParameter('pages'),
                        ),
                        $queryBuilder->expr()->eq(
                            'mm.fieldname',
                            $queryBuilder->createNamedParameter('categories'),
                        ),
                    ),
                );
        }

        $constraints = [
            $queryBuilder->expr()->eq(
                'pages.doktype',
                $queryBuilder->createNamedParameter(DoktypeIds::EVENT, Connection::PARAM_INT),
            ),
            $queryBuilder->expr()->eq(
                'pages.sys_language_uid',
                $queryBuilder->createNamedParameter($languageId, Connection::PARAM_INT),
            ),
            $queryBuilder->expr()->lt(
                'pages.tx_maievents_start_date',
                $queryBuilder->createNamedParameter($rangeEndTs, Connection::PARAM_INT),
            ),
            $queryBuilder->expr()->eq('pages.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->eq('pages.hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            $queryBuilder->expr()->or(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        'pages.tx_maievents_recurrence_frequency',
                        $queryBuilder->createNamedParameter(''),
                    ),
                    $queryBuilder->expr()->gte(
                        'pages.tx_maievents_start_date',
                        $queryBuilder->createNamedParameter($rangeStartTs, Connection::PARAM_INT),
                    ),
                ),
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->neq(
                        'pages.tx_maievents_recurrence_frequency',
                        $queryBuilder->createNamedParameter(''),
                    ),
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->eq(
                            'pages.tx_maievents_recurrence_until',
                            $queryBuilder->createNamedParameter(0, Connection::PARAM_INT),
                        ),
                        $queryBuilder->expr()->gte(
                            'pages.tx_maievents_recurrence_until',
                            $queryBuilder->createNamedParameter($rangeStartTs, Connection::PARAM_INT),
                        ),
                    ),
                ),
            ),
        ];

        if ($seriesPageUid > 0) {
            // CE may live on the default-language page while the FE language is an
            // overlay (match via l10n_source), or on the overlay page itself (uid).
            $constraints[] = $languageId > 0
                ? (string) $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        'pages.uid',
                        $queryBuilder->createNamedParameter($seriesPageUid, Connection::PARAM_INT),
                    ),
                    $queryBuilder->expr()->eq(
                        'pages.l10n_source',
                        $queryBuilder->createNamedParameter($seriesPageUid, Connection::PARAM_INT),
                    ),
                )
                : $queryBuilder->expr()->eq(
                    'pages.uid',
                    $queryBuilder->createNamedParameter($seriesPageUid, Connection::PARAM_INT),
                );
        }

        $rows = $queryBuilder
            ->where(...$constraints)
            ->orderBy('pages.tx_maievents_start_date', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $rangeStart = \DateTimeImmutable::createFromInterface($start);
        $rangeEnd = \DateTimeImmutable::createFromInterface($end);

        $events = [];
        foreach ($rows as $row) {
            $seriesStartTs = (int) $row['tx_maievents_start_date'];
            if ($seriesStartTs <= 0) {
                continue;
            }

            $seriesStart = (new \DateTimeImmutable())->setTimestamp($seriesStartTs);
            $seriesEndTs = (int) $row['tx_maievents_end_date'];
            $seriesEnd = $seriesEndTs > 0
                ? (new \DateTimeImmutable())->setTimestamp($seriesEndTs)
                : $seriesStart;

            $frequency = (string) ($row['tx_maievents_recurrence_frequency'] ?? '');
            $untilTs = (int) ($row['tx_maievents_recurrence_until'] ?? 0);
            $until = $untilTs > 0
                ? (new \DateTimeImmutable())->setTimestamp($untilTs)
                : null;
            $monthWeekday = (int) ($row['tx_maievents_recurrence_month_weekday'] ?? 0);

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
            $resolvedUrl = $this->resolvePageUrl($seriesUid);
            $description = (string) ($row['abstract'] ?? '');
            foreach ($occurrences as $occurrence) {
                $occurrenceStart = $occurrence['start']->getTimestamp();
                $events[] = new Event(
                    uid: 'tx_maievents_event_' . $seriesUid . '_' . $occurrenceStart,
                    title: (string) $row['title'],
                    start: $occurrence['start'],
                    end: $occurrence['end'],
                    description: $description,
                    location: (string) ($row['tx_maievents_location'] ?? ''),
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
     * Frontend URL for an Event page (doktype 121) via the site router.
     */
    protected function resolvePageUrl(int $pageUid): string
    {
        if ($pageUid <= 0) {
            return '';
        }

        try {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageUid);

            return (string) $site->getRouter()->generateUri($pageUid);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Current frontend language UID (0 = default). Falls back to 0 outside FE context.
     */
    protected function resolveLanguageId(): int
    {
        try {
            return (int) GeneralUtility::makeInstance(Context::class)
                ->getPropertyFromAspect('language', 'id');
        } catch (\Throwable) {
            return 0;
        }
    }
}
