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
