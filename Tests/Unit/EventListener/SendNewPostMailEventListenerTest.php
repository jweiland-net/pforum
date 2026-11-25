<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\EventListener;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Event\RecordPublishedEvent;
use JWeiland\Pforum\EventListener\SendNewPostMailEventListener;
use JWeiland\Pforum\Mail\NewPostMail;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class SendNewPostMailEventListenerTest extends UnitTestCase
{
    private NewPostMail&MockObject $newPostMail;

    private SendNewPostMailEventListener $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->newPostMail = $this->createMock(NewPostMail::class);
        $this->subject = new SendNewPostMailEventListener($this->newPostMail);
    }

    #[Test]
    public function visiblePostSendsMailWithSettingsAndRequest(): void
    {
        $post = new Post();
        $post->setHidden(false);
        $settings = ['auth' => '1'];
        $request = $this->createMock(ServerRequestInterface::class);
        $this->newPostMail->expects($this->once())->method('send')->with($post, $settings, $request);

        ($this->subject)(new RecordPublishedEvent($post, $settings, $request));
    }

    #[Test]
    public function hiddenPostSendsNoMail(): void
    {
        $post = new Post();
        $post->setHidden(true);
        $this->newPostMail->expects($this->never())->method('send');

        ($this->subject)(new RecordPublishedEvent($post, [], $this->createMock(ServerRequestInterface::class)));
    }

    #[Test]
    public function topicSendsNoMail(): void
    {
        $topic = new Topic();
        $topic->setHidden(false);
        $this->newPostMail->expects($this->never())->method('send');

        ($this->subject)(new RecordPublishedEvent($topic, [], $this->createMock(ServerRequestInterface::class)));
    }
}
