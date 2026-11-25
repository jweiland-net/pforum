<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Event;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Event\RecordPublishedEvent;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RecordPublishedEventTest extends UnitTestCase
{
    #[Test]
    public function gettersReturnConstructorArgumentsForTopic(): void
    {
        $topic = new Topic();
        $request = self::createStub(ServerRequestInterface::class);

        $subject = new RecordPublishedEvent($topic, ['auth' => '1'], $request);

        self::assertSame($topic, $subject->getRecord());
        self::assertSame(['auth' => '1'], $subject->getSettings());
        self::assertSame($request, $subject->getRequest());
    }

    #[Test]
    public function recordCanBePost(): void
    {
        $post = new Post();

        $subject = new RecordPublishedEvent($post, [], self::createStub(ServerRequestInterface::class));

        self::assertSame($post, $subject->getRecord());
        self::assertSame([], $subject->getSettings());
    }
}
