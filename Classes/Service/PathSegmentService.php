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
use JWeiland\Pforum\Utility\TypeCaster;
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
        protected ConnectionPool $connectionPool,
        protected TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * @param bool $regenerate Generate a new path segment, even if the record has one
     */
    public function generate(RecordType $recordType, int $uid, bool $regenerate = false): void
    {
        $record = $this->findRecord($recordType->getTable(), $uid);
        if ($record === [] || (!$regenerate && TypeCaster::toString($record[self::COLUMN]) !== '')) {
            return;
        }

        $pathSegment = $this->buildPathSegment($recordType, $record);
        if ($pathSegment !== TypeCaster::toString($record[self::COLUMN])) {
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
        $configuration = $this->tcaSchemaFactory->get($table)->getField(self::COLUMN)->getConfiguration();
        $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, $table, self::COLUMN, $configuration);

        $pid = TypeCaster::toInt($record['pid']);
        // SlugHelper falls back to "default-[hash]" for records without title
        $pathSegment = $this->hasGeneratorFieldValue($record, $configuration)
            ? $slugHelper->generate($record, $pid)
            : '';
        if ($pathSegment === '' || $pathSegment === '/') {
            $pathSegment = $slugHelper->sanitize($recordType->value . '-' . TypeCaster::toString($record['uid']));
        }

        $state = RecordStateFactory::forName($table)->fromArray($record, $pid, TypeCaster::toInt($record['uid']));

        return $slugHelper->buildSlugForUniqueInPid($pathSegment, $state);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $configuration
     */
    protected function hasGeneratorFieldValue(array $record, array $configuration): bool
    {
        // Fields can be grouped in arrays, see "generatorOptions" of TCA type "slug"
        $fields = TypeCaster::toArray($configuration['generatorOptions'] ?? [])['fields'] ?? [];
        foreach (TypeCaster::toArray($fields) as $field) {
            foreach ((array)$field as $fieldName) {
                if (trim(TypeCaster::toString($record[TypeCaster::toString($fieldName)] ?? '')) !== '') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function findRecord(string $table, int $uid): array
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
