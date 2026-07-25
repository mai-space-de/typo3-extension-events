<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\DataProcessor;

use Maispace\MaiEvents\DataProcessor\EventsDataProcessor;
use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\EventProvider\EventProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class EventsDataProcessorTest extends TestCase
{
    private function makeEvent(
        string $uid,
        string $title,
        string $start,
        string $end,
        string $url = '',
        string $description = '',
        string $location = '',
    ): Event {
        return new Event(
            uid: $uid,
            title: $title,
            start: new \DateTimeImmutable($start),
            end: new \DateTimeImmutable($end),
            description: $description,
            location: $location,
            url: $url,
        );
    }

    private function makeProcessor(array $providers = []): EventsDataProcessor
    {
        return new EventsDataProcessor($providers);
    }

    private function makeContentObject(array $data = []): ContentObjectRenderer
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with('language')->willReturn(null);

        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->data = $data;
        $cObj->method('getRequest')->willReturn($request);

        return $cObj;
    }

    // -------------------------------------------------------------------------
    // aggregation
    // -------------------------------------------------------------------------

    public function testAggregatesEventsFromMultipleProviders(): void
    {
        $event1 = $this->makeEvent('1', 'Event A', '2024-06-10 10:00', '2024-06-10 11:00');
        $event2 = $this->makeEvent('2', 'Event B', '2024-06-15 14:00', '2024-06-15 15:00');

        $providerA = $this->createMock(EventProviderInterface::class);
        $providerA->method('getEvents')->willReturn([$event1]);

        $providerB = $this->createMock(EventProviderInterface::class);
        $providerB->method('getEvents')->willReturn([$event2]);

        $processor = $this->makeProcessor([$providerA, $providerB]);
        $result = $processor->process($this->makeContentObject(), [], ['viewMode' => 'list'], []);

        self::assertCount(2, $result['calendar']['events']);
    }

    public function testEventsAreSortedByStartDate(): void
    {
        $eventLater = $this->makeEvent('2', 'Later', '2024-06-20 10:00', '2024-06-20 11:00');
        $eventEarlier = $this->makeEvent('1', 'Earlier', '2024-06-05 08:00', '2024-06-05 09:00');

        $provider = $this->createMock(EventProviderInterface::class);
        $provider->method('getEvents')->willReturn([$eventLater, $eventEarlier]);

        $processor = $this->makeProcessor([$provider]);
        $result = $processor->process($this->makeContentObject(), [], ['viewMode' => 'list'], []);

        $events = $result['calendar']['events'];
        self::assertSame('Earlier', $events[0]->getTitle());
        self::assertSame('Later', $events[1]->getTitle());
    }

    // -------------------------------------------------------------------------
    // FullCalendar payload
    // -------------------------------------------------------------------------

    public function testMapsViewModeToFullCalendarInitialView(): void
    {
        $processor = $this->makeProcessor([]);
        $cObj = $this->makeContentObject();

        $month = $processor->process($cObj, [], ['viewMode' => 'month'], []);
        self::assertSame('dayGridMonth', $month['calendar']['initialView']);

        $week = $processor->process($cObj, [], ['viewMode' => 'week'], []);
        self::assertSame('timeGridWeek', $week['calendar']['initialView']);

        $list = $processor->process($cObj, [], ['viewMode' => 'list'], []);
        self::assertSame('listWeek', $list['calendar']['initialView']);
    }

    public function testBuildsFullCalendarEventsPayload(): void
    {
        $event = $this->makeEvent(
            '1',
            'Test',
            '2024-06-15 10:00',
            '2024-06-15 11:00',
            url: 'https://example.org/event',
            description: '<p>Hello <strong>world</strong></p>',
            location: 'Hall A',
        );

        $provider = $this->createMock(EventProviderInterface::class);
        $provider->method('getEvents')->willReturn([$event]);

        $processor = $this->makeProcessor([$provider]);
        $_GET['tx_maievents_date'] = '2024-06-01';
        $result = $processor->process($this->makeContentObject(), [], ['viewMode' => 'month'], []);
        unset($_GET['tx_maievents_date']);

        self::assertArrayHasKey('fullCalendarEvents', $result['calendar']);
        self::assertCount(1, $result['calendar']['fullCalendarEvents']);

        $fcEvent = $result['calendar']['fullCalendarEvents'][0];
        self::assertSame('1', $fcEvent['id']);
        self::assertSame('Test', $fcEvent['title']);
        self::assertSame('https://example.org/event', $fcEvent['url']);
        self::assertSame('Hall A', $fcEvent['extendedProps']['location']);
        self::assertSame('Hello world', $fcEvent['extendedProps']['description']);
        self::assertArrayNotHasKey('weeks', $result['calendar']);
        self::assertArrayNotHasKey('navigation', $result['calendar']);
    }

    public function testPreloadRangeSpansMinusThreeToPlusTwelveMonths(): void
    {
        $processor = $this->makeProcessor([]);
        $_GET['tx_maievents_date'] = '2024-06-15';
        $result = $processor->process($this->makeContentObject(), [], ['viewMode' => 'month'], []);
        unset($_GET['tx_maievents_date']);

        self::assertSame('2024-03-01', $result['calendar']['start']->format('Y-m-d'));
        self::assertSame('2025-06-30', $result['calendar']['end']->format('Y-m-d'));
    }

    public function testLocaleDefaultsToDe(): void
    {
        $processor = $this->makeProcessor([]);
        $result = $processor->process($this->makeContentObject(), [], ['viewMode' => 'month'], []);

        self::assertSame('de', $result['calendar']['locale']);
    }

    // -------------------------------------------------------------------------
    // target variable
    // -------------------------------------------------------------------------

    public function testCustomTargetVariable(): void
    {
        $processor = $this->makeProcessor([]);
        $result = $processor->process(
            $this->makeContentObject(),
            [],
            ['targetVariable' => 'myCalendar', 'viewMode' => 'list'],
            [],
        );

        self::assertArrayHasKey('myCalendar', $result);
        self::assertArrayNotHasKey('calendar', $result);
    }

    // -------------------------------------------------------------------------
    // existing processedData is preserved
    // -------------------------------------------------------------------------

    public function testExistingProcessedDataIsPreserved(): void
    {
        $processor = $this->makeProcessor([]);
        $result = $processor->process(
            $this->makeContentObject(),
            [],
            ['viewMode' => 'list'],
            ['existingKey' => 'existingValue'],
        );

        self::assertSame('existingValue', $result['existingKey']);
    }

    // -------------------------------------------------------------------------
    // category filter
    // -------------------------------------------------------------------------

    public function testCategoryUidIsPassedToProviders(): void
    {
        $provider = $this->createMock(EventProviderInterface::class);
        $provider->expects(self::once())
            ->method('getEvents')
            ->with(
                self::isInstanceOf(\DateTimeInterface::class),
                self::isInstanceOf(\DateTimeInterface::class),
                42,
            )
            ->willReturn([]);

        $processor = $this->makeProcessor([$provider]);
        $processor->process($this->makeContentObject(), [], ['viewMode' => 'list', 'categoryUid' => 42], []);
    }

    public function testCategoryUidDefaultsToZero(): void
    {
        $provider = $this->createMock(EventProviderInterface::class);
        $provider->expects(self::once())
            ->method('getEvents')
            ->with(
                self::isInstanceOf(\DateTimeInterface::class),
                self::isInstanceOf(\DateTimeInterface::class),
                0,
            )
            ->willReturn([]);

        $processor = $this->makeProcessor([$provider]);
        $processor->process($this->makeContentObject(), [], ['viewMode' => 'list'], []);
    }

    // -------------------------------------------------------------------------
    // content uid (scroll anchor)
    // -------------------------------------------------------------------------

    public function testContentUidIsTakenFromContentObjectData(): void
    {
        $processor = $this->makeProcessor([]);
        $result = $processor->process($this->makeContentObject(['uid' => 123]), [], ['viewMode' => 'list'], []);

        self::assertSame(123, $result['calendar']['contentUid']);
    }

    public function testContentUidDefaultsToZeroWhenUidIsAbsent(): void
    {
        $processor = $this->makeProcessor([]);
        $result = $processor->process($this->makeContentObject(), [], ['viewMode' => 'list'], []);

        self::assertSame(0, $result['calendar']['contentUid']);
    }
}
