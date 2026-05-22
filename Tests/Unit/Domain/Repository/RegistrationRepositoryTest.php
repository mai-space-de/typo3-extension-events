<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Domain\Repository;

use Maispace\MaiEvents\Domain\Repository\RegistrationRepository;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Unit tests for RegistrationRepository class structure and default orderings.
 */
class RegistrationRepositoryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // class hierarchy
    // -------------------------------------------------------------------------

    public function testExtendsExtbaseRepository(): void
    {
        self::assertTrue(is_subclass_of(RegistrationRepository::class, Repository::class));
    }

    // -------------------------------------------------------------------------
    // default orderings
    // -------------------------------------------------------------------------

    public function testDefaultOrderingsSortsByRegisteredAtAscending(): void
    {
        $ref = new \ReflectionClass(RegistrationRepository::class);
        $prop = $ref->getProperty('defaultOrderings');
        $prop->setAccessible(true);

        $repo = $ref->newInstanceWithoutConstructor();
        $orderings = $prop->getValue($repo);

        self::assertArrayHasKey('registeredAt', $orderings);
        self::assertSame(QueryInterface::ORDER_ASCENDING, $orderings['registeredAt']);
    }

    public function testDefaultOrderingsHasExactlyOneSortKey(): void
    {
        $ref = new \ReflectionClass(RegistrationRepository::class);
        $prop = $ref->getProperty('defaultOrderings');
        $prop->setAccessible(true);

        $repo = $ref->newInstanceWithoutConstructor();
        $orderings = $prop->getValue($repo);

        self::assertCount(1, $orderings);
    }
}
