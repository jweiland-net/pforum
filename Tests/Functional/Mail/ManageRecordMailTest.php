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
use JWeiland\Pforum\Domain\Model\AnonymousUser;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Mail\ManageRecordMail;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
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
class ManageRecordMailTest extends FunctionalTestCase
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
    public function sendMailsLinksOfHiddenTopicToItsAnonymousUser(): void
    {
        $subject = $this->createSubject(new CapturingTransport());

        self::assertTrue($subject->send($this->getTopic(2), $this->getMailSettings('topic', true), $this->createExtbaseRequest()));

        $mails = CapturingTransport::getMails();
        self::assertCount(1, $mails);
        self::assertSame(['jane@example.com'], $mails[0]->recipients);
        self::assertSame(['Jane'], $mails[0]->recipientNames);
        self::assertSame('forum@example.com', $mails[0]->senderAddress);
        self::assertSame('Forum', $mails[0]->senderName);
        self::assertSame('Manage your newly created topic', $mails[0]->subject);
        self::assertSame('PforumManageTopic', $mails[0]->template);
        self::assertStringContainsString('topic=Hidden topic of Jane', $mails[0]->textBody);
    }

    #[Test]
    public function sendAddsAccessTokenAndActivationTokenIfAuthorActivatesTopic(): void
    {
        $subject = $this->createSubject(new CapturingTransport());

        $subject->send($this->getTopic(2), $this->getMailSettings('topic', true), $this->createExtbaseRequest());

        $mail = CapturingTransport::getMails()[0];
        $tokenService = $this->get(AnonymousAccessTokenService::class);
        self::assertStringContainsString("\ntoken=" . $tokenService->generateToken('Topic', 2) . "\n", $mail->textBody);
        self::assertStringContainsString("\nactivationToken=" . $tokenService->generateToken('TopicActivation', 2) . "\n", $mail->textBody);
        self::assertStringContainsString("\nactivationToken=" . $tokenService->generateToken('TopicActivation', 2) . "\n", $mail->htmlBody);
    }

    #[Test]
    public function sendAddsNoActivationTokenIfAdminActivatesTopic(): void
    {
        $subject = $this->createSubject(new CapturingTransport());

        $subject->send($this->getTopic(2), $this->getMailSettings('topic', true, true), $this->createExtbaseRequest());

        $mail = CapturingTransport::getMails()[0];
        self::assertStringContainsString("\ntoken=" . $this->get(AnonymousAccessTokenService::class)->generateToken('Topic', 2) . "\n", $mail->textBody);
        self::assertStringContainsString("\nactivationToken=\n", $mail->textBody);
    }

    #[Test]
    public function sendAddsNoActivationTokenIfTopicIsNotHiddenAtCreation(): void
    {
        $subject = $this->createSubject(new CapturingTransport());

        $subject->send($this->getTopic(2), $this->getMailSettings('topic', false), $this->createExtbaseRequest());

        self::assertStringContainsString("\nactivationToken=\n", CapturingTransport::getMails()[0]->textBody);
    }

    #[Test]
    public function sendAddsNoActivationTokenForVisibleTopic(): void
    {
        $subject = $this->createSubject(new CapturingTransport());

        // The topic of a frontend user is visible already
        $subject->send($this->getTopic(1), $this->getMailSettings('topic', true), $this->createExtbaseRequest());

        $mails = CapturingTransport::getMails();
        self::assertSame(['owner@example.com'], $mails[0]->recipients);
        self::assertStringContainsString("\nactivationToken=\n", $mails[0]->textBody);
    }

    #[Test]
    public function sendMailsLinksOfHiddenPostToItsAnonymousUser(): void
    {
        $subject = $this->createSubject(new CapturingTransport());

        self::assertTrue($subject->send($this->getPost(2), $this->getMailSettings('post', true), $this->createExtbaseRequest()));

        $mail = CapturingTransport::getMails()[0];
        $tokenService = $this->get(AnonymousAccessTokenService::class);
        self::assertSame(['jane@example.com'], $mail->recipients);
        self::assertSame('Manage your newly created post', $mail->subject);
        self::assertSame('PforumManagePost', $mail->template);
        self::assertStringContainsString('post=Hidden reply', $mail->textBody);
        self::assertStringContainsString("\ntoken=" . $tokenService->generateToken('Post', 2) . "\n", $mail->textBody);
        self::assertStringContainsString("\nactivationToken=" . $tokenService->generateToken('PostActivation', 2) . "\n", $mail->textBody);
    }

    #[Test]
    public function sendReturnsFalseForRecordWithoutUser(): void
    {
        $subject = $this->createSubject(new CapturingTransport());

        self::assertFalse($subject->send(new Topic(), $this->getMailSettings(), $this->createExtbaseRequest()));
        self::assertFalse($subject->send(new Post(), $this->getMailSettings('post'), $this->createExtbaseRequest()));
        self::assertSame([], CapturingTransport::getMails());
    }

    #[Test]
    public function sendReturnsFalseForInvalidEmailOfUser(): void
    {
        $user = new AnonymousUser();
        $user->setEmail('not-an-email');
        $user->setName('Invalid');
        $topic = $this->getTopic(2);
        $topic->setAnonymousUser($user);
        $subject = $this->createSubject(new CapturingTransport());

        self::assertFalse($subject->send($topic, $this->getMailSettings(), $this->createExtbaseRequest()));
        self::assertSame([], CapturingTransport::getMails());
    }

    #[Test]
    public function sendReturnsFalseWithoutSender(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';
        $subject = $this->createSubject(new CapturingTransport(), new ExtConf());

        self::assertFalse($subject->send($this->getTopic(2), $this->getMailSettings(), $this->createExtbaseRequest()));
        self::assertSame([], CapturingTransport::getMails());
    }

    #[Test]
    public function sendReturnsFalseIfTransportFails(): void
    {
        $subject = $this->createSubject(new FailingTransport());

        self::assertFalse($subject->send($this->getTopic(2), $this->getMailSettings(), $this->createExtbaseRequest()));
    }

    private function createSubject(TransportInterface $transport, ?ExtConf $extConf = null): ManageRecordMail
    {
        return new ManageRecordMail(
            $this->get(AnonymousAccessTokenService::class),
            $this->createPforumMailer($transport, $extConf),
        );
    }
}
