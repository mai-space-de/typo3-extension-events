<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Hook;

use Maispace\MaiEvents\Hook\EventCacheInvalidationHook;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Extbase\Service\CacheService;

final class EventCacheInvalidationHookTest extends TestCase
{
    private CacheService&MockObject $cacheService;

    private DataHandler&MockObject $dataHandler;

    protected function setUp(): void
    {
        $this->cacheService = $this->createMock(CacheService::class);
        $this->dataHandler = $this->createMock(DataHandler::class);
    }

    #[Test]
    public function eventRecordUpdateFlushesExtbaseCacheTagsTest(): void
    {
        $this->cacheService->expects(self::once())->method('clearCacheForRecord')->with('tx_maievents_event', 8);
        $this->cacheService->expects(self::once())->method('clearCachesOfRegisteredPageIds');

        (new EventCacheInvalidationHook($this->cacheService))->processDatamap_afterDatabaseOperations(
            'update',
            'tx_maievents_event',
            8,
            ['title' => 'Updated event'],
            $this->dataHandler,
        );
    }
}
