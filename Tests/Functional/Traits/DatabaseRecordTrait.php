<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Traits;

/**
 * Reads topics and posts, which are hidden or deleted, and the flash
 * messages of the frontend session
 */
trait DatabaseRecordTrait
{
    /**
     * The flash messages of the redirect are stored in the session
     */
    private function getFlashMessages(): string
    {
        $sessionData = $this->getConnectionPool()
            ->getConnectionForTable('fe_sessions')
            ->select(['ses_data'], 'fe_sessions')
            ->fetchFirstColumn();

        return implode('', array_map(strval(...), $sessionData));
    }

    /**
     * @return array<string, mixed>
     */
    private function getRecord(string $recordType, int $uid): array
    {
        return $this->findRecord($recordType, 'uid', (string)$uid);
    }

    /**
     * @return array<string, mixed>
     */
    private function getRecordByTitle(string $recordType, string $title): array
    {
        return $this->findRecord($recordType, 'title', $title);
    }

    /**
     * @return array<string, mixed>
     */
    private function findRecord(string $recordType, string $field, string $value): array
    {
        $table = 'tx_pforum_domain_model_' . $recordType;
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $record = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($record) ? $record : [];
    }
}
