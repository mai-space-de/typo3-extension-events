<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\DataProcessor;

use Maispace\MaiEvents\DataProcessor\EventsDataProcessor;
use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\EventProvider\EventProviderInterface;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class EventsDataProcessorTest extends TestCase
{
    private function makeEvent(
        string $uid,
        string $title,
        string $start,
        string $end,
    ): Event {
        return new Event(
            uid: $uid,
            title: $title,
            start: new \DateTimeImmutable($start),
            end: new \DateTimeImmutable($end),
        );
    }

    private function makeProcessor(array $providers = []): EventsDataProcessor
    {
        return new EventsDataProcessor($providers);
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
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $result = $processor->process($cObj, [], ['viewMode' => 'list', 'listLimit' => 100], []);

        self::assertCount(2, $result['calendar']['events']);
    }

    public function testEventsAreSortedByStartDate(): void
    {
        $eventLater = $this->makeEvent('2', 'Later', '2024-06-20 10:00', '2024-06-20 11:00');
        $eventEarlier = $this->makeEvent('1', 'Earlier', '2024-06-05 08:00', '2024-06-05 09:00');

        $provider = $this->createMock(EventProviderInterface::class);
        $provider->method('getEvents')->willReturn([$eventLater, $eventEarlier]);

        $processor = $this->makeProcessor([$provider]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $result = $processor->process($cObj, [], ['viewMode' => 'list', 'listLimit' => 100], []);

        $events = $result['calendar']['events'];
        self::assertSame('Earlier', $events[0]->getTitle());
        self::assertSame('Later', $events[1]->getTitle());
    }

    // -------------------------------------------------------------------------
    // list view
    // -------------------------------------------------------------------------

    public function testListViewRespectsLimit(): void
    {
        $events = [];
        for ($i = 1; $i <= 20; $i++) {
            $day = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $events[] = $this->makeEvent((string) $i, "Event $i", "2024-06-{$day} 10:00", "2024-06-{$day} 11:00");
        }

        $provider = $this->createMock(EventProviderInterface::class);
        $provider->method('getEvents')->willReturn($events);

        $processor = $this->makeProcessor([$provider]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $result = $processor->process($cObj, [], ['viewMode' => 'list', 'listLimit' => 5], []);

        self::assertCount(5, $result['calendar']['events']);
    }

    // -------------------------------------------------------------------------
    // month view grid
    // -------------------------------------------------------------------------

    public function testMonthViewBuildsWeekGrid(): void
    {
        $processor = $this->makeProcessor([]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        // June 2024 – starts on Saturday, 30 days
        $_GET['tx_maievents_date'] = '2024-06-15';
        $result = $processor->process($cObj, [], ['viewMode' => 'month'], []);
        unset($_GET['tx_maievents_date']);

        $weeks = $result['calendar']['weeks'];

        // Every week must have exactly 7 days
        foreach ($weeks as $week) {
            self::assertCount(7, $week);
        }

        // Each day must have required keys
        foreach ($weeks as $week) {
            foreach ($week as $day) {
                self::assertArrayHasKey('date', $day);
                self::assertArrayHasKey('isCurrentMonth', $day);
                self::assertArrayHasKey('isToday', $day);
                self::assertArrayHasKey('events', $day);
            }
        }
    }

    public function testMonthViewAssignsEventsToCorrectDay(): void
    {
        $event = $this->makeEvent('1', 'Test', '2024-06-15 10:00', '2024-06-15 11:00');

        $provider = $this->createMock(EventProviderInterface::class);
        $provider->method('getEvents')->willReturn([$event]);

        $processor = $this->makeProcessor([$provider]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $_GET['tx_maievents_date'] = '2024-06-01';
        $result = $processor->process($cObj, [], ['viewMode' => 'month'], []);
        unset($_GET['tx_maievents_date']);

        $found = false;
        foreach ($result['calendar']['weeks'] as $week) {
            foreach ($week as $day) {
                if ($day['date']->format('Y-m-d') === '2024-06-15') {
                    self::assertCount(1, $day['events']);
                    self::assertSame('Test', $day['events'][0]->getTitle());
                    $found = true;
                }
            }
        }
        self::assertTrue($found, 'Day 2024-06-15 was not found in the month grid');
    }

    // -------------------------------------------------------------------------
    // week view grid
    // -------------------------------------------------------------------------

    public function testWeekViewBuildsSevenDayGrid(): void
    {
        $processor = $this->makeProcessor([]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $_GET['tx_maievents_date'] = '2024-06-15';
        $result = $processor->process($cObj, [], ['viewMode' => 'week'], []);
        unset($_GET['tx_maievents_date']);

        $weeks = $result['calendar']['weeks'];

        self::assertCount(1, $weeks);
        self::assertCount(7, $weeks[0]);
    }

    // -------------------------------------------------------------------------
    // navigation
    // -------------------------------------------------------------------------

    public function testMonthNavigationPointsToPrevAndNextMonth(): void
    {
        $processor = $this->makeProcessor([]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $_GET['tx_maievents_date'] = '2024-06-15';
        $result = $processor->process($cObj, [], ['viewMode' => 'month'], []);
        unset($_GET['tx_maievents_date']);

        $nav = $result['calendar']['navigation'];
        self::assertSame('2024-05', $nav['prev']->format('Y-m'));
        self::assertSame('2024-07', $nav['next']->format('Y-m'));
    }

    public function testWeekNavigationPointsToPrevAndNextWeek(): void
    {
        $processor = $this->makeProcessor([]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $_GET['tx_maievents_date'] = '2024-06-15'; // Saturday
        $result = $processor->process($cObj, [], ['viewMode' => 'week'], []);
        unset($_GET['tx_maievents_date']);

        $nav = $result['calendar']['navigation'];
        $currentDate = new \DateTimeImmutable('2024-06-15');
        $expectedPrev = $currentDate->modify('-1 week');
        $expectedNext = $currentDate->modify('+1 week');

        self::assertSame($expectedPrev->format('Y-m-d'), $nav['prev']->format('Y-m-d'));
        self::assertSame($expectedNext->format('Y-m-d'), $nav['next']->format('Y-m-d'));
    }

    // -------------------------------------------------------------------------
    // target variable
    // -------------------------------------------------------------------------

    public function testCustomTargetVariable(): void
    {
        $processor = $this->makeProcessor([]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $result = $processor->process($cObj, [], ['targetVariable' => 'myCalendar', 'viewMode' => 'list'], []);

        self::assertArrayHasKey('myCalendar', $result);
        self::assertArrayNotHasKey('calendar', $result);
    }

    // -------------------------------------------------------------------------
    // existing processedData is preserved
    // -------------------------------------------------------------------------

    public function testExistingProcessedDataIsPreserved(): void
    {
        $processor = $this->makeProcessor([]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $result = $processor->process(
            $cObj,
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
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $processor->process($cObj, [], ['viewMode' => 'list', 'categoryUid' => 42], []);
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
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $processor->process($cObj, [], ['viewMode' => 'list'], []);
    }

    // -------------------------------------------------------------------------
    // content uid (scroll anchor)
    // -------------------------------------------------------------------------

    public function testContentUidIsTakenFromContentObjectData(): void
    {
        $processor = $this->makeProcessor([]);
        $cObj = $this->createMock(ContentObjectRenderer::class);
        $cObj->data = ['uid' => 123];

        $result = $processor->process($cObj, [], ['viewMode' => 'list'], []);

        self::assertSame(123, $result['calendar']['contentUid']);
    }

    public function testContentUidDefaultsToZeroWhenUidIsAbsent(): void
    {
        $processor = $this->makeProcessor([]);
        $cObj = $this->createMock(ContentObjectRenderer::class);

        $result = $processor->process($cObj, [], ['viewMode' => 'list'], []);

        self::assertSame(0, $result['calendar']['contentUid']);
    }
}
