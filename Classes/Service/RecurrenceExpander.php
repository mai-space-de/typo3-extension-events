<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Service;

/**
 * Expands a recurring event series into concrete occurrences for a date window.
 *
 * Interval is always 1 (daily / weekly / monthly / yearly / monthly_weekday).
 * Monthly and yearly steps clamp the day-of-month when the target month is shorter,
 * always relative to the original series start day (so Jan 31 → Feb 28 → Mar 31).
 * `monthly_weekday` repeats the n-th / last weekday of each month; the weekday is
 * taken from the series start, `$monthWeekday` selects 1/2/3/4 or -1 (last).
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
    public const FREQUENCY_MONTHLY_WEEKDAY = 'monthly_weekday';
    public const FREQUENCY_YEARLY = 'yearly';

    public const MONTH_WEEKDAY_LAST = -1;

    private const ALLOWED_FREQUENCIES = [
        self::FREQUENCY_DAILY,
        self::FREQUENCY_WEEKLY,
        self::FREQUENCY_MONTHLY,
        self::FREQUENCY_MONTHLY_WEEKDAY,
        self::FREQUENCY_YEARLY,
    ];

    private const ALLOWED_MONTH_WEEKDAYS = [1, 2, 3, 4, self::MONTH_WEEKDAY_LAST];

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
        int $monthWeekday = 0,
    ): array {
        $durationSeconds = max(0, $seriesEnd->getTimestamp() - $seriesStart->getTimestamp());

        if ($frequency === self::FREQUENCY_NONE || !in_array($frequency, self::ALLOWED_FREQUENCIES, true)) {
            return $this->singleOccurrenceIfInRange($seriesStart, $durationSeconds, $rangeStart, $rangeEnd);
        }

        if ($frequency === self::FREQUENCY_MONTHLY_WEEKDAY) {
            return $this->expandMonthlyWeekday(
                $seriesStart,
                $durationSeconds,
                $until,
                $rangeStart,
                $rangeEnd,
                $monthWeekday,
            );
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
        int $monthWeekday = 0,
    ): bool {
        if ($occurrenceStart <= 0) {
            return false;
        }

        $rangeStart = (new \DateTimeImmutable())->setTimestamp($occurrenceStart);
        $rangeEnd = $rangeStart->modify('+1 second');
        $occurrences = $this->expand(
            $seriesStart,
            $seriesEnd,
            $frequency,
            $until,
            $rangeStart,
            $rangeEnd,
            $monthWeekday,
        );

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
    private function expandMonthlyWeekday(
        \DateTimeImmutable $seriesStart,
        int $durationSeconds,
        ?\DateTimeImmutable $until,
        \DateTimeImmutable $rangeStart,
        \DateTimeImmutable $rangeEnd,
        int $monthWeekday,
    ): array {
        $resolvedWeekday = $this->resolveMonthWeekday($seriesStart, $monthWeekday);
        $occurrences = [];
        $monthOffset = 0;
        $seriesOccurrenceCount = 0;

        // Bound month iteration so skipped months (no 4th weekday) cannot spin forever.
        $maxMonths = self::MAX_OCCURRENCES * 2;

        while ($seriesOccurrenceCount < self::MAX_OCCURRENCES && $monthOffset < $maxMonths) {
            $cursor = $this->weekdayOccurrenceInMonth($seriesStart, $monthOffset, $resolvedWeekday);
            ++$monthOffset;

            if ($cursor === null) {
                continue;
            }

            if ($cursor->getTimestamp() < $seriesStart->getTimestamp()) {
                continue;
            }

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

            ++$seriesOccurrenceCount;
        }

        return $occurrences;
    }

    /**
     * Resolve which weekday-in-month index to use. Explicit 1/2/3/4/-1 win;
     * otherwise infer from the series start date (last if that weekday does not
     * occur again later in the same month).
     */
    private function resolveMonthWeekday(\DateTimeImmutable $seriesStart, int $monthWeekday): int
    {
        if (in_array($monthWeekday, self::ALLOWED_MONTH_WEEKDAYS, true)) {
            return $monthWeekday;
        }

        $nextSameWeekday = $seriesStart->modify('+1 week');
        if ((int) $nextSameWeekday->format('n') !== (int) $seriesStart->format('n')
            || (int) $nextSameWeekday->format('Y') !== (int) $seriesStart->format('Y')
        ) {
            return self::MONTH_WEEKDAY_LAST;
        }

        return min(4, (int) ceil(((int) $seriesStart->format('j')) / 7));
    }

    /**
     * Return the n-th / last weekday in the month of `$seriesStart + $monthsOffset`,
     * or null when that occurrence does not exist in the target month.
     */
    private function weekdayOccurrenceInMonth(
        \DateTimeImmutable $seriesStart,
        int $monthsOffset,
        int $monthWeekday,
    ): ?\DateTimeImmutable {
        $weekday = (int) $seriesStart->format('N'); // 1=Mon … 7=Sun
        $hour = (int) $seriesStart->format('H');
        $minute = (int) $seriesStart->format('i');
        $second = (int) $seriesStart->format('s');

        $monthStart = $seriesStart
            ->modify('first day of this month')
            ->setTime(0, 0, 0)
            ->modify('+' . $monthsOffset . ' month');
        $year = (int) $monthStart->format('Y');
        $month = (int) $monthStart->format('n');

        if ($monthWeekday === self::MONTH_WEEKDAY_LAST) {
            $lastDay = $monthStart->modify('last day of this month');
            $diff = ((int) $lastDay->format('N') - $weekday + 7) % 7;
            return $lastDay->modify('-' . $diff . ' day')->setTime($hour, $minute, $second);
        }

        $firstWeekdayOffset = ($weekday - (int) $monthStart->format('N') + 7) % 7;
        $first = $monthStart->modify('+' . $firstWeekdayOffset . ' day');
        $candidate = $first->modify('+' . ($monthWeekday - 1) . ' week')->setTime($hour, $minute, $second);

        if ((int) $candidate->format('Y') !== $year || (int) $candidate->format('n') !== $month) {
            return null;
        }

        return $candidate;
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
