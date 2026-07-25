<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Domain\Model;

use Maispace\MaiEvents\Domain\Model\Registration;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Unit tests for the Registration Extbase entity.
 * This is the primary coverage target for the events-2 task.
 */
class RegistrationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // class hierarchy
    // -------------------------------------------------------------------------

    public function testExtendsAbstractEntity(): void
    {
        self::assertInstanceOf(AbstractEntity::class, new Registration());
    }

    // -------------------------------------------------------------------------
    // default values
    // -------------------------------------------------------------------------

    public function testEventDefaultsToZero(): void
    {
        self::assertSame(0, (new Registration())->getEvent());
    }

    public function testFirstNameDefaultsToEmpty(): void
    {
        self::assertSame('', (new Registration())->getFirstName());
    }

    public function testLastNameDefaultsToEmpty(): void
    {
        self::assertSame('', (new Registration())->getLastName());
    }

    public function testEmailDefaultsToEmpty(): void
    {
        self::assertSame('', (new Registration())->getEmail());
    }

    public function testStatusDefaultsToRegistered(): void
    {
        self::assertSame('registered', (new Registration())->getStatus());
    }

    public function testWaitingListDefaultsToFalse(): void
    {
        self::assertFalse((new Registration())->isWaitingList());
    }

    public function testConfirmationTokenDefaultsToEmpty(): void
    {
        self::assertSame('', (new Registration())->getConfirmationToken());
    }

    public function testRegisteredAtDefaultsToNull(): void
    {
        self::assertNull((new Registration())->getRegisteredAt());
    }

    public function testConfirmedAtDefaultsToNull(): void
    {
        self::assertNull((new Registration())->getConfirmedAt());
    }

    public function testOccurrenceStartDefaultsToZero(): void
    {
        self::assertSame(0, (new Registration())->getOccurrenceStart());
    }

    // -------------------------------------------------------------------------
    // getter / setter round-trips
    // -------------------------------------------------------------------------

    public function testSetGetEvent(): void
    {
        $reg = new Registration();
        $reg->setEvent(42);
        self::assertSame(42, $reg->getEvent());
    }

    public function testSetGetOccurrenceStart(): void
    {
        $reg = new Registration();
        $reg->setOccurrenceStart(1_700_000_000);
        self::assertSame(1_700_000_000, $reg->getOccurrenceStart());
    }

    public function testSetGetFirstName(): void
    {
        $reg = new Registration();
        $reg->setFirstName('Jane');
        self::assertSame('Jane', $reg->getFirstName());
    }

    public function testSetGetLastName(): void
    {
        $reg = new Registration();
        $reg->setLastName('Doe');
        self::assertSame('Doe', $reg->getLastName());
    }

    public function testSetGetEmail(): void
    {
        $reg = new Registration();
        $reg->setEmail('jane@example.com');
        self::assertSame('jane@example.com', $reg->getEmail());
    }

    public function testSetGetStatus(): void
    {
        $reg = new Registration();
        $reg->setStatus('waiting');
        self::assertSame('waiting', $reg->getStatus());
    }

    public function testSetGetWaitingList(): void
    {
        $reg = new Registration();
        $reg->setWaitingList(true);
        self::assertTrue($reg->isWaitingList());
    }

    public function testSetGetConfirmationToken(): void
    {
        $reg = new Registration();
        $reg->setConfirmationToken('abc123token');
        self::assertSame('abc123token', $reg->getConfirmationToken());
    }

    public function testSetGetRegisteredAt(): void
    {
        $reg = new Registration();
        $ts = time();
        $reg->setRegisteredAt($ts);
        self::assertSame($ts, $reg->getRegisteredAt());
    }

    public function testSetGetRegisteredAtNull(): void
    {
        $reg = new Registration();
        $reg->setRegisteredAt(time());
        $reg->setRegisteredAt(null);
        self::assertNull($reg->getRegisteredAt());
    }

    public function testSetGetConfirmedAt(): void
    {
        $reg = new Registration();
        $ts = time();
        $reg->setConfirmedAt($ts);
        self::assertSame($ts, $reg->getConfirmedAt());
    }

    public function testSetGetConfirmedAtNull(): void
    {
        $reg = new Registration();
        $reg->setConfirmedAt(time());
        $reg->setConfirmedAt(null);
        self::assertNull($reg->getConfirmedAt());
    }

    // -------------------------------------------------------------------------
    // getFullName
    // -------------------------------------------------------------------------

    public function testGetFullNameReturnsBothNames(): void
    {
        $reg = new Registration();
        $reg->setFirstName('Jane');
        $reg->setLastName('Doe');
        self::assertSame('Jane Doe', $reg->getFullName());
    }

    public function testGetFullNameWithFirstNameOnly(): void
    {
        $reg = new Registration();
        $reg->setFirstName('Jane');
        self::assertSame('Jane', $reg->getFullName());
    }

    public function testGetFullNameWithLastNameOnly(): void
    {
        $reg = new Registration();
        $reg->setLastName('Doe');
        self::assertSame('Doe', $reg->getFullName());
    }

    public function testGetFullNameWithBothEmpty(): void
    {
        self::assertSame('', (new Registration())->getFullName());
    }

    // -------------------------------------------------------------------------
    // isConfirmed
    // -------------------------------------------------------------------------

    public function testIsNotConfirmedByDefault(): void
    {
        self::assertFalse((new Registration())->isConfirmed());
    }

    public function testIsNotConfirmedWhenConfirmedAtIsNull(): void
    {
        $reg = new Registration();
        $reg->setConfirmedAt(null);
        self::assertFalse($reg->isConfirmed());
    }

    public function testIsNotConfirmedWhenConfirmedAtIsZero(): void
    {
        $reg = new Registration();
        $reg->setConfirmedAt(0);
        self::assertFalse($reg->isConfirmed());
    }

    public function testIsConfirmedWhenConfirmedAtIsPositive(): void
    {
        $reg = new Registration();
        $reg->setConfirmedAt(time());
        self::assertTrue($reg->isConfirmed());
    }

    // -------------------------------------------------------------------------
    // status transitions (waiting list workflow)
    // -------------------------------------------------------------------------

    public function testStatusCanBeSetToWaiting(): void
    {
        $reg = new Registration();
        $reg->setStatus('waiting');
        self::assertSame('waiting', $reg->getStatus());
    }

    public function testStatusCanBeSetToCancelled(): void
    {
        $reg = new Registration();
        $reg->setStatus('cancelled');
        self::assertSame('cancelled', $reg->getStatus());
    }

    // -------------------------------------------------------------------------
    // instance isolation
    // -------------------------------------------------------------------------

    public function testTwoInstancesAreIndependent(): void
    {
        $a = new Registration();
        $b = new Registration();

        $a->setFirstName('Alice');
        $a->setEmail('alice@example.com');
        $a->setConfirmedAt(time());

        self::assertSame('', $b->getFirstName());
        self::assertSame('', $b->getEmail());
        self::assertNull($b->getConfirmedAt());
    }
}
