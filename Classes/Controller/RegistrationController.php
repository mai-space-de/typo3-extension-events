<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Controller;

use Maispace\MaiBase\Controller\AbstractActionController;
use Maispace\MaiBase\Controller\Traits\FlashMessageTrait;
use Maispace\MaiEvents\Domain\Model\EventRecord;
use Maispace\MaiEvents\Domain\Model\Registration;
use Maispace\MaiEvents\Domain\Repository\EventRepository;
use Maispace\MaiEvents\Domain\Repository\RegistrationRepository;
use Maispace\MaiEvents\Service\RecurrenceExpander;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class RegistrationController extends AbstractActionController
{
    use FlashMessageTrait;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly MailMessage $mailMessage,
        private readonly RecurrenceExpander $recurrenceExpander,
    ) {}

    public function showAction(int $eventUid = 0, int $occurrenceStart = 0): ResponseInterface
    {
        $eventUid = $this->resolveEventUid($eventUid);
        if ($eventUid === 0) {
            return $this->htmlResponse('<p class="mai-registration__empty">' . htmlspecialchars($this->translateOrDefault('registration.noEventAvailable', 'No event available for registration.')) . '</p>');
        }

        $event = $this->eventRepository->findByUid($eventUid);
        if (!$event instanceof EventRecord) {
            return $this->htmlResponse('<p class="mai-registration__empty">' . htmlspecialchars($this->translateOrDefault('registration.eventNotFound', 'Event not found.')) . '</p>');
        }

        $occurrenceStart = $this->resolveOccurrenceStart($event, $occurrenceStart);
        if ($occurrenceStart === 0 || !$this->isValidOccurrence($event, $occurrenceStart)) {
            return $this->htmlResponse('<p class="mai-registration__empty">' . htmlspecialchars($this->translateOrDefault('registration.occurrenceInvalid', 'This event occurrence is not valid.')) . '</p>');
        }

        $registrationCount = $this->registrationRepository->countByEventAndOccurrence($eventUid, $occurrenceStart);
        $isOpen = $event->isRegistrationOpenForOccurrence($occurrenceStart)
            && ($event->getMaxAttendees() === 0 || $registrationCount < $event->getMaxAttendees() || $event->isHasWaitingList());

        $this->view->assignMultiple([
            'event' => $event,
            'occurrenceStart' => $occurrenceStart,
            'registrationCount' => $registrationCount,
            'isOpen' => $isOpen,
            'registration' => new Registration(),
        ]);

        return $this->htmlResponse();
    }

    #[Validate(['validator' => 'NotEmpty', 'param' => 'registration'])]
    public function registerAction(int $eventUid, Registration $registration, int $occurrenceStart = 0): ResponseInterface
    {
        $event = $this->eventRepository->findByUid($eventUid);
        if (!$event instanceof EventRecord) {
            return $this->htmlResponse('<p class="mai-registration__empty">' . htmlspecialchars($this->translateOrDefault('registration.eventNotFound', 'Event not found.')) . '</p>');
        }

        $occurrenceStart = $this->resolveOccurrenceStart($event, $occurrenceStart);
        if ($occurrenceStart === 0 || !$this->isValidOccurrence($event, $occurrenceStart)) {
            return $this->htmlResponse('<p class="mai-registration__empty">' . htmlspecialchars($this->translateOrDefault('registration.occurrenceInvalid', 'This event occurrence is not valid.')) . '</p>');
        }

        if (!$event->isRegistrationOpenForOccurrence($occurrenceStart)) {
            return $this->htmlResponse('<p class="mai-registration__empty">' . htmlspecialchars($this->translateOrDefault('registration.closed', 'Registration for this event is closed.')) . '</p>');
        }

        $registrationCount = $this->registrationRepository->countByEventAndOccurrence($eventUid, $occurrenceStart);
        $isFull = $event->getMaxAttendees() > 0 && $registrationCount >= $event->getMaxAttendees();

        $registration->setEvent($eventUid);
        $registration->setOccurrenceStart($occurrenceStart);
        $registration->setRegisteredAt(time());
        $registration->setConfirmationToken(bin2hex(random_bytes(32)));
        $registration->setWaitingList($isFull && $event->isHasWaitingList());
        $registration->setStatus($registration->isWaitingList() ? 'waiting' : 'registered');

        $this->registrationRepository->add($registration);
        $this->persistenceManager->persistAll();

        $this->sendConfirmationEmail($registration, $event);

        $this->view->assignMultiple([
            'event' => $event,
            'occurrenceStart' => $occurrenceStart,
            'registration' => $registration,
        ]);

        return $this->htmlResponse();
    }

    public function confirmAction(string $token): ResponseInterface
    {
        $registration = $this->registrationRepository->findByConfirmationToken($token);

        if ($registration === null || $registration->isConfirmed()) {
            $this->flashError('registration.confirm.invalid');
            return $this->redirectToUri($this->uriBuilder->buildFrontendUri());
        }

        $registration->setConfirmedAt(time());
        $registration->setStatus('registered');
        $this->registrationRepository->update($registration);
        $this->persistenceManager->persistAll();

        $event = $this->eventRepository->findByUid($registration->getEvent());

        $this->view->assignMultiple([
            'registration' => $registration,
            'event' => $event,
            'occurrenceStart' => $registration->getOccurrenceStart(),
        ]);

        return $this->htmlResponse();
    }

    private function sendConfirmationEmail(Registration $registration, EventRecord $event): void
    {
        $confirmUrl = $this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(true)
            ->uriFor('confirm', ['token' => $registration->getConfirmationToken()], 'Registration', 'MaiEvents', 'Registration');

        $occurrenceLabel = $registration->getOccurrenceStart() > 0
            ? date('d.m.Y H:i', $registration->getOccurrenceStart())
            : '';

        $subject = $event->getTitle() . ' – Registrierung bestätigen';
        if ($occurrenceLabel !== '') {
            $subject .= ' (' . $occurrenceLabel . ')';
        }

        $this->mailMessage
            ->to(new Address($registration->getEmail(), $registration->getFullName()))
            ->subject($subject)
            ->text(
                'Bitte bestätigen Sie Ihre Anmeldung unter: ' . $confirmUrl,
            )
            ->send();
    }

    private function translateOrDefault(string $key, string $default): string
    {
        return LocalizationUtility::translate($key, 'mai_events') ?? $default;
    }

    private function resolveEventUid(int $eventUid): int
    {
        if ($eventUid > 0) {
            return $eventUid;
        }

        $settingsUid = (int) ($this->settings['eventUid'] ?? 0);
        if ($settingsUid > 0) {
            return $settingsUid;
        }

        $events = $this->eventRepository->findUpcoming(1);
        $firstEvent = $events->getFirst();
        if ($firstEvent instanceof EventRecord) {
            return (int) $firstEvent->getUid();
        }

        return 0;
    }

    private function resolveOccurrenceStart(EventRecord $event, int $occurrenceStart): int
    {
        if ($occurrenceStart > 0) {
            return $occurrenceStart;
        }

        return (int) ($event->getStartDate() ?? 0);
    }

    private function isValidOccurrence(EventRecord $event, int $occurrenceStart): bool
    {
        $seriesStart = $event->getStartDateAsDateTime();
        if ($seriesStart === null) {
            return false;
        }

        $seriesEnd = $event->getEndDateAsDateTime() ?? $seriesStart;
        $until = $event->getRecurrenceUntilAsDateTime();

        if (!$event->isRecurring()) {
            return $occurrenceStart === (int) $event->getStartDate();
        }

        return $this->recurrenceExpander->isValidOccurrence(
            $seriesStart,
            $seriesEnd,
            $event->getRecurrenceFrequency(),
            $until,
            $occurrenceStart,
            $event->getRecurrenceMonthWeekday(),
        );
    }
}
