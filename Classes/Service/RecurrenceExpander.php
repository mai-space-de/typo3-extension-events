<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Service;

/**
 * Expands a recurring event series into concrete occurrences for a date window.
 *
 * Interval is always 1 (daily / weekly / monthly / yearly). Monthly and yearly
 * steps clamp the day-of-month when the target month is shorter, always relative
 * to the original series start day (so Jan 31 → Feb 28 → Mar 31).
 * When `$until` is null the series is open-ended and only bounded by the
 * requested date window (and MAX_OCCURRENCES).
 */
final class RecurrenceExpander
{
    public const MAX_OCCURRENCES = 500;

    public const FREQUENCY_NONE = '';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_YEARLY = 'yearly';

    private const ALLOWED_FREQUENCIES = [
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_YEARLY,
    ];

    /**
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    public function expand(
        \DateTimeImmutable $seriesStart,
        \DateTimeImmutable $seriesEnd,
        string $frequency,
        ?\DateTimeImmutable $until,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
    ): array {
        $durationSeconds = max(0, $seriesEnd->getTimestamp() - $seriesStart->getTimestamp());

        if ($frequency === self::FREQUENCY_NONE || !in_array($frequency, self::ALLOWED_FREQUENCIES, true)) {
            return $this->singleOccurrenceIfInRange($seriesStart, $durationSeconds, $rangeStart, $rangeEnd);
        }

        $occurrences = [];
        $index = 0;

        while ($index < self::MAX_OCCURRENCES) {
            $cursor = $this->nthOccurrence($seriesStart, $frequency, $index);

            // Optional end: empty until means the series never ends.
            if ($until !== null && $cursor->getTimestamp() > $until->getTimestamp()) {
                break;
            }

            if ($cursor->getTimestamp() >= $rangeEnd->getTimestamp()
                && $cursor->getTimestamp() > $seriesStart->getTimestamp()
            ) {
                break;
            }

            if ($cursor->getTimestamp() >= $rangeStart->getTimestamp()
                && $cursor->getTimestamp() < $rangeEnd->getTimestamp()
            ) {
                $occurrences[] = [
                    'start' => $cursor,
                    'end' => $cursor->modify('+' . $durationSeconds . ' seconds'),
                ];
            }

            ++$index;
        }

        return $occurrences;
    }

    public function isValidOccurrence(
        \DateTimeImmutable $seriesStart,
        \DateTimeImmutable $seriesEnd,
        string $frequency,
        ?\DateTimeImmutable $until,
        int $occurrenceStart,
    ): bool {
        if ($occurrenceStart <= 0) {
            return false;
        }

        $rangeStart = (new \DateTimeImmutable())->setTimestamp($occurrenceStart);
        $rangeEnd = $rangeStart->modify('+1 second');
        $occurrences = $this->expand($seriesStart, $seriesEnd, $frequency, $until, $rangeStart, $rangeEnd);

        foreach ($occurrences as $occurrence) {
            if ($occurrence['start']->getTimestamp() === $occurrenceStart) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private function singleOccurrenceIfInRange(
        \DateTimeImmutable $seriesStart,
        int $durationSeconds,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
    ): array {
        if ($seriesStart->getTimestamp() < $rangeStart->getTimestamp()
            || $seriesStart->getTimestamp() >= $rangeEnd->getTimestamp()
        ) {
            return [];
        }

        return [[
            'start' => $seriesStart,
            'end' => $seriesStart->modify('+' . $durationSeconds . ' seconds'),
        ]];
    }

    private function nthOccurrence(\DateTimeImmutable $seriesStart, string $frequency, int $index): \DateTimeImmutable
    {
        if ($index === 0) {
            return $seriesStart;
        }

        return match ($frequency) {
            self::FREQUENCY_DAILY => $seriesStart->modify('+' . $index . ' day'),
            self::FREQUENCY_WEEKLY => $seriesStart->modify('+' . $index . ' week'),
            self::FREQUENCY_MONTHLY => $this->addMonthsClamped($seriesStart, $index),
            self::FREQUENCY_YEARLY => $this->addYearsClamped($seriesStart, $index),
            default => $seriesStart,
        };
    }

    private function addMonthsClamped(\DateTimeImmutable $seriesStart, int $months): \DateTimeImmutable
    {
        $day = (int) $seriesStart->format('j');
        $hour = (int) $seriesStart->format('H');
        $minute = (int) $seriesStart->format('i');
        $second = (int) $seriesStart->format('s');

        $base = $seriesStart->modify('first day of this month')->setTime(0, 0, 0)->modify('+' . $months . ' month');
        $clampedDay = min($day, (int) $base->format('t'));

        return $base->setDate((int) $base->format('Y'), (int) $base->format('n'), $clampedDay)
            ->setTime($hour, $minute, $second);
    }

    private function addYearsClamped(\DateTimeImmutable $seriesStart, int $years): \DateTimeImmutable
    {
        $month = (int) $seriesStart->format('n');
        $day = (int) $seriesStart->format('j');
        $hour = (int) $seriesStart->format('H');
        $minute = (int) $seriesStart->format('i');
        $second = (int) $seriesStart->format('s');

        $targetYear = (int) $seriesStart->format('Y') + $years;
        $clampedDay = $day;
        if ($month === 2 && $day === 29 && !checkdate(2, 29, $targetYear)) {
            $clampedDay = 28;
        }

        return $seriesStart->setDate($targetYear, $month, $clampedDay)->setTime($hour, $minute, $second);
    }
}
