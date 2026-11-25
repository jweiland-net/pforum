<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Service;

use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Service\PathSegmentService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class PathSegmentServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    private PathSegmentService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/PathSegments.csv');
        $this->subject = $this->get(PathSegmentService::class);
    }

    #[Test]
    public function generateBuildsPathSegmentOfTitle(): void
    {
        $this->subject->generate(RecordType::Post, 11);

        self::assertSame('reply', $this->getPathSegment('post', 11));
    }

    #[Test]
    public function generateSanitizesTitle(): void
    {
        $this->subject->generate(RecordType::Topic, 15);

        self::assertSame('cafe-spaces', $this->getPathSegment('topic', 15));
    }

    #[Test]
    public function generateUsesRecordTypeAndUidForRecordsWithoutTitle(): void
    {
        $this->subject->generate(RecordType::Topic, 14);
        $this->subject->generate(RecordType::Post, 10);

        self::assertSame('topic-14', $this->getPathSegment('topic', 14));
        self::assertSame('post-10', $this->getPathSegment('post', 10));
    }

    #[Test]
    public function generateMakesPathSegmentUniqueInSamePage(): void
    {
        $this->subject->generate(RecordType::Topic, 11);

        self::assertSame('same-title-1', $this->getPathSegment('topic', 11));
        self::assertSame('same-title', $this->getPathSegment('topic', 10));
    }

    #[Test]
    public function generateKeepsPathSegmentOfOtherPageUntouched(): void
    {
        $this->subject->generate(RecordType::Topic, 12);

        self::assertSame('same-title', $this->getPathSegment('topic', 12));
    }

    #[Test]
    public function generateKeepsExistingPathSegment(): void
    {
        $this->subject->generate(RecordType::Topic, 13);

        self::assertSame('custom-segment', $this->getPathSegment('topic', 13));
    }

    #[Test]
    public function generateWithRegenerateReplacesExistingPathSegment(): void
    {
        $this->subject->generate(RecordType::Topic, 13, true);

        self::assertSame('kept-title', $this->getPathSegment('topic', 13));
    }

    #[Test]
    public function generateWithRegenerateKeepsUniquePathSegmentOfSameRecord(): void
    {
        $this->subject->generate(RecordType::Topic, 10, true);

        self::assertSame('same-title', $this->getPathSegment('topic', 10));
    }

    #[Test]
    public function generateIgnoresUnknownRecord(): void
    {
        $this->subject->generate(RecordType::Topic, 9999);

        self::assertSame('', $this->getPathSegment('topic', 9999));
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
