<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Mail;

use JWeiland\Pforum\Configuration\ExtConf;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Mail\NewPostMail;
use JWeiland\Pforum\Tests\Functional\Fixtures\Mail\CapturingTransport;
use JWeiland\Pforum\Tests\Functional\Fixtures\Mail\FailingTransport;
use JWeiland\Pforum\Tests\Functional\Traits\ExtbaseRequestTrait;
use JWeiland\Pforum\Tests\Functional\Traits\MailTestTrait;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\Transport\TransportInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class NewPostMailTest extends FunctionalTestCase
{
    use ExtbaseRequestTrait;
    use MailTestTrait;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->setUpMailTest();
        $this->createExtbaseRequest();
    }

    #[Test]
    public function sendInformsFrontendUserAuthorOfTopic(): void
    {
        $subject = $this->createSubject(new CapturingTransport());

        self::assertTrue($subject->send($this->getPost(1), $this->getMailSettings(), $this->createExtbaseRequest()));

        $mails = CapturingTransport::getMails();
        self::assertCount(1, $mails);
        self::assertSame(['owner@example.com'], $mails[0]->recipients);
        self::assertSame(['Owner'], $mails[0]->recipientNames);
        self::assertSame('forum@example.com', $mails[0]->senderAddress);
        self::assertSame('Forum', $mails[0]->senderName);
        self::assertSame('New post at your topic: Welcome', $mails[0]->subject);
        self::assertSame('PforumNewPost', $mails[0]->template);
        self::assertStringContainsString("author=Owner\ntopic=Welcome\npost=Reply", $mails[0]->textBody);
    }

    #[Test]
    public function sendInformsAnonymousAuthorOfTopic(): void
    {
        $subject = $this->createSubject(new CapturingTransport());

        self::assertTrue($subject->send($this->getPost(3), $this->getMailSettings(), $this->createExtbaseRequest()));

        $mails = CapturingTransport::getMails();
        self::assertSame(['mallory@example.com'], $mails[0]->recipients);
        self::assertSame('New post at your topic: Topic of Mallory', $mails[0]->subject);
    }

    #[Test]
    public function sendReturnsFalseIfTopicHasNoUser(): void
    {
        $post = new Post();
        $post->setTopic(new Topic());
        $subject = $this->createSubject(new CapturingTransport());

        self::assertFalse($subject->send($post, $this->getMailSettings(), $this->createExtbaseRequest()));
        self::assertSame([], CapturingTransport::getMails());
    }

    #[Test]
    public function sendReturnsFalseWithoutSender(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';
        $subject = $this->createSubject(new CapturingTransport(), new ExtConf());

        self::assertFalse($subject->send($this->getPost(1), $this->getMailSettings(), $this->createExtbaseRequest()));
        self::assertSame([], CapturingTransport::getMails());
    }

    #[Test]
    public function sendReturnsFalseIfTransportFails(): void
    {
        $subject = $this->createSubject(new FailingTransport());

        self::assertFalse($subject->send($this->getPost(1), $this->getMailSettings(), $this->createExtbaseRequest()));
    }

    private function createSubject(TransportInterface $transport, ?ExtConf $extConf = null): NewPostMail
    {
        return new NewPostMail($this->createPforumMailer($transport, $extConf));
    }
}
