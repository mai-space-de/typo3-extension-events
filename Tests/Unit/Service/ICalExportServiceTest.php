<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Service;

use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\Service\ICalExportService;
use PHPUnit\Framework\TestCase;

class ICalExportServiceTest extends TestCase
{
    private ICalExportService $service;

    protected function setUp(): void
    {
        $this->service = new ICalExportService();
    }

    private function makeEvent(
        string $uid = 'test-uid',
        string $title = 'Test Event',
        string $start = '2024-06-15 10:00:00',
        string $end = '2024-06-15 11:00:00',
        string $description = '',
        string $location = '',
        string $url = '',
        bool $allDay = false,
    ): Event {
        return new Event(
            uid: $uid,
            title: $title,
            start: new \DateTimeImmutable($start, new \DateTimeZone('UTC')),
            end: new \DateTimeImmutable($end, new \DateTimeZone('UTC')),
            description: $description,
            location: $location,
            url: $url,
            allDay: $allDay,
        );
    }

    // -------------------------------------------------------------------------
    // structure
    // -------------------------------------------------------------------------

    public function testOutputStartsWithVcalendar(): void
    {
        $output = $this->service->generate([]);
        self::assertStringStartsWith('BEGIN:VCALENDAR', $output);
    }

    public function testOutputEndsWithVcalendar(): void
    {
        $output = $this->service->generate([]);
        self::assertStringContainsString('END:VCALENDAR', $output);
    }

    public function testOutputContainsVersion(): void
    {
        $output = $this->service->generate([]);
        self::assertStringContainsString('VERSION:2.0', $output);
    }

    public function testOutputContainsProdid(): void
    {
        $output = $this->service->generate([]);
        self::assertStringContainsString('PRODID:', $output);
    }

    // -------------------------------------------------------------------------
    // VEVENT
    // -------------------------------------------------------------------------

    public function testEventIsWrappedInVevent(): void
    {
        $output = $this->service->generate([$this->makeEvent()]);
        self::assertStringContainsString('BEGIN:VEVENT', $output);
        self::assertStringContainsString('END:VEVENT', $output);
    }

    public function testEventTitleAppearsAsSummary(): void
    {
        $output = $this->service->generate([$this->makeEvent(title: 'My Test Event')]);
        self::assertStringContainsString('SUMMARY:My Test Event', $output);
    }

    public function testEventUidAppearsInOutput(): void
    {
        $output = $this->service->generate([$this->makeEvent(uid: 'abc-123')]);
        self::assertStringContainsString('UID:abc-123@mai.space', $output);
    }

    public function testEventStartAndEndAppearInOutput(): void
    {
        $output = $this->service->generate([$this->makeEvent(start: '2024-06-15 10:00:00', end: '2024-06-15 11:00:00')]);
        self::assertStringContainsString('DTSTART:20240615T100000Z', $output);
        self::assertStringContainsString('DTEND:20240615T110000Z', $output);
    }

    public function testAllDayEventUsesDateFormat(): void
    {
        $event = $this->makeEvent(
            start: '2024-06-15 00:00:00',
            end: '2024-06-15 00:00:00',
            allDay: true,
        );
        $output = $this->service->generate([$event]);
        self::assertStringContainsString('DTSTART;VALUE=DATE:20240615', $output);
        self::assertStringContainsString('DTEND;VALUE=DATE:20240615', $output);
    }

    public function testDescriptionAppearsWhenSet(): void
    {
        $event = $this->makeEvent(description: 'Some description');
        $output = $this->service->generate([$event]);
        self::assertStringContainsString('DESCRIPTION:Some description', $output);
    }

    public function testDescriptionIsOmittedWhenEmpty(): void
    {
        $output = $this->service->generate([$this->makeEvent(description: '')]);
        self::assertStringNotContainsString('DESCRIPTION:', $output);
    }

    public function testLocationAppearsWhenSet(): void
    {
        $event = $this->makeEvent(location: 'Berlin');
        $output = $this->service->generate([$event]);
        self::assertStringContainsString('LOCATION:Berlin', $output);
    }

    public function testUrlAppearsWhenSet(): void
    {
        $event = $this->makeEvent(url: 'https://example.com');
        $output = $this->service->generate([$event]);
        self::assertStringContainsString('URL:https://example.com', $output);
    }

    // -------------------------------------------------------------------------
    // escaping
    // -------------------------------------------------------------------------

    public function testCommasInTitleAreEscaped(): void
    {
        $event = $this->makeEvent(title: 'Event, with comma');
        $output = $this->service->generate([$event]);
        self::assertStringContainsString('SUMMARY:Event\\, with comma', $output);
    }

    public function testSemicolonsInTitleAreEscaped(): void
    {
        $event = $this->makeEvent(title: 'Event; with semicolon');
        $output = $this->service->generate([$event]);
        self::assertStringContainsString('SUMMARY:Event\\; with semicolon', $output);
    }

    public function testNewlinesInDescriptionAreEscaped(): void
    {
        $event = $this->makeEvent(description: "Line one\nLine two");
        $output = $this->service->generate([$event]);
        self::assertStringContainsString('DESCRIPTION:Line one\\nLine two', $output);
    }

    // -------------------------------------------------------------------------
    // multiple events
    // -------------------------------------------------------------------------

    public function testMultipleEventsAreAllIncluded(): void
    {
        $events = [
            $this->makeEvent(uid: 'evt-1', title: 'First'),
            $this->makeEvent(uid: 'evt-2', title: 'Second'),
            $this->makeEvent(uid: 'evt-3', title: 'Third'),
        ];
        $output = $this->service->generate($events);

        self::assertSame(3, substr_count($output, 'BEGIN:VEVENT'));
        self::assertStringContainsString('UID:evt-1@mai.space', $output);
        self::assertStringContainsString('UID:evt-2@mai.space', $output);
        self::assertStringContainsString('UID:evt-3@mai.space', $output);
    }

    // -------------------------------------------------------------------------
    // line endings
    // -------------------------------------------------------------------------

    public function testOutputUsesCarriageReturnLineFeed(): void
    {
        $output = $this->service->generate([]);
        self::assertStringContainsString("\r\n", $output);
    }

    // -------------------------------------------------------------------------
    // calendar name
    // -------------------------------------------------------------------------

    public function testCustomCalendarNameAppearsInOutput(): void
    {
        $output = $this->service->generate([], 'My Calendar');
        self::assertStringContainsString('X-WR-CALNAME:My Calendar', $output);
    }
}
