<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Domain\Repository;

use Maispace\MaiEvents\Domain\Model\Registration;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Registration>
 */
class RegistrationRepository extends Repository
{
    protected $defaultOrderings = [
        'registeredAt' => QueryInterface::ORDER_ASCENDING,
    ];

    public function findByEvent(int $eventUid): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('event', $eventUid),
        );
        return $query->execute();
    }

    public function countByEvent(int $eventUid): int
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('event', $eventUid),
                $query->logicalNot($query->equals('status', 'cancelled')),
            ),
        );
        return $query->count();
    }

    public function countByEventAndOccurrence(int $eventUid, int $occurrenceStart): int
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('event', $eventUid),
                $query->equals('occurrenceStart', $occurrenceStart),
                $query->logicalNot($query->equals('status', 'cancelled')),
            ),
        );

        return $query->count();
    }

    public function findByEventAndOccurrence(int $eventUid, int $occurrenceStart): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('event', $eventUid),
                $query->equals('occurrenceStart', $occurrenceStart),
            ),
        );

        return $query->execute();
    }

    public function findWaitingByEvent(int $eventUid): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('event', $eventUid),
                $query->equals('waitingList', true),
            ),
        );
        return $query->execute();
    }

    public function findByConfirmationToken(string $token): ?Registration
    {
        $query = $this->createQuery();
        $query->matching(
            $query->equals('confirmationToken', $token),
        );
        $result = $query->execute()->getFirst();
        return $result;
    }
}
