<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Updates;

use JWeiland\Pforum\Converter\RteContentConverter;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * The descriptions of topics and posts are stored as HTML like the backend
 * RTE stores them. Descriptions without any tag are plain text of former
 * versions of pforum. They are converted into HTML: a line break becomes
 * <br />, an empty line starts a new paragraph.
 */
#[UpgradeWizard('pforum_descriptionToHtmlUpdate')]
final readonly class DescriptionToHtmlUpdate implements UpgradeWizardInterface
{
    private const TABLES = ['tx_pforum_domain_model_topic', 'tx_pforum_domain_model_post'];

    public function __construct(
        private ConnectionPool $connectionPool,
        private RteContentConverter $rteContentConverter,
        private LoggerInterface $logger,
    ) {}

    public function getTitle(): string
    {
        return '[pforum] Convert plain text descriptions of topics and posts into HTML';
    }

    public function getDescription(): string
    {
        return 'The description of topics and posts is a rich text field now. This wizard converts descriptions '
            . 'without any HTML tag into HTML, so line breaks and paragraphs are kept in the backend RTE.';
    }

    public function updateNecessary(): bool
    {
        foreach (self::TABLES as $table) {
            if ((int)$this->getQueryBuilderForPlainTextDescriptions($table)->count('uid')->executeQuery()->fetchOne() > 0) {
                return true;
            }
        }

        return false;
    }

    public function executeUpdate(): bool
    {
        foreach (self::TABLES as $table) {
            $connection = $this->connectionPool->getConnectionForTable($table);
            $result = $this->getQueryBuilderForPlainTextDescriptions($table)
                ->select('uid', 'description')
                ->executeQuery();

            $count = 0;
            while ($record = $result->fetchAssociative()) {
                $connection->update(
                    $table,
                    ['description' => $this->rteContentConverter->convertPlainTextToHtml((string)$record['description'])],
                    ['uid' => (int)$record['uid']],
                );
                $count++;
            }
            $this->logger->info('Converted {count} plain text descriptions of table {table} into HTML', ['count' => $count, 'table' => $table]);
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

    private function getQueryBuilderForPlainTextDescriptions(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->from($table)
            ->where(
                $queryBuilder->expr()->neq('description', $queryBuilder->createNamedParameter('')),
                $queryBuilder->expr()->notLike('description', $queryBuilder->createNamedParameter('%<%')),
            );
    }
}
