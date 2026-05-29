<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Hook;

use Maispace\MaiBase\Hook\AbstractRecordCacheInvalidationHook;

/**
 * Flushes calendar/list page cache tags when an event record is saved or deleted.
 */
final class EventCacheInvalidationHook extends AbstractRecordCacheInvalidationHook
{
    protected function getWatchedTable(): string
    {
        return 'tx_maievents_event';
    }
}
