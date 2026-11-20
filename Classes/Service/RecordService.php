<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Service;

use JWeiland\Pforum\Form\RecordFormData;
use JWeiland\Pforum\Form\RecordType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Reads and writes topics, posts and pforum users (anonymous users) for the
 * forms and the frontend actions of pforum
 */
readonly class RecordService
{
    public const USER_TABLE = 'tx_pforum_domain_model_anonymoususer';

    public function __construct(
        private ConnectionPool $connectionPool,
        private FileReferenceService $fileReferenceService,
    ) {}

    /**
     * Hidden records are found, too. Deleted ones not.
     *
     * @return array<string, mixed>
     */
    public function findRecord(RecordType $recordType, int $uid): array
    {
        return $this->findRow($recordType->getTable(), $uid);
    }

    /**
     * Returns the forum of a topic or the topic of a post
     *
     * @return array<string, mixed>
     */
    public function findParentRecord(RecordType $recordType, int $parentUid): array
    {
        return $this->findRow($recordType->getParentTable(), $parentUid);
    }

    /**
     * @return array<string, mixed>
     */
    public function findUser(int $uid): array
    {
        return $this->findRow(self::USER_TABLE, $uid);
    }

    /**
     * @return array<string, mixed>
     */
    public function findFrontendUser(int $uid): array
    {
        return $this->findRow('fe_users', $uid);
    }

    /**
     * Creates a topic in the folder of its forum or a post in the folder of its topic
     *
     * @param int $frontendUserUid Author of the record. 0 for pforum users (anonymous users).
     * @return int UID of the new record
     */
    public function create(RecordType $recordType, RecordFormData $formData, int $parentUid, bool $hidden, int $frontendUserUid): int
    {
        $pid = (int)($this->findParentRecord($recordType, $parentUid)['pid'] ?? 0);
        $columns = array_replace($formData->columns, [
            'pid' => $pid,
            'crdate' => time(),
            'tstamp' => time(),
            'hidden' => (int)$hidden,
            $recordType->getParentColumn() => $parentUid,
            'frontend_user' => $frontendUserUid,
            'anonymous_user' => $frontendUserUid === 0 ? $this->insertUser($formData->userColumns, $pid) : 0,
        ]);

        $connection = $this->connectionPool->getConnectionForTable($recordType->getTable());
        $connection->insert($recordType->getTable(), $columns);
        $uid = (int)$connection->lastInsertId();

        $this->saveFiles($recordType, $formData, $uid, $pid);
        $this->updateParentCounter($recordType, $parentUid);

        return $uid;
    }

    /**
     * Updates the record and its pforum user. Author and visibility are kept.
     */
    public function update(RecordType $recordType, RecordFormData $formData, int $uid): void
    {
        $record = $this->findRecord($recordType, $uid);
        if ($record === []) {
            return;
        }

        $columns = $formData->columns;
        $columns['tstamp'] = time();
        if ((int)$record['anonymous_user'] > 0 && $formData->userColumns !== []) {
            $this->connectionPool->getConnectionForTable(self::USER_TABLE)
                ->update(self::USER_TABLE, array_replace($formData->userColumns, ['tstamp' => time()]), ['uid' => (int)$record['anonymous_user']]);
        }

        $this->connectionPool->getConnectionForTable($recordType->getTable())
            ->update($recordType->getTable(), $columns, ['uid' => $uid]);
        $this->saveFiles($recordType, $formData, $uid, (int)$record['pid']);
    }

    public function activate(RecordType $recordType, int $uid): void
    {
        $this->connectionPool->getConnectionForTable($recordType->getTable())
            ->update($recordType->getTable(), ['hidden' => 0, 'tstamp' => time()], ['uid' => $uid]);
    }

    /**
     * Marks the record as deleted. The posts of a topic are deleted, too.
     */
    public function delete(RecordType $recordType, int $uid): void
    {
        $record = $this->findRecord($recordType, $uid);
        if ($record === []) {
            return;
        }

        $this->connectionPool->getConnectionForTable($recordType->getTable())
            ->update($recordType->getTable(), ['deleted' => 1, 'tstamp' => time()], ['uid' => $uid]);
        if ($recordType === RecordType::Topic) {
            $this->connectionPool->getConnectionForTable(RecordType::Post->getTable())
                ->update(RecordType::Post->getTable(), ['deleted' => 1, 'tstamp' => time()], ['topic' => $uid]);
        }

        $this->updateParentCounter($recordType, (int)$record[$recordType->getParentColumn()]);
    }

    /**
     * @param array<string, string> $userColumns
     */
    private function insertUser(array $userColumns, int $pid): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::USER_TABLE);
        $connection->insert(self::USER_TABLE, array_replace($userColumns, [
            'pid' => $pid,
            'crdate' => time(),
            'tstamp' => time(),
        ]));

        return (int)$connection->lastInsertId();
    }

    private function saveFiles(RecordType $recordType, RecordFormData $formData, int $uid, int $pid): void
    {
        foreach ($formData->files as $column => $fileUids) {
            $this->fileReferenceService->setFileUids($recordType->getTable(), $uid, $pid, $column, $fileUids);
        }
    }

    /**
     * The inline column "topics" of a forum and "posts" of a topic contain
     * the amount of their child records
     */
    private function updateParentCounter(RecordType $recordType, int $parentUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($recordType->getTable());
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $amount = (int)$queryBuilder
            ->count('uid')
            ->from($recordType->getTable())
            ->where($queryBuilder->expr()->eq(
                $recordType->getParentColumn(),
                $queryBuilder->createNamedParameter($parentUid, Connection::PARAM_INT),
            ))
            ->executeQuery()
            ->fetchOne();

        $this->connectionPool->getConnectionForTable($recordType->getParentTable())
            ->update($recordType->getParentTable(), [$recordType->getParentCounterColumn() => $amount], ['uid' => $parentUid]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findRow(string $table, int $uid): array
    {
        if ($uid <= 0) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $row = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : [];
    }
}
