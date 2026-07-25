<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class EventRecord extends AbstractEntity
{
    protected string $title = '';
    protected string $description = '';
    protected string $location = '';
    protected ?int $startDate = null;
    protected ?int $endDate = null;
    protected ?int $registrationDeadline = null;
    protected string $recurrenceFrequency = '';
    protected ?int $recurrenceUntil = null;
    protected int $maxAttendees = 0;
    protected bool $hasWaitingList = false;

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $image;

    public function __construct()
    {
        $this->image = new ObjectStorage();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(string $location): void
    {
        $this->location = $location;
    }

    public function getStartDate(): ?int
    {
        return $this->startDate;
    }

    public function setStartDate(?int $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getStartDateAsDateTime(): ?\DateTimeImmutable
    {
        if ($this->startDate === null || $this->startDate === 0) {
            return null;
        }
        return (new \DateTimeImmutable())->setTimestamp($this->startDate);
    }

    public function getEndDate(): ?int
    {
        return $this->endDate;
    }

    public function setEndDate(?int $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getEndDateAsDateTime(): ?\DateTimeImmutable
    {
        if ($this->endDate === null || $this->endDate === 0) {
            return null;
        }
        return (new \DateTimeImmutable())->setTimestamp($this->endDate);
    }

    public function getRegistrationDeadline(): ?int
    {
        return $this->registrationDeadline;
    }

    public function setRegistrationDeadline(?int $registrationDeadline): void
    {
        $this->registrationDeadline = $registrationDeadline;
    }

    public function getRecurrenceFrequency(): string
    {
        return $this->recurrenceFrequency;
    }

    public function setRecurrenceFrequency(string $recurrenceFrequency): void
    {
        $this->recurrenceFrequency = $recurrenceFrequency;
    }

    public function isRecurring(): bool
    {
        return $this->recurrenceFrequency !== '';
    }

    public function getRecurrenceUntil(): ?int
    {
        return $this->recurrenceUntil;
    }

    public function setRecurrenceUntil(?int $recurrenceUntil): void
    {
        $this->recurrenceUntil = $recurrenceUntil;
    }

    public function getRecurrenceUntilAsDateTime(): ?\DateTimeImmutable
    {
        if ($this->recurrenceUntil === null || $this->recurrenceUntil === 0) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($this->recurrenceUntil);
    }

    /**
     * Resolve the registration deadline for a concrete occurrence.
     *
     * For recurring events the series deadline is treated as an offset from the
     * first start_date and applied to each occurrence.
     */
    public function getRegistrationDeadlineForOccurrence(int $occurrenceStart): ?int
    {
        if ($this->registrationDeadline === null || $this->registrationDeadline === 0) {
            return null;
        }

        if (!$this->isRecurring() || $this->startDate === null || $this->startDate === 0) {
            return $this->registrationDeadline;
        }

        $offset = $this->startDate - $this->registrationDeadline;

        return $occurrenceStart - $offset;
    }

    public function isRegistrationOpenForOccurrence(int $occurrenceStart): bool
    {
        $deadline = $this->getRegistrationDeadlineForOccurrence($occurrenceStart);
        if ($deadline === null) {
            return true;
        }

        return $deadline > time();
    }

    public function isRegistrationOpen(): bool
    {
        if ($this->registrationDeadline === null || $this->registrationDeadline === 0) {
            return true;
        }
        return $this->registrationDeadline > time();
    }

    public function getMaxAttendees(): int
    {
        return $this->maxAttendees;
    }

    public function setMaxAttendees(int $maxAttendees): void
    {
        $this->maxAttendees = $maxAttendees;
    }

    public function isHasWaitingList(): bool
    {
        return $this->hasWaitingList;
    }

    public function setHasWaitingList(bool $hasWaitingList): void
    {
        $this->hasWaitingList = $hasWaitingList;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getImage(): ObjectStorage
    {
        return $this->image;
    }

    /**
     * @param ObjectStorage<FileReference> $image
     */
    public function setImage(ObjectStorage $image): void
    {
        $this->image = $image;
    }

    public function getFirstImage(): ?FileReference
    {
        $this->image->rewind();
        return $this->image->current() ?: null;
    }
}
