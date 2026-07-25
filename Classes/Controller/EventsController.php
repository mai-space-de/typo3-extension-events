<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Controller;

use Maispace\MaiBase\Controller\AbstractActionController;
use Maispace\MaiBase\Controller\Traits\ResponseHelpersTrait;
use Maispace\MaiEvents\Service\CalendarService;
use Maispace\MaiEvents\Service\ICalExportService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class EventsController extends AbstractActionController
{
    use ResponseHelpersTrait;

    public function __construct(
        private readonly ICalExportService $iCalExportService,
        private readonly CalendarService $calendarService,
    ) {}

    public function listAction(): ResponseInterface
    {
        // Allow override via GET parameter, e.g. ?tx_maievents_view=week&tx_maievents_date=2024-06-01
        $requestedViewMode = is_string($_GET['tx_maievents_view'] ?? null) ? $_GET['tx_maievents_view'] : null;
        $requestedDate = is_string($_GET['tx_maievents_date'] ?? null) ? $_GET['tx_maievents_date'] : null;

        $calendar = $this->calendarService->buildCalendar(
            requestedViewMode: $requestedViewMode,
            requestedDate: $requestedDate,
            configuredViewMode: (string) ($this->settings['viewMode'] ?? $this->settings['defaultViewMode'] ?? 'month'),
            configuredDate: '',
            listLimit: (int) ($this->settings['listLimit'] ?? 10),
            categoryUid: (int) ($this->settings['categoryUid'] ?? 0),
            contentUid: (int) ($this->getContentObjectData()['uid'] ?? 0),
        );
        $calendar['locale'] = $this->resolveLocale();

        $this->view->assign('calendar', $calendar);

        return $this->htmlResponse($this->view->render('Calendar/FullCalendar'));
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

        $events = $this->calendarService->aggregateEvents(
            $start,
            $end,
            (int) ($this->settings['categoryUid'] ?? 0),
        );
        $icalContent = $this->iCalExportService->generate($events);

        return $this->fileDownloadResponse($icalContent, 'events.ics', 'text/calendar; charset=utf-8');
    }

    private function resolveLocale(): string
    {
        $language = $this->request->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            $hreflang = $language->getHreflang();
            if ($hreflang !== '') {
                return strtolower(substr($hreflang, 0, 2));
            }
            $locale = $language->getLocale()->getName();
            if ($locale !== '') {
                return strtolower(substr($locale, 0, 2));
            }
        }

        return 'de';
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
}
