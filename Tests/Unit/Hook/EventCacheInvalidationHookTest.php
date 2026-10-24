<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Tests\Unit\Hook;

use Maispace\MaiBase\Backend\CustomPageModule\DoktypeIds;
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
    public function eventPageUpdateFlushesExtbaseCacheTagsTest(): void
    {
        $this->cacheService->expects(self::once())->method('clearCacheForRecord')->with('pages', 8);
        $this->cacheService->expects(self::once())->method('clearCachesOfRegisteredPageIds');

        (new EventCacheInvalidationHook($this->cacheService))->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            8,
            ['doktype' => DoktypeIds::EVENT, 'tx_maievents_location' => 'Hall'],
            $this->dataHandler,
        );
    }

    #[Test]
    public function legacyEventRecordUpdateStillFlushesCache(): void
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

    #[Test]
    public function nonEventPageUpdateDoesNotFlush(): void
    {
        $this->cacheService->expects(self::never())->method('clearCacheForRecord');
        $this->cacheService->expects(self::never())->method('clearCachesOfRegisteredPageIds');

        (new EventCacheInvalidationHook($this->cacheService))->processDatamap_afterDatabaseOperations(
            'update',
            'pages',
            1,
            ['title' => 'Home'],
            $this->dataHandler,
        );
    }
}
