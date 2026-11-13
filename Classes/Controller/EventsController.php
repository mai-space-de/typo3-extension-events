<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Controller;

use Maispace\MaiBase\Controller\AbstractActionController;
use Maispace\MaiBase\Controller\Traits\ResponseHelpersTrait;
use Maispace\MaiEvents\Service\CalendarService;
use Maispace\MaiEvents\Service\ICalExportService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

class EventsController extends AbstractActionController
{
    use ResponseHelpersTrait;

    /**
     * Page cache tag of every cached calendar. Flushed by EventCacheInvalidationHook.
     */
    public const string CALENDAR_CACHE_TAG = 'tx_maievents_calendar';

    public const array NAVIGATION_PARAMETERS = ['tx_maievents_view', 'tx_maievents_date'];

    public function __construct(
        private readonly ICalExportService $iCalExportService,
        private readonly CalendarService $calendarService,
    ) {}

    public function listAction(): ResponseInterface
    {
        // Allow override via GET parameter, e.g. ?tx_maievents_view=week&tx_maievents_date=2024-06-01
        $requestedViewMode = is_string($_GET['tx_maievents_view'] ?? null) ? $_GET['tx_maievents_view'] : null;
        $requestedDate = is_string($_GET['tx_maievents_date'] ?? null) ? $_GET['tx_maievents_date'] : null;

        $this->applyCachePolicy($this->hasNavigationParameters());

        $contentData = $this->getContentObjectData();
        $seriesPageUid = $this->calendarService->resolveSeriesPageUid((int) ($contentData['pid'] ?? 0));

        $calendar = $this->calendarService->buildCalendar(
            requestedViewMode: $requestedViewMode,
            requestedDate: $requestedDate,
            configuredViewMode: (string) ($this->settings['viewMode'] ?? $this->settings['defaultViewMode'] ?? 'month'),
            configuredDate: '',
            listLimit: (int) ($this->settings['listLimit'] ?? 10),
            categoryUid: (int) ($this->settings['categoryUid'] ?? 0),
            contentUid: (int) ($contentData['uid'] ?? 0),
            seriesPageUid: $seriesPageUid,
        );
        $calendar['locale'] = $this->resolveLocale();

        $this->view->assign('calendar', $calendar);

        return $this->htmlResponse($this->view->render('Calendar/FullCalendar'));
    }

    /**
     * The calendar is cacheable only in its default state.
     *
     * The navigation parameters are excluded from cHash validation, so the page
     * cache identifier is identical with and without them. Whatever variant is
     * rendered for such a request must therefore never be stored, otherwise one
     * crafted URL would replace the cached calendar for every visitor.
     *
     * The default state depends on the current day, so the entry expires at
     * midnight and is additionally tagged for invalidation on event changes.
     */
    protected function applyCachePolicy(bool $hasNavigationParameters): void
    {
        $cacheInstruction = $this->request->getAttribute('frontend.cache.instruction');
        if ($hasNavigationParameters) {
            if ($cacheInstruction instanceof CacheInstruction) {
                $cacheInstruction->disableCache('mai_events: calendar navigation parameters present');
            }

            return;
        }

        $collector = $this->request->getAttribute('frontend.cache.collector');
        if ($collector instanceof CacheDataCollector) {
            $collector->addCacheTags(new CacheTag(self::CALENDAR_CACHE_TAG));
            $collector->restrictMaximumLifetime($this->secondsUntilTomorrow());
        }
    }

    protected function hasNavigationParameters(): bool
    {
        $queryParams = $this->request->getQueryParams();
        foreach (self::NAVIGATION_PARAMETERS as $parameter) {
            if (array_key_exists($parameter, $_GET) || array_key_exists($parameter, $queryParams)) {
                return true;
            }
        }

        return false;
    }

    private function secondsUntilTomorrow(): int
    {
        return max(60, (new \DateTimeImmutable('tomorrow'))->getTimestamp() - time());
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

        $seriesPageUid = $this->calendarService->resolveSeriesPageUid(
            (int) ($this->getContentObjectData()['pid'] ?? 0),
        );

        $events = $this->calendarService->aggregateEvents(
            $start,
            $end,
            (int) ($this->settings['categoryUid'] ?? 0),
            $seriesPageUid,
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
