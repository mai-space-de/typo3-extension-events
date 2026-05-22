<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Controller;

use Maispace\MaiBase\Controller\AbstractActionController;
use Maispace\MaiBase\Controller\Traits\ResponseHelpersTrait;
use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\EventProvider\EventProviderInterface;
use Maispace\MaiEvents\Service\ICalExportService;
use Psr\Http\Message\ResponseInterface;

class EventsController extends AbstractActionController
{
    use ResponseHelpersTrait;

    public function __construct(
        private readonly iterable $eventProviders,
        private readonly ICalExportService $iCalExportService,
    ) {}

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
        foreach ($this->eventProviders as $provider) {
            foreach ($provider->getEvents($start, $end) as $event) {
                $events[] = $event;
            }
        }

        usort($events, static fn(Event $a, Event $b) => $a->getStart() <=> $b->getStart());

        return $events;
    }
}
