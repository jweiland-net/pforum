<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Updates;

use JWeiland\Pforum\Updates\PathSegmentUpdate;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class PathSegmentUpdateTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
    }

    #[Test]
    public function executeUpdateFillsUniquePathSegmentsOfTopicsAndPosts(): void
    {
        $subject = $this->get(PathSegmentUpdate::class);
        self::assertTrue($subject->updateNecessary());

        $subject->executeUpdate();

        self::assertSame('welcome', $this->getPathSegment('topic', 1));
        self::assertSame('hidden-topic-of-jane', $this->getPathSegment('topic', 2));
        self::assertSame('reply', $this->getPathSegment('post', 1));
        self::assertSame('hidden-reply', $this->getPathSegment('post', 2));
        // Path segments of records of former versions are NULL after the database compare
        self::assertSame('topic-of-mallory', $this->getPathSegment('topic', 4));
        // Records without title get the record type and their UID
        self::assertSame('post-4', $this->getPathSegment('post', 4));
        self::assertFalse($subject->updateNecessary());
    }

    private function getPathSegment(string $recordType, int $uid): string
    {
        $table = 'tx_pforum_domain_model_' . $recordType;
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        return (string)$queryBuilder
            ->select('path_segment')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()
            ->fetchOne();
    }
}
