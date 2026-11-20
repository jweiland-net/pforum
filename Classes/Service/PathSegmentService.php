<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Service;

use JWeiland\Pforum\Domain\RecordType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\Model\RecordStateFactory;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Generates the path segment (column "path_segment") of a topic or post
 * with the slug configuration of TCA. The path segment is unique in the
 * folder of the record. Records without title get "topic-[uid]" or
 * "post-[uid]".
 */
readonly class PathSegmentService
{
    public const COLUMN = 'path_segment';

    public function __construct(
        private ConnectionPool $connectionPool,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * @param bool $regenerate Generate a new path segment, even if the record has one
     */
    public function generate(RecordType $recordType, int $uid, bool $regenerate = false): void
    {
        $record = $this->findRecord($recordType->getTable(), $uid);
        if ($record === [] || (!$regenerate && (string)$record[self::COLUMN] !== '')) {
            return;
        }

        $pathSegment = $this->buildPathSegment($recordType, $record);
        if ($pathSegment !== (string)$record[self::COLUMN]) {
            $this->connectionPool->getConnectionForTable($recordType->getTable())
                ->update($recordType->getTable(), [self::COLUMN => $pathSegment], ['uid' => $uid]);
        }
    }

    /**
     * @param array<string, mixed> $record
     */
    public function buildPathSegment(RecordType $recordType, array $record): string
    {
        $table = $recordType->getTable();
        $slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            $table,
            self::COLUMN,
            $this->tcaSchemaFactory->get($table)->getField(self::COLUMN)->getConfiguration(),
        );

        $pid = (int)$record['pid'];
        $pathSegment = $slugHelper->generate($record, $pid);
        if ($pathSegment === '' || $pathSegment === '/') {
            $pathSegment = $slugHelper->sanitize($recordType->value . '-' . $record['uid']);
        }

        $state = RecordStateFactory::forName($table)->fromArray($record, $pid, (int)$record['uid']);

        return $slugHelper->buildSlugForUniqueInPid($pathSegment, $state);
    }

    /**
     * @return array<string, mixed>
     */
    private function findRecord(string $table, int $uid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());
        $record = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($record) ? $record : [];
    }
}
