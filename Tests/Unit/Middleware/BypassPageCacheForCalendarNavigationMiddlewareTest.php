<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Middleware;

use Maispace\MaiEvents\Middleware\BypassPageCacheForCalendarNavigationMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

final class BypassPageCacheForCalendarNavigationMiddlewareTest extends TestCase
{
    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function navigationQueryProvider(): array
    {
        return [
            'view' => [['tx_maievents_view' => 'list']],
            'date' => [['tx_maievents_date' => '2026-12-01']],
            'both' => [['tx_maievents_view' => 'week', 'tx_maievents_date' => '2026-12-01']],
        ];
    }

    /**
     * @param array<string, string> $query
     */
    #[Test]
    #[DataProvider('navigationQueryProvider')]
    public function navigationParametersDisablePageCacheTest(array $query): void
    {
        $captured = $this->process((new ServerRequest('https://example.test/'))->withQueryParams($query));

        $instruction = $captured->getAttribute('frontend.cache.instruction');
        self::assertInstanceOf(CacheInstruction::class, $instruction);
        self::assertFalse($instruction->isCachingAllowed());
    }

    #[Test]
    public function requestWithoutNavigationParametersKeepsCacheEnabledTest(): void
    {
        $captured = $this->process((new ServerRequest('https://example.test/'))->withQueryParams(['foo' => 'bar']));

        self::assertNull($captured->getAttribute('frontend.cache.instruction'));
    }

    private function process(ServerRequestInterface $request): ServerRequestInterface
    {
        $handler = new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $received = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->received = $request;

                return new \TYPO3\CMS\Core\Http\Response();
            }
        };

        (new BypassPageCacheForCalendarNavigationMiddleware())->process($request, $handler);
        self::assertNotNull($handler->received);

        return $handler->received;
    }
}
