<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Domain\Model;

use Maispace\MaiEvents\Domain\Model\EventRecord;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Unit tests for the EventRecord Extbase entity.
 */
class EventRecordTest extends TestCase
{
    // -------------------------------------------------------------------------
    // class hierarchy
    // -------------------------------------------------------------------------

    public function testExtendsAbstractEntity(): void
    {
        self::assertInstanceOf(AbstractEntity::class, new EventRecord());
    }

    // -------------------------------------------------------------------------
    // default values
    // -------------------------------------------------------------------------

    public function testTitleDefaultsToEmpty(): void
    {
        self::assertSame('', (new EventRecord())->getTitle());
    }

    public function testDescriptionDefaultsToEmpty(): void
    {
        self::assertSame('', (new EventRecord())->getDescription());
    }

    public function testLocationDefaultsToEmpty(): void
    {
        self::assertSame('', (new EventRecord())->getLocation());
    }

    public function testLinkDefaultsToEmpty(): void
    {
        self::assertSame('', (new EventRecord())->getLink());
    }

    public function testStartDateDefaultsToNull(): void
    {
        self::assertNull((new EventRecord())->getStartDate());
    }

    public function testEndDateDefaultsToNull(): void
    {
        self::assertNull((new EventRecord())->getEndDate());
    }

    public function testRegistrationDeadlineDefaultsToNull(): void
    {
        self::assertNull((new EventRecord())->getRegistrationDeadline());
    }

    public function testMaxAttendeesDefaultsToZero(): void
    {
        self::assertSame(0, (new EventRecord())->getMaxAttendees());
    }

    public function testHasWaitingListDefaultsToFalse(): void
    {
        self::assertFalse((new EventRecord())->isHasWaitingList());
    }

    public function testRecurrenceFrequencyDefaultsToEmpty(): void
    {
        self::assertSame('', (new EventRecord())->getRecurrenceFrequency());
        self::assertFalse((new EventRecord())->isRecurring());
    }

    public function testRecurrenceUntilDefaultsToNull(): void
    {
        self::assertNull((new EventRecord())->getRecurrenceUntil());
    }

    public function testImageStorageIsInitialisedOnConstruction(): void
    {
        self::assertInstanceOf(ObjectStorage::class, (new EventRecord())->getImage());
    }

    public function testCategoriesStorageIsInitialisedOnConstruction(): void
    {
        self::assertInstanceOf(ObjectStorage::class, (new EventRecord())->getCategories());
        self::assertCount(0, (new EventRecord())->getCategories());
    }

    // -------------------------------------------------------------------------
    // getter / setter round-trips
    // -------------------------------------------------------------------------

    public function testSetGetTitle(): void
    {
        $record = new EventRecord();
        $record->setTitle('Summer Festival');
        self::assertSame('Summer Festival', $record->getTitle());
    }

    public function testSetGetDescription(): void
    {
        $record = new EventRecord();
        $record->setDescription('A fun outdoor event');
        self::assertSame('A fun outdoor event', $record->getDescription());
    }

    public function testSetGetLocation(): void
    {
        $record = new EventRecord();
        $record->setLocation('Town Square');
        self::assertSame('Town Square', $record->getLocation());
    }

    public function testSetGetLink(): void
    {
        $record = new EventRecord();
        $record->setLink('t3://page?uid=42');
        self::assertSame('t3://page?uid=42', $record->getLink());
    }

    public function testSetGetStartDate(): void
    {
        $record = new EventRecord();
        $ts = mktime(10, 0, 0, 6, 15, 2024);
        $record->setStartDate($ts);
        self::assertSame($ts, $record->getStartDate());
    }

    public function testSetGetEndDate(): void
    {
        $record = new EventRecord();
        $ts = mktime(12, 0, 0, 6, 15, 2024);
        $record->setEndDate($ts);
        self::assertSame($ts, $record->getEndDate());
    }

    public function testSetGetRegistrationDeadline(): void
    {
        $record = new EventRecord();
        $ts = mktime(23, 59, 0, 6, 14, 2024);
        $record->setRegistrationDeadline($ts);
        self::assertSame($ts, $record->getRegistrationDeadline());
    }

    public function testSetGetMaxAttendees(): void
    {
        $record = new EventRecord();
        $record->setMaxAttendees(50);
        self::assertSame(50, $record->getMaxAttendees());
    }

    public function testSetGetHasWaitingList(): void
    {
        $record = new EventRecord();
        $record->setHasWaitingList(true);
        self::assertTrue($record->isHasWaitingList());
    }

    // -------------------------------------------------------------------------
    // getStartDateAsDateTime
    // -------------------------------------------------------------------------

    public function testGetStartDateAsDateTimeReturnsNullWhenNull(): void
    {
        self::assertNull((new EventRecord())->getStartDateAsDateTime());
    }

    public function testGetStartDateAsDateTimeReturnsNullWhenZero(): void
    {
        $record = new EventRecord();
        $record->setStartDate(0);
        self::assertNull($record->getStartDateAsDateTime());
    }

    public function testGetStartDateAsDateTimeReturnsDateTimeImmutable(): void
    {
        $record = new EventRecord();
        $ts = mktime(10, 0, 0, 6, 15, 2024);
        $record->setStartDate($ts);
        $dt = $record->getStartDateAsDateTime();
        self::assertInstanceOf(\DateTimeImmutable::class, $dt);
        self::assertSame($ts, $dt->getTimestamp());
    }

    // -------------------------------------------------------------------------
    // getEndDateAsDateTime
    // -------------------------------------------------------------------------

    public function testGetEndDateAsDateTimeReturnsNullWhenNull(): void
    {
        self::assertNull((new EventRecord())->getEndDateAsDateTime());
    }

    public function testGetEndDateAsDateTimeReturnsNullWhenZero(): void
    {
        $record = new EventRecord();
        $record->setEndDate(0);
        self::assertNull($record->getEndDateAsDateTime());
    }

    public function testGetEndDateAsDateTimeReturnsDateTimeImmutable(): void
    {
        $record = new EventRecord();
        $ts = mktime(12, 0, 0, 6, 15, 2024);
        $record->setEndDate($ts);
        $dt = $record->getEndDateAsDateTime();
        self::assertInstanceOf(\DateTimeImmutable::class, $dt);
        self::assertSame($ts, $dt->getTimestamp());
    }

    // -------------------------------------------------------------------------
    // isRegistrationOpen
    // -------------------------------------------------------------------------

    public function testRegistrationIsOpenWhenNoDeadline(): void
    {
        // registrationDeadline = null → always open
        self::assertTrue((new EventRecord())->isRegistrationOpen());
    }

    public function testRegistrationIsOpenWhenDeadlineIsZero(): void
    {
        $record = new EventRecord();
        $record->setRegistrationDeadline(0);
        self::assertTrue($record->isRegistrationOpen());
    }

    public function testRegistrationIsOpenWhenDeadlineIsInFuture(): void
    {
        $record = new EventRecord();
        $record->setRegistrationDeadline(time() + 86400); // tomorrow
        self::assertTrue($record->isRegistrationOpen());
    }

    public function testRegistrationIsClosedWhenDeadlineIsInPast(): void
    {
        $record = new EventRecord();
        $record->setRegistrationDeadline(time() - 86400); // yesterday
        self::assertFalse($record->isRegistrationOpen());
    }

    public function testRelativeDeadlineAppliesOffsetToOccurrence(): void
    {
        $record = new EventRecord();
        $now = time();
        $seriesStart = $now + 86400 * 14;
        $deadline = $seriesStart - 86400 * 7;
        $occurrence = $seriesStart + 86400 * 7;

        $record->setStartDate($seriesStart);
        $record->setRegistrationDeadline($deadline);
        $record->setRecurrenceFrequency('weekly');

        $expected = $occurrence - ($seriesStart - $deadline);
        self::assertSame($expected, $record->getRegistrationDeadlineForOccurrence($occurrence));
        self::assertTrue($record->isRegistrationOpenForOccurrence($occurrence));
    }

    public function testSetGetRecurrenceFields(): void
    {
        $record = new EventRecord();
        $record->setRecurrenceFrequency('monthly');
        $record->setRecurrenceUntil(1_800_000_000);
        $record->setRecurrenceMonthWeekday(2);

        self::assertSame('monthly', $record->getRecurrenceFrequency());
        self::assertTrue($record->isRecurring());
        self::assertSame(1_800_000_000, $record->getRecurrenceUntil());
        self::assertInstanceOf(\DateTimeImmutable::class, $record->getRecurrenceUntilAsDateTime());
        self::assertSame(2, $record->getRecurrenceMonthWeekday());
    }

    // -------------------------------------------------------------------------
    // getFirstImage
    // -------------------------------------------------------------------------

    public function testGetFirstImageReturnsNullOnEmptyStorage(): void
    {
        self::assertNull((new EventRecord())->getFirstImage());
    }

    // -------------------------------------------------------------------------
    // instance isolation
    // -------------------------------------------------------------------------

    public function testTwoInstancesHaveIndependentImageStorages(): void
    {
        $a = new EventRecord();
        $b = new EventRecord();
        self::assertNotSame($a->getImage(), $b->getImage());
    }

    public function testTwoInstancesHaveIndependentCategoryStorages(): void
    {
        $a = new EventRecord();
        $b = new EventRecord();
        self::assertNotSame($a->getCategories(), $b->getCategories());
    }

    public function testSetGetCategories(): void
    {
        $record = new EventRecord();
        $categories = new ObjectStorage();
        $record->setCategories($categories);
        self::assertSame($categories, $record->getCategories());
    }
}
