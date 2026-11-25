<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Updates;

use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Service\PathSegmentService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * Fills the empty path segments of topics and posts
 */
#[UpgradeWizard('pforum_pathSegmentUpdate')]
final readonly class PathSegmentUpdate implements UpgradeWizardInterface
{
    public function __construct(
        private ConnectionPool $connectionPool,
        private PathSegmentService $pathSegmentService,
        private LoggerInterface $logger,
    ) {}

    public function getTitle(): string
    {
        return '[pforum] Fill path segments of topics and posts';
    }

    public function getDescription(): string
    {
        return 'Topics and posts have a path segment for speaking URLs now. This wizard generates the path '
            . 'segment of all topics and posts without one.';
    }

    public function updateNecessary(): bool
    {
        foreach (RecordType::cases() as $recordType) {
            if ((int)$this->getQueryBuilderForEmptyPathSegments($recordType)->count('uid')->executeQuery()->fetchOne() > 0) {
                return true;
            }
        }

        return false;
    }

    public function executeUpdate(): bool
    {
        foreach (RecordType::cases() as $recordType) {
            $result = $this->getQueryBuilderForEmptyPathSegments($recordType)->select('uid')->executeQuery();
            $count = 0;
            while ($uid = $result->fetchOne()) {
                $this->pathSegmentService->generate($recordType, (int)$uid);
                $count++;
            }
            $this->logger->info('Generated {count} path segments of table {table}', ['count' => $count, 'table' => $recordType->getTable()]);
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }

    private function getQueryBuilderForEmptyPathSegments(RecordType $recordType): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($recordType->getTable());
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        return $queryBuilder
            ->from($recordType->getTable())
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(PathSegmentService::COLUMN, $queryBuilder->createNamedParameter('')),
                    $queryBuilder->expr()->isNull(PathSegmentService::COLUMN),
                ),
            );
    }
}
