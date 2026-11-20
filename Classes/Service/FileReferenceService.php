<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Reads and writes the file references (sys_file_reference) of a topic or
 * post for the file column "images". The column of the record contains the
 * amount of file references.
 */
readonly class FileReferenceService
{
    private const REFERENCE_TABLE = 'sys_file_reference';

    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array<int, int> UIDs of the files (sys_file) with the UID of their reference as key
     */
    public function getFileUids(string $table, int $recordUid, string $column): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::REFERENCE_TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $result = $queryBuilder
            ->select('uid', 'uid_local')
            ->from(self::REFERENCE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($recordUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($column)),
            )
            ->orderBy('sorting_foreign')
            ->executeQuery();

        $fileUids = [];
        while ($reference = $result->fetchAssociative()) {
            $fileUids[(int)$reference['uid']] = (int)$reference['uid_local'];
        }

        return $fileUids;
    }

    /**
     * Keeps the references of the given files, so their metadata (e.g. the
     * alternative text set in backend) is kept. References of other files
     * are removed, new files get a new reference.
     *
     * @param int[] $fileUids
     */
    public function setFileUids(string $table, int $recordUid, int $pid, string $column, array $fileUids): void
    {
        $fileUids = array_values(array_unique($fileUids));
        $storedFileUids = $this->getFileUids($table, $recordUid, $column);
        $connection = $this->connectionPool->getConnectionForTable(self::REFERENCE_TABLE);

        foreach (array_diff($storedFileUids, $fileUids) as $referenceUid => $_) {
            $connection->update(self::REFERENCE_TABLE, ['deleted' => 1, 'tstamp' => time()], ['uid' => $referenceUid]);
        }

        foreach ($fileUids as $sorting => $fileUid) {
            $referenceUid = array_search($fileUid, $storedFileUids, true);
            if ($referenceUid !== false) {
                $connection->update(self::REFERENCE_TABLE, ['sorting_foreign' => $sorting + 1], ['uid' => $referenceUid]);
            } else {
                $this->insertReference($table, $recordUid, $pid, $column, $fileUid, $sorting + 1);
            }
        }

        $this->connectionPool->getConnectionForTable($table)
            ->update($table, [$column => count($fileUids)], ['uid' => $recordUid]);
    }

    private function insertReference(string $table, int $recordUid, int $pid, string $column, int $fileUid, int $sorting): void
    {
        $this->connectionPool->getConnectionForTable(self::REFERENCE_TABLE)->insert(self::REFERENCE_TABLE, [
            'pid' => $pid,
            'tstamp' => time(),
            'crdate' => time(),
            'uid_local' => $fileUid,
            'uid_foreign' => $recordUid,
            'tablenames' => $table,
            'fieldname' => $column,
            'sorting_foreign' => $sorting,
        ]);
    }
}
