<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Domain\Repository;

use Maispace\MaiEvents\Domain\Repository\EventRepository;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Unit tests for EventRepository class structure and default orderings.
 */
class EventRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // class hierarchy
    // -------------------------------------------------------------------------

    public function testExtendsExtbaseRepository(): void
    {
        self::assertTrue(is_subclass_of(EventRepository::class, Repository::class));
    }

    // -------------------------------------------------------------------------
    // default orderings
    // -------------------------------------------------------------------------

    public function testDefaultOrderingsSortsByStartDateAscending(): void
    {
        $ref = new \ReflectionClass(EventRepository::class);
        $prop = $ref->getProperty('defaultOrderings');
        $prop->setAccessible(true);

        // Instantiate without calling constructor — defaultOrderings is a class property
        $repo = $ref->newInstanceWithoutConstructor();
        $orderings = $prop->getValue($repo);

        self::assertArrayHasKey('startDate', $orderings);
        self::assertSame(QueryInterface::ORDER_ASCENDING, $orderings['startDate']);
    }

    public function testDefaultOrderingsHasExactlyOneSortKey(): void
    {
        $ref = new \ReflectionClass(EventRepository::class);
        $prop = $ref->getProperty('defaultOrderings');
        $prop->setAccessible(true);

        $repo = $ref->newInstanceWithoutConstructor();
        $orderings = $prop->getValue($repo);

        self::assertCount(1, $orderings);
    }
}
