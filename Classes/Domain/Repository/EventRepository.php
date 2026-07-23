<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Domain\Repository;

use Maispace\MaiEvents\Domain\Model\EventRecord;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<EventRecord>
 */
class EventRepository extends Repository
{
    protected $defaultOrderings = [
        'startDate' => QueryInterface::ORDER_ASCENDING,
    ];

    public function findUpcoming(int $limit = 10): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->greaterThanOrEqual('startDate', time()),
        );
        $query->setLimit($limit);
        return $query->execute();
    }

    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): QueryResultInterface
    {
        $query = $this->createQuery();
        $querySettings = $query->getQuerySettings();
        $querySettings->setStoragePageIds([27]);
        $query->setQuerySettings($querySettings);
        $query->matching(
            $query->logicalAnd(
                $query->greaterThanOrEqual('startDate', $start->getTimestamp()),
                $query->lessThan('startDate', $end->getTimestamp()),
            ),
        );
        return $query->execute();
    }
}
