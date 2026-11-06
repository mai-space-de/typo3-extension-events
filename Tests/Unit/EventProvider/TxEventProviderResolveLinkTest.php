<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\EventProvider;

use Maispace\MaiEvents\EventProvider\TxEventProvider;
use PHPUnit\Framework\TestCase;

final class TxEventProviderResolveLinkTest extends TestCase
{
    public function testResolvePageUrlReturnsEmptyForInvalidUid(): void
    {
        $provider = new class extends TxEventProvider {
            public function exposeResolvePageUrl(int $pageUid): string
            {
                return $this->resolvePageUrl($pageUid);
            }
        };

        self::assertSame('', $provider->exposeResolvePageUrl(0));
        self::assertSame('', $provider->exposeResolvePageUrl(-1));
    }

    public function testResolveRouteArgumentsKeepsDefaultLanguageUid(): void
    {
        $provider = new class extends TxEventProvider {
            /**
             * @return array{0: int, 1: array<string, int>}
             */
            public function exposeResolveRouteArguments(int $pageUid, int $languageId, int $defaultLanguagePageUid): array
            {
                return $this->resolveRouteArguments($pageUid, $languageId, $defaultLanguagePageUid);
            }
        };

        self::assertSame([11126, []], $provider->exposeResolveRouteArguments(11126, 0, 0));
    }

    public function testResolveRouteArgumentsUsesDefaultLanguageUidAndLanguageBase(): void
    {
        $provider = new class extends TxEventProvider {
            /**
             * @return array{0: int, 1: array<string, int>}
             */
            public function exposeResolveRouteArguments(int $pageUid, int $languageId, int $defaultLanguagePageUid): array
            {
                return $this->resolveRouteArguments($pageUid, $languageId, $defaultLanguagePageUid);
            }
        };

        self::assertSame(
            [11126, ['_language' => 2]],
            $provider->exposeResolveRouteArguments(11420, 2, 11126),
        );
    }

    public function testResolveLanguageIdFallsBackToZeroOutsideFrontend(): void
    {
        $provider = new class extends TxEventProvider {
            public function exposeResolveLanguageId(): int
            {
                return $this->resolveLanguageId();
            }
        };

        self::assertSame(0, $provider->exposeResolveLanguageId());
    }
}
