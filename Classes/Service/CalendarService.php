<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Service;

use Maispace\MaiEvents\Domain\Model\Event;
use Maispace\MaiEvents\EventProvider\EventProviderInterface;
use Maispace\MaiEvents\EventProvider\TxEventProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Typolink\LinkFactory;

/**
 * Builds the calendar data structure shared by the Calendar FLUIDTEMPLATE
 * content element (via EventsDataProcessor) and the Extbase Events plugin
 * (via EventsController) for the FullCalendar frontend mount.
 *
 * Supported view modes: month (default), week, list — mapped to FullCalendar views.
 */
final class CalendarService
{
    private const ALLOWED_VIEW_MODES = ['month', 'week', 'list'];

    private const VIEW_MODE_TO_FULLCALENDAR = [
        'month' => 'dayGridMonth',
        'week' => 'timeGridWeek',
        'list' => 'listUpcoming',
    ];

    private const LIST_LIMIT_MIN = 1;
    private const LIST_LIMIT_MAX = 100;

    /**
     * RTE tags kept in the info-popup description (links resolved separately).
     *
     * @var list<string>
     */
    private const DESCRIPTION_ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li', 'a', 'h3', 'h4', 'blockquote',
    ];

    /**
     * Tags that must be removed entirely (not unwrapped) to avoid leaking their contents.
     *
     * @var list<string>
     */
    private const DESCRIPTION_REMOVED_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea',
    ];

    /**
     * @param iterable<EventProviderInterface>|null $providers Explicit providers (mainly for tests);
     *                                                          null resolves providers from the container.
     */
    public function __construct(private readonly ?iterable $providers = null) {}

    /**
     * @return array{
     *     viewMode: string,
     *     initialView: string,
     *     currentDate: \DateTimeImmutable,
     *     start: \DateTimeImmutable,
     *     end: \DateTimeImmutable,
     *     events: Event[],
     *     fullCalendarEvents: list<array<string, mixed>>,
     *     contentUid: int,
     *     locale: string,
     *     listLimit: int,
     * }
     */
    public function buildCalendar(
        ?string $requestedViewMode,
        ?string $requestedDate,
        string $configuredViewMode = 'month',
        string $configuredDate = '',
        int $listLimit = 10,
        int $categoryUid = 0,
        int $contentUid = 0,
    ): array {
        $viewMode = $this->resolveViewMode($requestedViewMode, $configuredViewMode);
        $currentDate = $this->resolveCurrentDate($requestedDate, $configuredDate);
        $listLimit = $this->clampListLimit($listLimit);

        // Preload a wide window so FullCalendar can navigate client-side without reloads.
        [$start, $end] = $this->calculatePreloadRange($currentDate);
        $events = $this->aggregateEvents($start, $end, $categoryUid);

        return [
            'viewMode' => $viewMode,
            'initialView' => self::VIEW_MODE_TO_FULLCALENDAR[$viewMode],
            'currentDate' => $currentDate,
            'start' => $start,
            'end' => $end,
            'events' => $events,
            'fullCalendarEvents' => $this->mapToFullCalendarEvents($events),
            'contentUid' => $contentUid,
            'locale' => 'de',
            'listLimit' => $listLimit,
        ];
    }

    public function clampListLimit(int $listLimit): int
    {
        return max(self::LIST_LIMIT_MIN, min($listLimit, self::LIST_LIMIT_MAX));
    }

    /**
     * @param Event[] $events
     * @return list<array<string, mixed>>
     */
    public function mapToFullCalendarEvents(array $events): array
    {
        $mapped = [];
        foreach ($events as $event) {
            $mapped[] = [
                'id' => $event->getUid(),
                'title' => $event->getTitle(),
                'start' => $event->getStart()->format('c'),
                'end' => $event->getEnd()->format('c'),
                'allDay' => $event->isAllDay(),
                'url' => $event->getUrl(),
                'extendedProps' => [
                    'description' => $this->formatDescriptionHtml($event->getDescription()),
                    'location' => $event->getLocation(),
                ],
            ];
        }

        return $mapped;
    }

    /**
     * Keep safe RTE markup (including <a>) for the calendar info popup.
     * Typolink / t3:// hrefs are resolved to frontend URLs; unsafe schemes are dropped.
     */
    private function formatDescriptionHtml(string $description): string
    {
        $description = trim($description);
        if ($description === '') {
            return '';
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="mai-cal-desc-root">' . $description . '</div>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded !== true) {
            return htmlspecialchars(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $root = $document->getElementById('mai-cal-desc-root');
        if (!$root instanceof \DOMElement) {
            return '';
        }

        $this->sanitizeDescriptionNode($root);

        $html = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }

    private function sanitizeDescriptionNode(\DOMNode $node): void
    {
        if (!$node->hasChildNodes()) {
            return;
        }

        /** @var list<\DOMNode> $children */
        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (!$child instanceof \DOMElement) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DESCRIPTION_REMOVED_TAGS, true)) {
                $child->parentNode?->removeChild($child);
                continue;
            }

            if (!in_array($tag, self::DESCRIPTION_ALLOWED_TAGS, true)) {
                $this->sanitizeDescriptionNode($child);
                $this->unwrapElement($child);
                continue;
            }

            if ($tag === 'a') {
                $this->sanitizeDescriptionAnchor($child);
            } else {
                $this->stripElementAttributes($child);
            }

            $this->sanitizeDescriptionNode($child);
        }
    }

    private function sanitizeDescriptionAnchor(\DOMElement $anchor): void
    {
        $href = trim(html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $resolved = $this->resolveDescriptionHref($href);

        $this->stripElementAttributes($anchor);

        if ($resolved === '' || !$this->isSafeDescriptionHref($resolved)) {
            $this->unwrapElement($anchor);

            return;
        }

        $anchor->setAttribute('href', $resolved);
        if (preg_match('#^https?:#i', $resolved) === 1) {
            $anchor->setAttribute('target', '_blank');
            $anchor->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function resolveDescriptionHref(string $href): string
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return $href;
        }

        if (
            preg_match('#^(https?:)?//#i', $href) === 1
            || str_starts_with($href, '/')
            || str_starts_with($href, 'mailto:')
            || str_starts_with($href, 'tel:')
        ) {
            return $href;
        }

        try {
            $linkFactory = GeneralUtility::makeInstance(LinkFactory::class);
            $result = $linkFactory->createUri($href);

            return $result->getUrl();
        } catch (\Throwable) {
            return '';
        }
    }

    private function isSafeDescriptionHref(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '#')) {
            return true;
        }

        $lower = strtolower($href);
        foreach (['javascript:', 'data:', 'vbscript:'] as $dangerous) {
            if (str_starts_with($lower, $dangerous)) {
                return false;
            }
        }

        return preg_match('~^(https?:|mailto:|tel:|/|#)~i', $href) === 1;
    }

    private function stripElementAttributes(\DOMElement $element): void
    {
        /** @var list<string> $names */
        $names = [];
        foreach ($element->attributes ?? [] as $attribute) {
            $names[] = $attribute->name;
        }
        foreach ($names as $name) {
            $element->removeAttribute($name);
        }
    }

    private function unwrapElement(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private function resolveViewMode(?string $requested, string $configured): string
    {
        if ($requested !== null && in_array($requested, self::ALLOWED_VIEW_MODES, true)) {
            return $requested;
        }

        if (in_array($configured, self::ALLOWED_VIEW_MODES, true)) {
            return $configured;
        }

        return 'month';
    }

    private function resolveCurrentDate(?string $requested, string $configuredDate): \DateTimeImmutable
    {
        if ($requested !== null && $requested !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $requested);
            if ($parsed !== false) {
                return $parsed->setTime(0, 0, 0);
            }
        }

        if ($configuredDate !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $configuredDate);
            if ($parsed !== false) {
                return $parsed->setTime(0, 0, 0);
            }
        }

        return new \DateTimeImmutable('today');
    }

    /**
     * Preload window: today −3 months … today +12 months (relative to currentDate).
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function calculatePreloadRange(\DateTimeImmutable $currentDate): array
    {
        $start = $currentDate->modify('-3 months')->modify('first day of this month')->setTime(0, 0, 0);
        $end = $currentDate->modify('+12 months')->modify('last day of this month')->setTime(23, 59, 59);

        return [$start, $end];
    }

    /**
     * @return Event[]
     */
    public function aggregateEvents(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $categoryUid = 0,
    ): array {
        $events = [];
        foreach ($this->getEventProviders() as $provider) {
            foreach ($provider->getEvents($start, $end, $categoryUid) as $event) {
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
        if ($this->providers !== null) {
            yield from $this->providers;

            return;
        }

        try {
            $container = GeneralUtility::getContainer();
            if ($container->has(TxEventProvider::class)) {
                yield $container->get(TxEventProvider::class);
            }
        } catch (\Throwable) {
            // Container unavailable or error — continue with empty result
        }
    }
}
