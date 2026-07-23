<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Controller;

use Maispace\MaiBase\Controller\AbstractActionController;
use Maispace\MaiBase\Controller\Traits\ResponseHelpersTrait;
use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\EventProvider\EventProviderInterface;
use Maispace\MaiEvents\EventProvider\TxEventProvider;
use Maispace\MaiEvents\Service\ICalExportService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class EventsController extends AbstractActionController
{
    use ResponseHelpersTrait;

    public function __construct(
        private readonly ICalExportService $iCalExportService,
    ) {}

    public function listAction(): ResponseInterface
    {
        $currentDate = new \DateTimeImmutable('today');
        $start = $currentDate->setTime(0, 0, 0);
        $end = $start->modify('+1 year')->setTime(23, 59, 59);
        $events = $this->aggregateEvents($start, $end);

        $limit = (int) ($this->settings['listLimit'] ?? 10);
        if ($limit > 0) {
            $events = array_slice($events, 0, $limit);
        }

        $this->view->assign('calendar', [
            'viewMode' => 'list',
            'currentDate' => $currentDate,
            'start' => $start,
            'end' => $end,
            'events' => $events,
            'navigation' => [
                'prev' => $currentDate->modify('-1 month'),
                'next' => $currentDate->modify('+1 month'),
            ],
        ]);

        return $this->htmlResponse();
    }

    public function icalExportAction(): ResponseInterface
    {
        $start = $this->resolveDate(
            $this->request->hasArgument('start') ? (string) $this->request->getArgument('start') : '',
            new \DateTimeImmutable('first day of this month'),
        );
        $end = $this->resolveDate(
            $this->request->hasArgument('end') ? (string) $this->request->getArgument('end') : '',
            new \DateTimeImmutable('last day of this month midnight'),
        );

        $events = $this->aggregateEvents($start, $end);
        $icalContent = $this->iCalExportService->generate($events);

        return $this->fileDownloadResponse($icalContent, 'events.ics', 'text/calendar; charset=utf-8');
    }

    private function resolveDate(string $value, \DateTimeImmutable $default): \DateTimeImmutable
    {
        if ($value === '') {
            return $default->setTime(0, 0, 0);
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($parsed === false) {
            return $default->setTime(0, 0, 0);
        }

        return $parsed->setTime(0, 0, 0);
    }

    private function aggregateEvents(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $events = [];
        foreach ($this->getEventProviders() as $provider) {
            foreach ($provider->getEvents($start, $end) as $event) {
                $events[] = $event;
            }
        }

        usort($events, static fn(Event $a, Event $b) => $a->getStart() <=> $b->getStart());

        return $events;
    }

    /**
     * @return iterable<EventProviderInterface>
     */
    private function getEventProviders(): iterable
    {
        try {
            $container = GeneralUtility::getContainer();
            if ($container->has(TxEventProvider::class)) {
                yield $container->get(TxEventProvider::class);
            }
        } catch (\Throwable $e) {
            // Container unavailable — continue with empty result
        }
    }
}
