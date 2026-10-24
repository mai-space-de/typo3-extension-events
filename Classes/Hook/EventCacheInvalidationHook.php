<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Hook;

use Maispace\MaiBase\Backend\CustomPageModule\DoktypeIds;
use Maispace\MaiBase\Hook\AbstractRecordCacheInvalidationHook;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Flushes calendar/list page cache tags when an Event page (or legacy record) changes.
 */
final class EventCacheInvalidationHook extends AbstractRecordCacheInvalidationHook
{
    protected function getWatchedTable(): string
    {
        return 'pages';
    }

    public function processDatamap_afterDatabaseOperations(
        string $status,
        string $table,
        int|string $id,
        array $fieldArray,
        DataHandler $dataHandler,
    ): void {
        if ($table === 'tx_maievents_event') {
            $uid = $status === 'new'
                ? (int) ($dataHandler->substNEWwithIDs[$id] ?? 0)
                : (int) $id;
            if ($uid > 0) {
                $this->flushCachesForRecord($table, $uid);
            }

            return;
        }

        if ($table !== 'pages' || !$this->isEventPageChange($fieldArray, $dataHandler)) {
            return;
        }

        parent::processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, $dataHandler);
    }

    public function processCmdmap_deleteAction(
        string $table,
        int $id,
        array $recordToDelete,
        bool &$recordWasDeleted,
        DataHandler $dataHandler,
    ): void {
        if ($table === 'tx_maievents_event') {
            if ($id > 0) {
                $this->flushCachesForRecord($table, $id);
            }

            return;
        }

        if ($table !== 'pages') {
            return;
        }

        $doktype = (int) ($recordToDelete['doktype'] ?? 0);
        if ($doktype !== DoktypeIds::EVENT) {
            return;
        }

        parent::processCmdmap_deleteAction($table, $id, $recordToDelete, $recordWasDeleted, $dataHandler);
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function isEventPageChange(array $fieldArray, DataHandler $dataHandler): bool
    {
        if (isset($fieldArray['doktype']) && (int) $fieldArray['doktype'] === DoktypeIds::EVENT) {
            return true;
        }

        foreach (array_keys($fieldArray) as $field) {
            if (str_starts_with((string) $field, 'tx_maievents_')) {
                return true;
            }
        }

        $currentDoktype = (int) ($dataHandler->checkValue_currentRecord['doktype'] ?? 0);

        return $currentDoktype === DoktypeIds::EVENT;
    }
}
