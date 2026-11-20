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
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders the forms and confirmation pages of the Forum plugin. Anonymous
 * users activate, edit and delete their records with the links of the mail.
 * Records are activated and deleted via POST only, as mail security
 * scanners open the links of a mail automatically.
 */
class RecordManagementTest extends FunctionalTestCase
{
    private const PLUGIN_NAMESPACE = 'tx_pforum_forum';

    private const FORUM_PAGE = 2;

    /**
     * Hidden topic of the pforum user "Jane" of Fixtures/Forum.csv
     */
    private const HIDDEN_TOPIC = 2;

    private const VISIBLE_TOPIC = 1;

    private const ADMIN = 7;

    protected array $coreExtensionsToLoad = [
        'form',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected array $configurationToUseInTestInstance = [
        'MAIL' => [
            'transport' => 'null',
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
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => ['EXT:pforum/Tests/Functional/Fixtures/Frontend/Constants.typoscript'],
                'setup' => ['EXT:pforum/Tests/Functional/Fixtures/Frontend/Forum.typoscript'],
            ],
        );
        $this->get(SiteWriter::class)->write('pforum', [
            'rootPageId' => 1,
            'base' => 'https://www.example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);
    }

    #[Test]
    public function newActionRendersTopicFormForAnonymousUsers(): void
    {
        $content = (string)$this->request('Topic', 'new', ['forum' => '1'])->getBody();

        self::assertStringContainsString('<form', $content);
        self::assertMatchesRegularExpression('/name="tx_pforum_forum\[pforumTopic\]\[name\]"/', $content);
        self::assertMatchesRegularExpression('/name="tx_pforum_forum\[pforumTopic\]\[title\]"/', $content);
        self::assertMatchesRegularExpression('/<textarea[^>]*class="[^"]*form-control/', $content);
        self::assertStringContainsString('tx_pforum_forum%5Baction%5D=new', $content);
    }

    #[Test]
    public function editActionWithValidTokenRendersPrefilledTopicForm(): void
    {
        $content = (string)$this->request('Topic', 'edit', [
            'topic' => (string)self::HIDDEN_TOPIC,
            'token' => $this->generateToken('Topic', self::HIDDEN_TOPIC),
        ])->getBody();

        self::assertMatchesRegularExpression('/name="tx_pforum_forum\[pforumTopic\]\[title\]"[^>]*value="Hidden topic of Jane"/', $content);
        self::assertStringContainsString('tx_pforum_forum%5Baction%5D=edit', $content);
    }

    #[Test]
    public function editActionWithInvalidTokenRendersNoForm(): void
    {
        $content = (string)$this->request('Topic', 'edit', [
            'topic' => (string)self::HIDDEN_TOPIC,
            'token' => 'invalid',
        ])->getBody();

        self::assertStringNotContainsString('Hidden topic of Jane', $content);
        self::assertStringContainsString('You are not allowed to edit records that are not assigned to you.', $content);
    }

    #[Test]
    public function deleteViaGetShowsConfirmationOnly(): void
    {
        $content = (string)$this->request('Topic', 'delete', [
            'topic' => (string)self::HIDDEN_TOPIC,
            'token' => $this->generateToken('Topic', self::HIDDEN_TOPIC),
        ])->getBody();

        self::assertStringContainsString('Hidden topic of Jane', $content);
        self::assertMatchesRegularExpression('/<input[^>]*type="submit"[^>]*value="Delete Topic"/', $content);
        self::assertFalse($this->isDeleted('topic', self::HIDDEN_TOPIC));
    }

    #[Test]
    public function deleteViaPostWithValidTokenDeletesTopicAndItsPosts(): void
    {
        $this->post('Topic', 'delete', [
            'topic' => (string)self::VISIBLE_TOPIC,
            'token' => $this->generateToken('Topic', self::VISIBLE_TOPIC),
        ]);

        self::assertTrue($this->isDeleted('topic', self::VISIBLE_TOPIC));
        self::assertTrue($this->isDeleted('post', 1));
        self::assertStringContainsString('Topic was deleted.', $this->getFlashMessages());
    }

    #[Test]
    public function deleteViaPostWithTokenOfOtherTopicKeepsTopic(): void
    {
        $this->post('Topic', 'delete', [
            'topic' => (string)self::HIDDEN_TOPIC,
            'token' => $this->generateToken('Topic', self::VISIBLE_TOPIC),
        ]);

        self::assertFalse($this->isDeleted('topic', self::HIDDEN_TOPIC));
    }

    #[Test]
    public function confirmActivationWithValidTokenShowsConfirmationForm(): void
    {
        $token = $this->generateToken('TopicActivation', self::HIDDEN_TOPIC);

        $content = (string)$this->request('Topic', 'confirmActivation', [
            'topic' => (string)self::HIDDEN_TOPIC,
            'token' => $token,
        ])->getBody();

        self::assertStringContainsString('Hidden topic of Jane', $content);
        self::assertMatchesRegularExpression('/<input[^>]*type="submit"[^>]*value="Activate Topic"/', $content);
        self::assertStringContainsString('name="' . self::PLUGIN_NAMESPACE . '[token]" value="' . $token . '"', $content);
        self::assertTrue($this->isHidden('topic', self::HIDDEN_TOPIC));
    }

    #[Test]
    public function confirmActivationWithTokenToEditTheTopicRedirectsWithError(): void
    {
        $content = (string)$this->request('Topic', 'confirmActivation', [
            'topic' => (string)self::HIDDEN_TOPIC,
            'token' => $this->generateToken('Topic', self::HIDDEN_TOPIC),
        ])->getBody();

        self::assertStringNotContainsString('Activate Topic', $content);
        self::assertStringContainsString('The entry can not be activated.', $this->getFlashMessages());
    }

    #[Test]
    public function activateViaGetKeepsTopicHidden(): void
    {
        $this->request('Topic', 'activate', [
            'topic' => (string)self::HIDDEN_TOPIC,
            'token' => $this->generateToken('TopicActivation', self::HIDDEN_TOPIC),
        ]);

        self::assertTrue($this->isHidden('topic', self::HIDDEN_TOPIC));
    }

    #[Test]
    public function activateViaPostWithValidTokenActivatesTopic(): void
    {
        $this->post('Topic', 'activate', [
            'topic' => (string)self::HIDDEN_TOPIC,
            'token' => $this->generateToken('TopicActivation', self::HIDDEN_TOPIC),
        ]);

        self::assertFalse($this->isHidden('topic', self::HIDDEN_TOPIC));
        self::assertStringContainsString('Topic was successfully activated', $this->getFlashMessages());
    }

    #[Test]
    public function activateViaPostWithInvalidTokenKeepsTopicHidden(): void
    {
        $this->post('Topic', 'activate', ['topic' => (string)self::HIDDEN_TOPIC, 'token' => 'invalid']);

        self::assertTrue($this->isHidden('topic', self::HIDDEN_TOPIC));
    }

    #[Test]
    public function activateViaPostByAdminActivatesPostWithoutToken(): void
    {
        $this->post('Post', 'activate', ['post' => '2'], self::ADMIN);

        self::assertFalse($this->isHidden('post', 2));
    }

    /**
     * @param array<string, string> $pluginArguments
     */
    private function post(string $controllerName, string $actionName, array $pluginArguments, int $frontendUserUid = 0): ResponseInterface
    {
        return $this->request($controllerName, $actionName, [], [self::PLUGIN_NAMESPACE => $pluginArguments], $frontendUserUid);
    }

    /**
     * @param array<string, string> $pluginArguments Arguments of the query string
     * @param array<string, mixed> $parsedBody
     */
    private function request(
        string $controllerName,
        string $actionName,
        array $pluginArguments = [],
        array $parsedBody = [],
        int $frontendUserUid = 0,
    ): ResponseInterface {
        $queryParameters = [
            'id' => self::FORUM_PAGE,
            self::PLUGIN_NAMESPACE . '[action]' => $actionName,
            self::PLUGIN_NAMESPACE . '[controller]' => $controllerName,
        ];
        foreach ($pluginArguments as $argumentName => $value) {
            $queryParameters[self::PLUGIN_NAMESPACE . '[' . $argumentName . ']'] = $value;
        }
        $queryParameters['cHash'] = $this->get(CacheHashCalculator::class)
            ->generateForParameters(HttpUtility::buildQueryString($queryParameters));

        $request = (new InternalRequest('https://www.example.com/'))->withQueryParameters($queryParameters);
        if ($parsedBody !== []) {
            $request = $request->withMethod('POST')->withParsedBody($parsedBody);
            // The testing framework can not build a body of nested arrays
            $request->getBody()->write(http_build_query($parsedBody));
        }

        $context = new InternalRequestContext();
        if ($frontendUserUid > 0) {
            $context = $context->withFrontendUserId($frontendUserUid);
        }

        return $this->executeFrontendSubRequest($request, $context);
    }

    private function generateToken(string $type, int $uid): string
    {
        return $this->get(AnonymousAccessTokenService::class)->generateToken($type, $uid);
    }

    /**
     * The flash messages of the redirect are stored in the session
     */
    private function getFlashMessages(): string
    {
        $sessionData = $this->getConnectionPool()
            ->getConnectionForTable('fe_sessions')
            ->select(['ses_data'], 'fe_sessions')
            ->fetchFirstColumn();

        return implode('', array_map(strval(...), $sessionData));
    }

    private function isHidden(string $recordType, int $uid): bool
    {
        return (int)$this->getRecord($recordType, $uid)['hidden'] === 1;
    }

    private function isDeleted(string $recordType, int $uid): bool
    {
        return (int)$this->getRecord($recordType, $uid)['deleted'] === 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function getRecord(string $recordType, int $uid): array
    {
        $table = 'tx_pforum_domain_model_' . $recordType;
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $record = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $uid))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($record) ? $record : [];
    }
}
