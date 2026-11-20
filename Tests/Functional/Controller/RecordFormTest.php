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
use JWeiland\Pforum\Tests\Functional\Traits\FrontendRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Submits the Extbase forms of topics and posts
 */
class RecordFormTest extends FunctionalTestCase
{
    use FrontendRequestTrait;

    /**
     * The mails are written to this file in mbox format
     */
    private string $mailFile = '';

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => 'mbox',
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
        // The settings of the test instance are written for the first test of the class only
        $this->mailFile = sys_get_temp_dir() . '/pforum-record-form-test.mbox';
        $this->configurationToUseInTestInstance['MAIL']['transport_mbox_file'] = $this->mailFile;
        if (is_file($this->mailFile)) {
            unlink($this->mailFile);
        }

        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->setUpForumSite();
    }

    #[Test]
    public function createStoresHiddenTopicOfPforumUserAndSendsMailWithActivationLink(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => [
                'title' => 'New topic',
                'description' => 'My question',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        $topic = $this->getTopicByTitle('New topic');
        self::assertSame(1, (int)$topic['hidden']);
        self::assertSame(1, (int)$topic['forum']);
        self::assertSame('<p>My question</p>', $topic['description']);
        self::assertGreaterThan(0, (int)$topic['anonymous_user']);
        self::assertStringContainsString('New Topic was created. We have send an email', $this->getFlashMessages());

        $mail = $this->getMails();
        self::assertStringContainsString('john@example.com', $mail);
        self::assertStringContainsString('confirmActivation', $mail);
        self::assertStringContainsString(
            htmlspecialchars(rawurlencode($this->get(AnonymousAccessTokenService::class)->generateToken('TopicActivation', (int)$topic['uid']))),
            $mail,
        );
    }

    #[Test]
    public function createWithoutMandatoryEmailStoresNoTopic(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => [
                'title' => 'Without email',
                'description' => 'My question',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => ''],
            ],
        ]);

        self::assertSame([], $this->getTopicByTitle('Without email'));
    }

    #[Test]
    public function createWithInvalidEmailStoresNoTopic(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'topic' => [
                'title' => 'Invalid email',
                'description' => 'My question',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@'],
            ],
        ]);

        self::assertSame([], $this->getTopicByTitle('Invalid email'));
    }

    #[Test]
    public function createWithPreviewStoresHiddenTopicWithoutMail(): void
    {
        $this->submitForm('Topic', 'create', [], [
            'forum' => '1',
            'preview' => 'Preview',
            'topic' => [
                'title' => 'Preview topic',
                'description' => 'My question',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        self::assertSame(1, (int)$this->getTopicByTitle('Preview topic')['hidden']);
        self::assertSame('', $this->getMails());
    }

    #[Test]
    public function updateOfNewTopicPublishesTopicAndSendsMail(): void
    {
        $this->submitForm('Topic', 'update', [], [
            'isNew' => '1',
            'token' => $this->generateToken('Topic', 2),
            'topic' => [
                '__identity' => '2',
                'title' => 'Published topic',
                'description' => 'My question',
                'anonymousUser' => ['__identity' => '1', 'name' => 'Jane', 'username' => 'jane', 'email' => 'jane@example.com'],
            ],
        ]);

        self::assertSame('Published topic', $this->getTopicByTitle('Published topic')['title'] ?? '');
        self::assertStringContainsString('jane@example.com', $this->getMails());
    }

    #[Test]
    public function updateOfHiddenTopicKeepsTopicHidden(): void
    {
        $this->submitForm('Topic', 'update', [], [
            'token' => $this->generateToken('Topic', 2),
            'topic' => [
                '__identity' => '2',
                'title' => 'Edited topic',
                'description' => 'My question',
                'anonymousUser' => ['__identity' => '1', 'name' => 'Jane', 'username' => 'jane', 'email' => 'jane@example.com'],
            ],
        ]);

        $topic = $this->getTopicByTitle('Edited topic');
        self::assertSame(1, (int)$topic['hidden']);
        self::assertStringContainsString('Topic was updated.', $this->getFlashMessages());
        self::assertSame('', $this->getMails());
    }

    #[Test]
    public function updateWithTokenOfOtherTopicKeepsTopic(): void
    {
        $this->submitForm('Topic', 'update', [], [
            'token' => $this->generateToken('Topic', 1),
            'topic' => [
                '__identity' => '2',
                'title' => 'Foreign topic',
                'description' => 'My question',
                'anonymousUser' => ['__identity' => '1', 'name' => 'Jane', 'username' => 'jane', 'email' => 'jane@example.com'],
            ],
        ]);

        self::assertSame([], $this->getTopicByTitle('Foreign topic'));
    }

    #[Test]
    public function createStoresPostInTopic(): void
    {
        $this->submitForm('Post', 'create', [], [
            'topic' => '1',
            'post' => [
                'title' => 'New answer',
                'description' => 'My answer',
                'anonymousUser' => ['name' => 'John', 'username' => 'john', 'email' => 'john@example.com'],
            ],
        ]);

        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_pforum_domain_model_post');
        $queryBuilder->getRestrictions()->removeAll();
        $post = $queryBuilder->select('*')->from('tx_pforum_domain_model_post')
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter('New answer')))
            ->executeQuery()->fetchAssociative();

        self::assertIsArray($post);
        self::assertSame(1, (int)$post['topic']);
    }

    private function generateToken(string $type, int $uid): string
    {
        return $this->get(AnonymousAccessTokenService::class)->generateToken($type, $uid);
    }

    /**
     * @return array<string, mixed>
     */
    private function getTopicByTitle(string $title): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('tx_pforum_domain_model_topic');
        $queryBuilder->getRestrictions()->removeAll();
        $topic = $queryBuilder->select('*')->from('tx_pforum_domain_model_topic')
            ->where($queryBuilder->expr()->eq('title', $queryBuilder->createNamedParameter($title)))
            ->executeQuery()->fetchAssociative();

        return is_array($topic) ? $topic : [];
    }

    private function getFlashMessages(): string
    {
        $sessionData = $this->getConnectionPool()
            ->getConnectionForTable('fe_sessions')
            ->select(['ses_data'], 'fe_sessions')
            ->fetchFirstColumn();

        return implode('', array_map(strval(...), $sessionData));
    }

    protected function tearDown(): void
    {
        if (is_file($this->mailFile)) {
            unlink($this->mailFile);
        }

        parent::tearDown();
    }

    private function getMails(): string
    {
        return is_file($this->mailFile) ? quoted_printable_decode((string)file_get_contents($this->mailFile)) : '';
    }
}
