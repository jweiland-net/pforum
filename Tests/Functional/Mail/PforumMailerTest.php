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
use JWeiland\Pforum\Mail\PforumMailer;
use JWeiland\Pforum\Tests\Functional\Fixtures\Mail\CapturingTransport;
use JWeiland\Pforum\Tests\Functional\Fixtures\Mail\FailingTransport;
use JWeiland\Pforum\Tests\Functional\Traits\ExtbaseRequestTrait;
use JWeiland\Pforum\Tests\Functional\Traits\MailTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class PforumMailerTest extends FunctionalTestCase
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

    /**
     * @return array<string, array{string}>
     */
    public static function invalidEmailAddressProvider(): array
    {
        return [
            'empty' => [''],
            'without domain' => ['jane'],
            'without at' => ['jane.example.com'],
            'with spaces' => ['jane doe@example.com'],
        ];
    }

    #[Test]
    public function sendSendsMailWithSenderOfExtensionConfiguration(): void
    {
        $subject = $this->createPforumMailer(new CapturingTransport());

        self::assertTrue($this->send($subject, $this->createUser('jane@example.com', 'Jane')));

        $mails = CapturingTransport::getMails();
        self::assertCount(1, $mails);
        self::assertSame(['jane@example.com'], $mails[0]->recipients);
        self::assertSame(['Jane'], $mails[0]->recipientNames);
        self::assertSame('forum@example.com', $mails[0]->senderAddress);
        self::assertSame('Forum', $mails[0]->senderName);
        self::assertSame('Subject of the mail', $mails[0]->subject);
        self::assertSame('PforumNewPost', $mails[0]->template);
    }

    #[Test]
    public function sendFallsBackToSenderOfInstallTool(): void
    {
        $subject = $this->createPforumMailer(new CapturingTransport(), new ExtConf());

        self::assertTrue($this->send($subject, $this->createUser('jane@example.com', 'Jane')));

        $mails = CapturingTransport::getMails();
        self::assertSame('install-tool@example.com', $mails[0]->senderAddress);
        self::assertSame('Install Tool', $mails[0]->senderName);
    }

    #[Test]
    public function sendRendersVariablesInTextAndHtmlTemplate(): void
    {
        $subject = $this->createPforumMailer(new CapturingTransport());

        $this->send($subject, $this->createUser('jane@example.com', 'Jane'));

        $mails = CapturingTransport::getMails();
        self::assertStringContainsString("author=Jane\ntopic=Welcome\npost=Reply", $mails[0]->textBody);
        self::assertStringContainsString("author=Jane\ntopic=Welcome\npost=Reply", $mails[0]->htmlBody);
    }

    #[Test]
    #[DataProvider('invalidEmailAddressProvider')]
    public function sendReturnsFalseForInvalidRecipientEmail(string $email): void
    {
        $subject = $this->createPforumMailer(new CapturingTransport());

        self::assertFalse($this->send($subject, $this->createUser($email, 'Jane')));
        self::assertSame([], CapturingTransport::getMails());
    }

    #[Test]
    public function sendReturnsFalseWithoutSenderAddress(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';
        $subject = $this->createPforumMailer(new CapturingTransport(), new ExtConf('', 'Forum'));

        self::assertFalse($this->send($subject, $this->createUser('jane@example.com', 'Jane')));
        self::assertSame([], CapturingTransport::getMails());
    }

    #[Test]
    public function sendReturnsFalseWithoutSenderName(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = '';
        $subject = $this->createPforumMailer(new CapturingTransport(), new ExtConf('forum@example.com'));

        self::assertFalse($this->send($subject, $this->createUser('jane@example.com', 'Jane')));
        self::assertSame([], CapturingTransport::getMails());
    }

    #[Test]
    public function sendReturnsFalseIfTransportFails(): void
    {
        $subject = $this->createPforumMailer(new FailingTransport());

        self::assertFalse($this->send($subject, $this->createUser('jane@example.com', 'Jane')));
    }

    private function send(PforumMailer $subject, AnonymousUser $recipient): bool
    {
        $post = $this->getPost(1);

        return $subject->send(
            $recipient,
            'Subject of the mail',
            'PforumNewPost',
            ['author' => $recipient, 'topic' => $post->getTopic(), 'post' => $post],
            $this->getMailSettings(),
            $this->createExtbaseRequest(),
        );
    }

    private function createUser(string $email, string $name): AnonymousUser
    {
        $user = new AnonymousUser();
        $user->setEmail($email);
        $user->setName($name);

        return $user;
    }
}
