<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Backend module "pforumBackendModule" with a backend user
 */
class AdministrationControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/BackendUsers.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function indexRendersModule(): void
    {
        $response = $this->request('index');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-module-name="pforumBackendModule"', (string)$response->getBody());
    }

    #[Test]
    public function listHiddenTopicsListsHiddenTopicsOnly(): void
    {
        $content = (string)$this->request('listHiddenTopics')->getBody();

        self::assertStringContainsString('Manage hidden topics', $content);
        self::assertStringContainsString('Hidden topic of Jane', $content);
        self::assertStringContainsString('Hidden topic of owner', $content);
        self::assertStringNotContainsString('Welcome', $content);
        self::assertStringNotContainsString('Topic of Mallory', $content);
        self::assertStringContainsString('activateTopic?', $content);
        self::assertStringContainsString('record=2', $content);
    }

    #[Test]
    public function listHiddenPostsListsHiddenPostsOnly(): void
    {
        $content = (string)$this->request('listHiddenPosts')->getBody();

        self::assertStringContainsString('Hidden reply', $content);
        self::assertStringNotContainsString('Answer of Mallory', $content);
        self::assertStringContainsString('activatePost?', $content);
        self::assertStringContainsString('record=2', $content);
    }

    #[Test]
    public function listHiddenTopicsShowsInfoIfNothingIsHidden(): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_pforum_domain_model_topic')
            ->update('tx_pforum_domain_model_topic', ['hidden' => 0], ['hidden' => 1]);

        $content = (string)$this->request('listHiddenTopics')->getBody();

        self::assertStringContainsString('There are currently no activateable records available', $content);
        self::assertStringNotContainsString('<table', $content);
    }

    #[Test]
    public function activateTopicUnhidesTopicAndRedirectsToList(): void
    {
        $response = $this->request('activateTopic', ['record' => '2']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('listHiddenTopics', $response->getHeaderLine('Location'));
        self::assertSame(0, (int)$this->getRecord('topic', 2)['hidden']);
        // Other hidden records stay hidden
        self::assertSame(1, (int)$this->getRecord('topic', 3)['hidden']);
    }

    #[Test]
    public function activateTopicShowsSuccessMessageInList(): void
    {
        $this->request('activateTopic', ['record' => '2']);

        $content = (string)$this->request('listHiddenTopics')->getBody();

        self::assertStringContainsString('[OK] Topic activated: Topic "Hidden topic of Jane" was activated.', $content);
        self::assertStringNotContainsString('<td>Hidden topic of Jane</td>', $content);
        self::assertStringContainsString('<td>Hidden topic of owner</td>', $content);
    }

    #[Test]
    public function activatePostUnhidesPostAndShowsSuccessMessageInList(): void
    {
        $response = $this->request('activatePost', ['record' => '2']);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('listHiddenPosts', $response->getHeaderLine('Location'));
        self::assertSame(0, (int)$this->getRecord('post', 2)['hidden']);

        $content = (string)$this->request('listHiddenPosts')->getBody();
        self::assertStringContainsString('[OK] Post activated: Post "Hidden reply" was activated.', $content);
    }

    #[Test]
    public function activateTopicOfUnknownUidShowsErrorMessageInsteadOfFatalError(): void
    {
        $response = $this->request('activateTopic', ['record' => '9999']);

        self::assertSame(303, $response->getStatusCode());
        $content = (string)$this->request('listHiddenTopics')->getBody();
        self::assertStringContainsString('[ERROR] Topic not found: The topic with UID 9999 does not exist anymore.', $content);
        self::assertSame(1, (int)$this->getRecord('topic', 2)['hidden']);
    }

    #[Test]
    public function activatePostOfUnknownUidShowsErrorMessageInsteadOfFatalError(): void
    {
        $response = $this->request('activatePost', ['record' => '9999']);

        self::assertSame(303, $response->getStatusCode());
        $content = (string)$this->request('listHiddenPosts')->getBody();
        self::assertStringContainsString('[ERROR] Post not found: The post with UID 9999 does not exist anymore.', $content);
    }

    #[Test]
    public function activateTopicIgnoresDeletedTopic(): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_pforum_domain_model_topic')
            ->update('tx_pforum_domain_model_topic', ['deleted' => 1], ['uid' => 2]);

        $this->request('activateTopic', ['record' => '2']);

        $content = (string)$this->request('listHiddenTopics')->getBody();
        self::assertStringContainsString('[ERROR] Topic not found: The topic with UID 2 does not exist anymore.', $content);
        self::assertSame(1, (int)$this->getRecord('topic', 2)['hidden']);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function request(string $actionName, array $parameters = []): ResponseInterface
    {
        $route = $this->get(Router::class)->getRoute('pforumBackendModule');
        $module = $this->get(ModuleProvider::class)->getModule('pforumBackendModule', $GLOBALS['BE_USER']);
        $serverRequest = new ServerRequest(
            'https://www.example.com/typo3/module/web/pforum',
            'GET',
            'php://input',
            [],
            ['HTTP_HOST' => 'www.example.com', 'HTTPS' => 'on', 'REQUEST_URI' => '/typo3/module/web/pforum', 'SCRIPT_NAME' => '/typo3/index.php'],
        );
        $request = $serverRequest
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($serverRequest))
            ->withAttribute('route', $route)
            ->withAttribute('module', $module)
            ->withQueryParams(['controller' => 'Administration', 'action' => $actionName, ...$parameters]);

        return $this->get(Bootstrap::class)->handleBackendRequest($request);
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
