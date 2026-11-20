<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Updates;

use JWeiland\Pforum\Updates\DescriptionToHtmlUpdate;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class DescriptionToHtmlUpdateTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'form',
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
    public function updateIsNecessaryForPlainTextDescriptions(): void
    {
        self::assertTrue($this->get(DescriptionToHtmlUpdate::class)->updateNecessary());
    }

    #[Test]
    public function executeUpdateConvertsPlainTextDescriptionsOnly(): void
    {
        $subject = $this->get(DescriptionToHtmlUpdate::class);

        self::assertTrue($subject->executeUpdate());

        self::assertSame('<p>First line<br />Second line</p>', $this->getDescriptionOfTopic(2));
        self::assertSame('<p>Hello forum</p>', $this->getDescriptionOfTopic(1));
        self::assertFalse($subject->updateNecessary());
    }

    private function getDescriptionOfTopic(int $uid): string
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_pforum_domain_model_topic');
        $queryBuilder->getRestrictions()->removeAll();

        return (string)$queryBuilder
            ->select('description')
            ->from('tx_pforum_domain_model_topic')
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()
            ->fetchOne();
    }
}
