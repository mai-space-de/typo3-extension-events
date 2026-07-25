<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Domain\Model;

use Maispace\MaiEvents\Domain\Model\Event;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Event value object.
 * Event is a lightweight read-only DTO (not an Extbase entity).
 */
class EventTest extends TestCase
{
    private function makeEvent(
        string $uid = 'evt-1',
        string $title = 'Test Event',
        string $start = '2024-06-15 10:00:00',
        string $end = '2024-06-15 11:00:00',
        string $description = '',
        string $location = '',
        string $url = '',
        bool $allDay = false,
        string $source = '',
    ): Event {
        return new Event(
            uid: $uid,
            title: $title,
            start: new \DateTimeImmutable($start),
            end: new \DateTimeImmutable($end),
            description: $description,
            location: $location,
            url: $url,
            allDay: $allDay,
            source: $source,
        );
    }

    // -------------------------------------------------------------------------
    // required fields
    // -------------------------------------------------------------------------

    public function testGetUidReturnsConstructorValue(): void
    {
        $event = $this->makeEvent(uid: 'my-uid');
        self::assertSame('my-uid', $event->getUid());
    }

    public function testGetTitleReturnsConstructorValue(): void
    {
        $event = $this->makeEvent(title: 'Annual Meeting');
        self::assertSame('Annual Meeting', $event->getTitle());
    }

    public function testGetStartReturnsCorrectDateTime(): void
    {
        $start = new \DateTimeImmutable('2024-06-15 10:00:00');
        $event = new Event(
            uid: 'e',
            title: 'T',
            start: $start,
            end: new \DateTimeImmutable('2024-06-15 11:00:00'),
        );
        self::assertSame($start, $event->getStart());
    }

    public function testGetEndReturnsCorrectDateTime(): void
    {
        $end = new \DateTimeImmutable('2024-06-15 11:00:00');
        $event = new Event(
            uid: 'e',
            title: 'T',
            start: new \DateTimeImmutable('2024-06-15 10:00:00'),
            end: $end,
        );
        self::assertSame($end, $event->getEnd());
    }

    // -------------------------------------------------------------------------
    // optional fields — defaults
    // -------------------------------------------------------------------------

    public function testDescriptionDefaultsToEmpty(): void
    {
        self::assertSame('', $this->makeEvent()->getDescription());
    }

    public function testLocationDefaultsToEmpty(): void
    {
        self::assertSame('', $this->makeEvent()->getLocation());
    }

    public function testUrlDefaultsToEmpty(): void
    {
        self::assertSame('', $this->makeEvent()->getUrl());
    }

    public function testAllDayDefaultsToFalse(): void
    {
        self::assertFalse($this->makeEvent()->isAllDay());
    }

    public function testSourceDefaultsToEmpty(): void
    {
        self::assertSame('', $this->makeEvent()->getSource());
    }

    public function testSeriesUidDefaultsToZero(): void
    {
        self::assertSame(0, $this->makeEvent()->getSeriesUid());
    }

    public function testOccurrenceStartFallsBackToStartTimestamp(): void
    {
        $event = $this->makeEvent(start: '2024-06-15 10:00:00');
        self::assertSame(
            (new \DateTimeImmutable('2024-06-15 10:00:00'))->getTimestamp(),
            $event->getOccurrenceStart(),
        );
    }

    // -------------------------------------------------------------------------
    // optional fields — set values
    // -------------------------------------------------------------------------

    public function testGetDescriptionReturnsConstructorValue(): void
    {
        $event = $this->makeEvent(description: 'A great event');
        self::assertSame('A great event', $event->getDescription());
    }

    public function testGetLocationReturnsConstructorValue(): void
    {
        $event = $this->makeEvent(location: 'Berlin');
        self::assertSame('Berlin', $event->getLocation());
    }

    public function testGetUrlReturnsConstructorValue(): void
    {
        $event = $this->makeEvent(url: 'https://example.com');
        self::assertSame('https://example.com', $event->getUrl());
    }

    public function testIsAllDayReturnsTrueWhenSet(): void
    {
        $event = $this->makeEvent(allDay: true);
        self::assertTrue($event->isAllDay());
    }

    public function testGetSourceReturnsConstructorValue(): void
    {
        $event = $this->makeEvent(source: 'tx_maievents');
        self::assertSame('tx_maievents', $event->getSource());
    }

    public function testGetSeriesUidAndOccurrenceStartReturnConstructorValues(): void
    {
        $event = new Event(
            uid: 'e',
            title: 'T',
            start: new \DateTimeImmutable('2024-06-15 10:00:00'),
            end: new \DateTimeImmutable('2024-06-15 11:00:00'),
            seriesUid: 42,
            occurrenceStart: 1718445600,
        );

        self::assertSame(42, $event->getSeriesUid());
        self::assertSame(1718445600, $event->getOccurrenceStart());
    }

    // -------------------------------------------------------------------------
    // instance isolation
    // -------------------------------------------------------------------------

    public function testTwoInstancesAreIndependent(): void
    {
        $a = $this->makeEvent(uid: 'a', title: 'Alpha');
        $b = $this->makeEvent(uid: 'b', title: 'Beta');
        self::assertNotSame($a->getUid(), $b->getUid());
        self::assertNotSame($a->getTitle(), $b->getTitle());
    }
}
