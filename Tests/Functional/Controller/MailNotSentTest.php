<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Controller;

use JWeiland\Pforum\Tests\Functional\Fixtures\Mail\FailingTransport;
use JWeiland\Pforum\Tests\Functional\Traits\DatabaseRecordTrait;
use JWeiland\Pforum\Tests\Functional\Traits\FrontendRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The mail transport fails like an unreachable SMTP server. The records
 * are created nevertheless. The author is warned, that the mail with the
 * links to edit and delete the record could not be sent.
 */
class MailNotSentTest extends FunctionalTestCase
{
    use DatabaseRecordTrait;
    use FrontendRequestTrait;

    private const MAIL_NOT_SENT_MESSAGE = 'The email with the links to edit and delete your entry could not be sent.';

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => FailingTransport::class,
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

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->setUpForumSite();
    }

    #[Test]
    public function createTopicRedirectsToForumWithWarningIfMailCanNotBeSent(): void
    {
        $response = $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => [
                'title' => 'New topic',
                'description' => 'My question',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        self::assertSame(303, $response->getStatusCode());
        $location = rawurldecode($response->getHeaderLine('Location'));
        self::assertStringContainsString('[controller]=Forum', $location);
        self::assertStringContainsString('[action]=show', $location);
        self::assertStringContainsString('[forum]=1', $location);

        $flashMessages = $this->getFlashMessages();
        self::assertStringContainsString(self::MAIL_NOT_SENT_MESSAGE, $flashMessages);
        // Severity 1 is a warning
        self::assertStringContainsString('"severity":1,"title":"","message":"' . self::MAIL_NOT_SENT_MESSAGE, $flashMessages);
        // The warning is followed by the message about the created topic
        self::assertStringContainsString('New Topic was created', $flashMessages);
    }

    #[Test]
    public function createTopicStoresTopicIfMailCanNotBeSent(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => [
                'title' => 'New topic',
                'description' => 'My question',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        $topic = $this->getRecordByTitle('topic', 'New topic');
        self::assertNotSame([], $topic);
        self::assertSame(1, (int)$topic['forum']);
        self::assertSame('new-topic', $topic['path_segment']);
    }

    #[Test]
    public function createPostRedirectsToTopicWithWarningIfMailCanNotBeSent(): void
    {
        $response = $this->submitForm('Post', 'create', [], [
            'topic' => '1',
            'post' => [
                'title' => 'New answer',
                'description' => 'My answer',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        self::assertSame(303, $response->getStatusCode());
        $location = rawurldecode($response->getHeaderLine('Location'));
        self::assertStringContainsString('[controller]=Topic', $location);
        self::assertStringContainsString('[topic]=1', $location);
        self::assertStringContainsString(self::MAIL_NOT_SENT_MESSAGE, $this->getFlashMessages());
        self::assertNotSame([], $this->getRecordByTitle('post', 'New answer'));
    }
}
