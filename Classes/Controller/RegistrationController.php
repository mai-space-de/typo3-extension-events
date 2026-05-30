<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Controller;

use Maispace\MaiBase\Controller\AbstractActionController;
use Maispace\MaiBase\Controller\Traits\FlashMessageTrait;
use Maispace\MaiEvents\Domain\Model\EventRecord;
use Maispace\MaiEvents\Domain\Model\Registration;
use Maispace\MaiEvents\Domain\Repository\EventRepository;
use Maispace\MaiEvents\Domain\Repository\RegistrationRepository;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class RegistrationController extends AbstractActionController
{
    use FlashMessageTrait;

    public function __construct(
        private readonly EventRepository $eventRepository,
        private readonly RegistrationRepository $registrationRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly MailMessage $mailMessage,
    ) {}

    public function showAction(int $eventUid = 0): ResponseInterface
    {
        $eventUid = $this->resolveEventUid($eventUid);
        if ($eventUid === 0) {
            return $this->htmlResponse('<p>No event available for registration.</p>');
        }

        $event = $this->eventRepository->findByUid($eventUid);
        if (!$event instanceof EventRecord) {
            return $this->htmlResponse('<p>Event not found.</p>');
        }

        $registrationCount = $this->registrationRepository->countByEvent($eventUid);
        $isOpen = $event->isRegistrationOpen()
            && ($event->getMaxAttendees() === 0 || $registrationCount < $event->getMaxAttendees() || $event->isHasWaitingList());

        $this->view->assignMultiple([
            'event' => $event,
            'registrationCount' => $registrationCount,
            'isOpen' => $isOpen,
            'registration' => new Registration(),
        ]);

        return $this->htmlResponse();
    }

    #[Validate(['validator' => 'NotEmpty', 'param' => 'registration'])]
    public function registerAction(int $eventUid, Registration $registration): ResponseInterface
    {
        $event = $this->eventRepository->findByUid($eventUid);
        if (!$event instanceof EventRecord) {
            return $this->htmlResponse('<p>Event not found.</p>');
        }

        $registrationCount = $this->registrationRepository->countByEvent($eventUid);
        $isFull = $event->getMaxAttendees() > 0 && $registrationCount >= $event->getMaxAttendees();

        $registration->setEvent($eventUid);
        $registration->setRegisteredAt(time());
        $registration->setConfirmationToken(bin2hex(random_bytes(32)));
        $registration->setWaitingList($isFull && $event->isHasWaitingList());
        $registration->setStatus($registration->isWaitingList() ? 'waiting' : 'registered');

        $this->registrationRepository->add($registration);
        $this->persistenceManager->persistAll();

        $this->sendConfirmationEmail($registration, $event);

        $this->view->assignMultiple([
            'event' => $event,
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
        ]);

        return $this->htmlResponse();
    }

    private function sendConfirmationEmail(Registration $registration, EventRecord $event): void
    {
        $confirmUrl = $this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(true)
            ->uriFor('confirm', ['token' => $registration->getConfirmationToken()], 'Registration', 'MaiEvents', 'Registration');

        $this->mailMessage
            ->to(new Address($registration->getEmail(), $registration->getFullName()))
            ->subject($event->getTitle() . ' – Registrierung bestätigen')
            ->text(
                'Bitte bestätigen Sie Ihre Anmeldung unter: ' . $confirmUrl,
            )
            ->send();
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
            return $firstEvent->getUid();
        }

        return 0;
    }
}
