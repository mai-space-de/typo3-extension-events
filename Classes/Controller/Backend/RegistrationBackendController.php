<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Controller\Backend;

use Maispace\MaiBase\Controller\Backend\AbstractBackendController;
use Maispace\MaiBase\Controller\Traits\ResponseHelpersTrait;
use Maispace\MaiEvents\Domain\Repository\EventRepository;
use Maispace\MaiEvents\Domain\Repository\RegistrationRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;

#[AsController]
class RegistrationBackendController extends AbstractBackendController
{
    use ResponseHelpersTrait;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        IconFactory $iconFactory,
        private readonly EventRepository $eventRepository,
        private readonly RegistrationRepository $registrationRepository,
    ) {
        parent::__construct($moduleTemplateFactory, $iconFactory);
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->createModuleTemplate();

        $events = $this->eventRepository->findAll();
        $selectedEventUid = (int) ($this->request->getParsedBody()['eventUid']
            ?? $this->request->getQueryParams()['eventUid']
            ?? 0);

        $registrations = $selectedEventUid > 0
            ? $this->registrationRepository->findByEvent($selectedEventUid)
            : [];

        $this->assignMultiple($moduleTemplate, [
            'events' => $events,
            'selectedEventUid' => $selectedEventUid,
            'registrations' => $registrations,
        ]);

        return $this->renderModuleResponse($moduleTemplate, 'Index');
    }

    public function exportCsvAction(): ResponseInterface
    {
        $eventUid = (int) ($this->request->getParsedBody()['eventUid']
            ?? $this->request->getQueryParams()['eventUid']
            ?? 0);

        if ($eventUid === 0) {
            return $this->indexAction();
        }

        $registrations = $this->registrationRepository->findByEvent($eventUid);
        $rows = [['first_name', 'last_name', 'email', 'status', 'waiting_list', 'occurrence_start', 'registered_at', 'confirmed_at']];

        foreach ($registrations as $registration) {
            $rows[] = [
                $registration->getFirstName(),
                $registration->getLastName(),
                $registration->getEmail(),
                $registration->getStatus(),
                $registration->isWaitingList() ? '1' : '0',
                $registration->getOccurrenceStart() > 0
                    ? date('Y-m-d H:i:s', $registration->getOccurrenceStart())
                    : '',
                $registration->getRegisteredAt() ? date('Y-m-d H:i:s', $registration->getRegisteredAt()) : '',
                $registration->getConfirmedAt() ? date('Y-m-d H:i:s', $registration->getConfirmedAt()) : '',
            ];
        }

        return $this->csvResponse($rows, 'registrations-event-' . $eventUid . '.csv');
    }
}
