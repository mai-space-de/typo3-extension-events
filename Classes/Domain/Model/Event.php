<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Domain\Model;

/**
 * Represents a single calendar event (one concrete occurrence).
 */
class Event
{
    public function __construct(
        protected string $uid,
        protected string $title,
        protected \DateTimeInterface $start,
        protected \DateTimeInterface $end,
        protected string $description = '',
        protected string $location = '',
        protected string $url = '',
        protected bool $allDay = false,
        protected string $source = '',
        protected int $seriesUid = 0,
        protected int $occurrenceStart = 0,
    ) {}

    public function getUid(): string
    {
        return $this->uid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStart(): \DateTimeInterface
    {
        return $this->start;
    }

    public function getEnd(): \DateTimeInterface
    {
        return $this->end;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getSeriesUid(): int
    {
        return $this->seriesUid;
    }

    public function getOccurrenceStart(): int
    {
        return $this->occurrenceStart > 0
            ? $this->occurrenceStart
            : $this->start->getTimestamp();
    }
}
