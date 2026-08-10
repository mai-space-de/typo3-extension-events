<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\EventProvider;

use Maispace\MaiEvents\EventProvider\TxEventProvider;
use PHPUnit\Framework\TestCase;

final class TxEventProviderResolveLinkTest extends TestCase
{
    public function testEmptyLinkResolvesToEmptyString(): void
    {
        $provider = new class extends TxEventProvider {
            public function exposeResolveLink(string $link): string
            {
                return $this->resolveLink($link);
            }
        };

        self::assertSame('', $provider->exposeResolveLink(''));
        self::assertSame('', $provider->exposeResolveLink('   '));
    }

    public function testHttpUrlsAreReturnedAsIs(): void
    {
        $provider = new class extends TxEventProvider {
            public function exposeResolveLink(string $link): string
            {
                return $this->resolveLink($link);
            }
        };

        self::assertSame('https://example.org/a', $provider->exposeResolveLink('https://example.org/a'));
        self::assertSame('http://example.org/a', $provider->exposeResolveLink('http://example.org/a'));
        self::assertSame('//cdn.example.org/a', $provider->exposeResolveLink('//cdn.example.org/a'));
        self::assertSame('/path/to/page', $provider->exposeResolveLink('/path/to/page'));
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
