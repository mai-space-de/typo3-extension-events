<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Registration extends AbstractEntity
{
    protected int $event = 0;
    protected int $occurrenceStart = 0;
    protected string $firstName = '';
    protected string $lastName = '';
    protected string $email = '';
    protected string $status = 'registered';
    protected bool $waitingList = false;
    protected string $confirmationToken = '';
    protected ?int $registeredAt = null;
    protected ?int $confirmedAt = null;

    public function getEvent(): int
    {
        return $this->event;
    }

    public function setEvent(int $event): void
    {
        $this->event = $event;
    }

    public function getOccurrenceStart(): int
    {
        return $this->occurrenceStart;
    }

    public function setOccurrenceStart(int $occurrenceStart): void
    {
        $this->occurrenceStart = $occurrenceStart;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isWaitingList(): bool
    {
        return $this->waitingList;
    }

    public function setWaitingList(bool $waitingList): void
    {
        $this->waitingList = $waitingList;
    }

    public function getConfirmationToken(): string
    {
        return $this->confirmationToken;
    }

    public function setConfirmationToken(string $confirmationToken): void
    {
        $this->confirmationToken = $confirmationToken;
    }

    public function getRegisteredAt(): ?int
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(?int $registeredAt): void
    {
        $this->registeredAt = $registeredAt;
    }

    public function getConfirmedAt(): ?int
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?int $confirmedAt): void
    {
        $this->confirmedAt = $confirmedAt;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null && $this->confirmedAt > 0;
    }
}
