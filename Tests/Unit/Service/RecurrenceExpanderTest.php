<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Service;

use Maispace\MaiEvents\Service\RecurrenceExpander;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecurrenceExpanderTest extends TestCase
{
    private RecurrenceExpander $subject;

    protected function setUp(): void
    {
        $this->subject = new RecurrenceExpander();
    }

    #[Test]
    public function expandWithoutFrequencyReturnsSingleOccurrenceInRange(): void
    {
        $start = new \DateTimeImmutable('2026-06-10 18:00:00');
        $end = new \DateTimeImmutable('2026-06-10 20:00:00');
        $rangeStart = new \DateTimeImmutable('2026-06-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2026-07-01 00:00:00');

        $result = $this->subject->expand($start, $end, '', null, $rangeStart, $rangeEnd);

        self::assertCount(1, $result);
        self::assertSame($start->getTimestamp(), $result[0]['start']->getTimestamp());
        self::assertSame($end->getTimestamp(), $result[0]['end']->getTimestamp());
    }

    #[Test]
    public function expandWithoutFrequencyReturnsEmptyOutsideRange(): void
    {
        $start = new \DateTimeImmutable('2026-05-10 18:00:00');
        $end = new \DateTimeImmutable('2026-05-10 20:00:00');
        $rangeStart = new \DateTimeImmutable('2026-06-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2026-07-01 00:00:00');

        self::assertSame([], $this->subject->expand($start, $end, '', null, $rangeStart, $rangeEnd));
    }

    #[Test]
    public function expandDailyReturnsOccurrencesWithinWindow(): void
    {
        $start = new \DateTimeImmutable('2026-06-01 10:00:00');
        $end = new \DateTimeImmutable('2026-06-01 11:00:00');
        $until = new \DateTimeImmutable('2026-06-05 23:59:59');
        $rangeStart = new \DateTimeImmutable('2026-06-02 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2026-06-05 00:00:00');

        $result = $this->subject->expand($start, $end, 'daily', $until, $rangeStart, $rangeEnd);

        self::assertCount(3, $result);
        self::assertSame('2026-06-02 10:00:00', $result[0]['start']->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-03 10:00:00', $result[1]['start']->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-04 10:00:00', $result[2]['start']->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-02 11:00:00', $result[0]['end']->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function expandWeeklyReturnsWeeklyOccurrences(): void
    {
        $start = new \DateTimeImmutable('2026-06-03 18:00:00'); // Wednesday
        $end = new \DateTimeImmutable('2026-06-03 19:30:00');
        $until = new \DateTimeImmutable('2026-06-30 23:59:59');
        $rangeStart = new \DateTimeImmutable('2026-06-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2026-07-01 00:00:00');

        $result = $this->subject->expand($start, $end, 'weekly', $until, $rangeStart, $rangeEnd);

        self::assertCount(4, $result);
        self::assertSame('2026-06-03', $result[0]['start']->format('Y-m-d'));
        self::assertSame('2026-06-10', $result[1]['start']->format('Y-m-d'));
        self::assertSame('2026-06-17', $result[2]['start']->format('Y-m-d'));
        self::assertSame('2026-06-24', $result[3]['start']->format('Y-m-d'));
    }

    #[Test]
    public function expandMonthlyClampsShortMonths(): void
    {
        $start = new \DateTimeImmutable('2026-01-31 12:00:00');
        $end = new \DateTimeImmutable('2026-01-31 13:00:00');
        $until = new \DateTimeImmutable('2026-04-30 23:59:59');
        $rangeStart = new \DateTimeImmutable('2026-01-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2026-05-01 00:00:00');

        $result = $this->subject->expand($start, $end, 'monthly', $until, $rangeStart, $rangeEnd);

        self::assertCount(4, $result);
        self::assertSame('2026-01-31', $result[0]['start']->format('Y-m-d'));
        self::assertSame('2026-02-28', $result[1]['start']->format('Y-m-d'));
        self::assertSame('2026-03-31', $result[2]['start']->format('Y-m-d'));
        self::assertSame('2026-04-30', $result[3]['start']->format('Y-m-d'));
    }

    #[Test]
    public function expandYearlyReturnsYearlyOccurrences(): void
    {
        $start = new \DateTimeImmutable('2024-02-29 09:00:00');
        $end = new \DateTimeImmutable('2024-02-29 10:00:00');
        $until = new \DateTimeImmutable('2027-12-31 23:59:59');
        $rangeStart = new \DateTimeImmutable('2024-01-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2028-01-01 00:00:00');

        $result = $this->subject->expand($start, $end, 'yearly', $until, $rangeStart, $rangeEnd);

        self::assertCount(4, $result);
        self::assertSame('2024-02-29', $result[0]['start']->format('Y-m-d'));
        self::assertSame('2025-02-28', $result[1]['start']->format('Y-m-d'));
        self::assertSame('2026-02-28', $result[2]['start']->format('Y-m-d'));
        self::assertSame('2027-02-28', $result[3]['start']->format('Y-m-d'));
    }

    #[Test]
    public function expandRespectsUntilBoundary(): void
    {
        $start = new \DateTimeImmutable('2026-06-01 10:00:00');
        $end = new \DateTimeImmutable('2026-06-01 11:00:00');
        $until = new \DateTimeImmutable('2026-06-03 10:00:00');
        $rangeStart = new \DateTimeImmutable('2026-06-01 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2026-07-01 00:00:00');

        $result = $this->subject->expand($start, $end, 'daily', $until, $rangeStart, $rangeEnd);

        self::assertCount(3, $result);
        self::assertSame('2026-06-03 10:00:00', $result[2]['start']->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function expandWithoutUntilContinuesThroughRequestedWindow(): void
    {
        $start = new \DateTimeImmutable('2026-06-01 10:00:00');
        $end = new \DateTimeImmutable('2026-06-01 11:00:00');
        $rangeStart = new \DateTimeImmutable('2026-06-10 00:00:00');
        $rangeEnd = new \DateTimeImmutable('2026-06-15 00:00:00');

        $result = $this->subject->expand($start, $end, 'daily', null, $rangeStart, $rangeEnd);

        self::assertCount(5, $result);
        self::assertSame('2026-06-10 10:00:00', $result[0]['start']->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-14 10:00:00', $result[4]['start']->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function isValidOccurrenceAcceptsGeneratedTimestamp(): void
    {
        $start = new \DateTimeImmutable('2026-06-03 18:00:00');
        $end = new \DateTimeImmutable('2026-06-03 19:00:00');
        $until = new \DateTimeImmutable('2026-06-30 23:59:59');
        $occurrence = new \DateTimeImmutable('2026-06-17 18:00:00');

        self::assertTrue(
            $this->subject->isValidOccurrence($start, $end, 'weekly', $until, $occurrence->getTimestamp()),
        );
    }

    #[Test]
    public function isValidOccurrenceRejectsForeignTimestamp(): void
    {
        $start = new \DateTimeImmutable('2026-06-03 18:00:00');
        $end = new \DateTimeImmutable('2026-06-03 19:00:00');
        $until = new \DateTimeImmutable('2026-06-30 23:59:59');
        $occurrence = new \DateTimeImmutable('2026-06-18 18:00:00');

        self::assertFalse(
            $this->subject->isValidOccurrence($start, $end, 'weekly', $until, $occurrence->getTimestamp()),
        );
    }
}
