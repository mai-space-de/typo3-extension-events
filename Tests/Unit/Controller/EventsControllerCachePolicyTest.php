<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Controller;

use Maispace\MaiEvents\Controller\EventsController;
use Maispace\MaiEvents\Service\CalendarService;
use Maispace\MaiEvents\Service\ICalExportService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

final class EventsControllerCachePolicyTest extends TestCase
{
    private CacheDataCollector $collector;

    private CacheInstruction $instruction;

    protected function setUp(): void
    {
        $this->collector = new CacheDataCollector();
        $this->instruction = new CacheInstruction();
        unset($_GET['tx_maievents_view'], $_GET['tx_maievents_date']);
    }

    protected function tearDown(): void
    {
        unset($_GET['tx_maievents_view'], $_GET['tx_maievents_date']);
    }

    #[Test]
    public function defaultRequestIsCacheableTaggedAndExpiresTomorrowTest(): void
    {
        $this->applyPolicy([]);

        self::assertTrue($this->instruction->isCachingAllowed());
        self::assertSame(
            [EventsController::CALENDAR_CACHE_TAG],
            array_map(static fn($tag): string => $tag->name, $this->collector->getCacheTags()),
        );
        $lifetime = $this->collector->resolveLifetime();
        self::assertGreaterThanOrEqual(60, $lifetime);
        self::assertLessThanOrEqual(86400 + 3600, $lifetime);
    }

    #[Test]
    public function navigationQueryParameterDisablesCachingTest(): void
    {
        $this->applyPolicy(['tx_maievents_view' => 'week']);

        self::assertFalse($this->instruction->isCachingAllowed());
        self::assertSame([], $this->collector->getCacheTags());
    }

    #[Test]
    public function navigationGetSuperglobalDisablesCachingTest(): void
    {
        $_GET['tx_maievents_date'] = '1999-01-01';

        $this->applyPolicy([]);

        self::assertFalse($this->instruction->isCachingAllowed());
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function applyPolicy(array $queryParams): void
    {
        $request = new Request(
            (new ServerRequest('https://example.org/', 'GET'))
                ->withAttribute('extbase', new ExtbaseRequestParameters())
                ->withAttribute('frontend.cache.instruction', $this->instruction)
                ->withAttribute('frontend.cache.collector', $this->collector)
                ->withQueryParams($queryParams),
        );

        $controller = new class (
            $this->createMock(ICalExportService::class),
            new CalendarService([]),
            $request,
        ) extends EventsController {
            public function __construct(ICalExportService $i, CalendarService $c, RequestInterface $request)
            {
                parent::__construct($i, $c);
                $this->request = $request;
            }

            public function run(): void
            {
                $this->applyCachePolicy($this->hasNavigationParameters());
            }
        };
        $controller->run();
    }
}
