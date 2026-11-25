<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Controller;

use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Tests\Functional\Fixtures\Mail\CapturedMail;
use JWeiland\Pforum\Tests\Functional\Fixtures\Mail\CapturingTransport;
use JWeiland\Pforum\Tests\Functional\Traits\DatabaseRecordTrait;
use JWeiland\Pforum\Tests\Functional\Traits\FrontendRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Publishing a topic or post dispatches RecordPublishedEvent. The author of
 * the topic gets the mail PforumNewPost for new visible posts only.
 */
class RecordPublishedMailTest extends FunctionalTestCase
{
    use DatabaseRecordTrait;
    use FrontendRequestTrait;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => CapturingTransport::class,
        ],
        'EXTENSIONS' => [
            'pforum' => [
                'emailFromAddress' => 'forum@example.com',
                'emailFromName' => 'Forum',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        CapturingTransport::reset();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->setUpForumSite();
    }

    #[Test]
    public function creatingVisiblePostSendsNewPostMailToAuthorOfTopic(): void
    {
        $this->setUpVisibleRecords();

        $this->submitForm('Post', 'create', [], [
            'topic' => '1',
            'post' => [
                'title' => 'New answer',
                'description' => 'My answer',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        self::assertSame(0, (int)$this->getRecordByTitle('post', 'New answer')['hidden']);
        $newPostMails = $this->getMailsOfTemplate('PforumNewPost');
        self::assertCount(1, $newPostMails);
        self::assertSame(['owner@example.com'], $newPostMails[0]->recipients);
        self::assertSame(['Owner'], $newPostMails[0]->recipientNames);
        self::assertSame('forum@example.com', $newPostMails[0]->senderAddress);
        self::assertSame('New post at your topic: Welcome', $newPostMails[0]->subject);
        self::assertStringContainsString('Welcome', $newPostMails[0]->textBody);
        self::assertStringContainsString('New answer', $newPostMails[0]->textBody);
        self::assertStringContainsString('My answer', $newPostMails[0]->textBody);
    }

    #[Test]
    public function creatingVisiblePostSendsManageMailToAuthorOfPostToo(): void
    {
        $this->setUpVisibleRecords();

        $this->submitForm('Post', 'create', [], [
            'topic' => '1',
            'post' => [
                'title' => 'New answer',
                'description' => 'My answer',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        $manageMails = $this->getMailsOfTemplate('PforumManagePost');
        self::assertCount(1, $manageMails);
        self::assertSame(['john@example.com'], $manageMails[0]->recipients);
        self::assertSame('Manage your newly created post', $manageMails[0]->subject);
        self::assertCount(2, CapturingTransport::getMails());
    }

    #[Test]
    public function creatingHiddenPostSendsNoNewPostMail(): void
    {
        $this->submitForm('Post', 'create', [], [
            'topic' => '1',
            'post' => [
                'title' => 'Hidden answer',
                'description' => 'Waiting for activation',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        self::assertSame(1, (int)$this->getRecordByTitle('post', 'Hidden answer')['hidden']);
        self::assertSame([], $this->getMailsOfTemplate('PforumNewPost'));
        self::assertCount(1, $this->getMailsOfTemplate('PforumManagePost'));
    }

    #[Test]
    public function creatingVisibleTopicSendsNoNewPostMail(): void
    {
        $this->setUpVisibleRecords();

        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => [
                'title' => 'New topic',
                'description' => 'My question',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        self::assertSame(0, (int)$this->getRecordByTitle('topic', 'New topic')['hidden']);
        self::assertSame([], $this->getMailsOfTemplate('PforumNewPost'));
        self::assertCount(1, $this->getMailsOfTemplate('PforumManageTopic'));
    }

    #[Test]
    public function activatingHiddenPostSendsNewPostMailToAuthorOfTopic(): void
    {
        $this->post('Post', 'activate', [
            'post' => '2',
            'token' => $this->get(AnonymousAccessTokenService::class)->generateToken('PostActivation', 2),
        ]);

        self::assertSame(0, (int)$this->getRecord('post', 2)['hidden']);
        $newPostMails = $this->getMailsOfTemplate('PforumNewPost');
        self::assertCount(1, $newPostMails);
        self::assertSame(['owner@example.com'], $newPostMails[0]->recipients);
        self::assertStringContainsString('Hidden reply', $newPostMails[0]->textBody);
    }

    #[Test]
    public function activatingHiddenPostWithInvalidTokenSendsNoMail(): void
    {
        $this->post('Post', 'activate', ['post' => '2', 'token' => 'invalid']);

        self::assertSame(1, (int)$this->getRecord('post', 2)['hidden']);
        self::assertSame([], CapturingTransport::getMails());
    }

    #[Test]
    public function manageMailContainsLinksWithTokens(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => [
                'title' => 'Topic to activate',
                'description' => 'My question',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        $uid = (int)$this->getRecordByTitle('topic', 'Topic to activate')['uid'];
        $tokenService = $this->get(AnonymousAccessTokenService::class);
        $mails = $this->getMailsOfTemplate('PforumManageTopic');
        self::assertCount(1, $mails);
        $text = rawurldecode($mails[0]->textBody);
        self::assertStringContainsString('[action]=edit&', $text);
        self::assertStringContainsString('[token]=' . $tokenService->generateToken('Topic', $uid) . '&', $text);
        self::assertStringContainsString('[action]=delete&', $text);
        self::assertStringContainsString('[action]=confirmActivation&', $text);
        self::assertStringContainsString('[token]=' . $tokenService->generateToken('TopicActivation', $uid) . '&', $text);
    }

    /**
     * @param array<string, string> $pluginArguments
     */
    private function post(string $controllerName, string $actionName, array $pluginArguments): void
    {
        $this->request($controllerName, $actionName, [], [self::PLUGIN_NAMESPACE => $pluginArguments]);
    }

    /**
     * @return CapturedMail[]
     */
    private function getMailsOfTemplate(string $template): array
    {
        return array_values(array_filter(
            CapturingTransport::getMails(),
            static fn(CapturedMail $mail): bool => $mail->template === $template,
        ));
    }

    private function setUpVisibleRecords(): void
    {
        $this->setUpForumSite(['EXT:pforum/Tests/Functional/Fixtures/Frontend/VisibleRecords.typoscript']);
    }
}
