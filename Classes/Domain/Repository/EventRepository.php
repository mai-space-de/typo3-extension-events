<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Domain\Repository;

use Maispace\MaiBase\Backend\CustomPageModule\DoktypeIds;
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

    public function __construct()
    {
        parent::__construct();
        // Repository::__construct() derives $objectType from the class name
        // convention ("EventRepository" -> Domain\Model\Event), which here
        // resolves to the plain ICS-import DTO instead of the persisted
        // AbstractEntity. EventRecord is the actual entity for Event pages.
        $this->objectType = EventRecord::class;
    }

    public function initializeObject(): void
    {
        $querySettings = $this->createQuery()->getQuerySettings();
        $querySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($querySettings);
    }

    public function findUpcoming(int $limit = 10): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('doktype', DoktypeIds::EVENT),
                $query->greaterThanOrEqual('startDate', time()),
            ),
        );
        $query->setLimit($limit);
        return $query->execute();
    }

    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('doktype', DoktypeIds::EVENT),
                $query->greaterThanOrEqual('startDate', $start->getTimestamp()),
                $query->lessThan('startDate', $end->getTimestamp()),
            ),
        );
        return $query->execute();
    }

    public function findByUid($uid): ?EventRecord
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('uid', (int) $uid),
                $query->equals('doktype', DoktypeIds::EVENT),
            ),
        );
        $query->setLimit(1);
        /** @var EventRecord|null $result */
        $result = $query->execute()->getFirst();

        return $result;
    }
}
