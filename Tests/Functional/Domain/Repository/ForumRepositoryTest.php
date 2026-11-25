<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Domain\Repository;

use JWeiland\Pforum\Domain\Model\Forum;
use JWeiland\Pforum\Domain\Repository\ForumRepository;
use JWeiland\Pforum\Tests\Functional\Traits\ExtbaseRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class ForumRepositoryTest extends FunctionalTestCase
{
    use ExtbaseRequestTrait;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    private ForumRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/ForumSorting.csv');
        $this->createExtbaseRequest();
        $this->subject = $this->get(ForumRepository::class);
    }

    #[Test]
    public function findAllSortsForumsBySorting(): void
    {
        self::assertSame(
            [12, 13, 11],
            array_map(
                static fn(Forum $forum): ?int => $forum->getUid(),
                $this->subject->findAll()->toArray(),
            ),
        );
    }

    #[Test]
    public function findByIdentifierReturnsForum(): void
    {
        $forum = $this->subject->findByIdentifier(13);

        self::assertInstanceOf(Forum::class, $forum);
        self::assertSame('Second', $forum->getTitle());
    }
}
