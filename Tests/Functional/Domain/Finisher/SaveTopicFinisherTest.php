<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Domain\Finisher;

use JWeiland\Pforum\Domain\Finisher\SaveTopicFinisher;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Tests\Functional\Traits\RecordFormDefinitionTrait;
use JWeiland\Pforum\Tests\Functional\Traits\RecordFormRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Domain\Runtime\FormState;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class SaveTopicFinisherTest extends FunctionalTestCase
{
    use RecordFormDefinitionTrait;
    use RecordFormRequestTrait;

    private const TABLE = 'tx_pforum_domain_model_topic';

    private const USER_TABLE = 'tx_pforum_domain_model_anonymoususer';

    private const FORM_VALUES = [
        'name' => 'John',
        'username' => 'john',
        'email' => 'john@example.com',
        'title' => '<b>New</b> topic',
        'description' => "First line\nsecond line & more\n\nParagraph",
    ];

    protected array $coreExtensionsToLoad = [
        'form',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/Forum.csv');
    }

    #[Test]
    public function executeStoresNewTopicOfAnonymousUserInFolderOfForum(): void
    {
        $topicUid = $this->executeFinisher(self::FORM_VALUES, $this->buildFormRequest(['forum' => '1']));
        $topic = $this->getRecord(self::TABLE, $topicUid);
        $user = $this->getRecord(self::USER_TABLE, (int)$topic['anonymous_user']);

        self::assertSame(10, (int)$topic['pid']);
        self::assertSame(1, (int)$topic['forum']);
        self::assertSame(0, (int)$topic['hidden']);
        self::assertSame(0, (int)$topic['frontend_user']);
        self::assertSame('New topic', $topic['title']);
        self::assertSame("<p>First line<br />second line &amp; more</p>\r\n<p>Paragraph</p>", $topic['description']);
        self::assertSame(['John', 'john', 'john@example.com', 10], [$user['name'], $user['username'], $user['email'], (int)$user['pid']]);
        self::assertSame(4, (int)$this->getRecord('tx_pforum_domain_model_forum', 1)['topics']);
    }

    #[Test]
    public function executeHidesNewTopicAndProvidesActivationToken(): void
    {
        $finisherContext = null;
        $topicUid = $this->executeFinisher(
            self::FORM_VALUES,
            $this->buildFormRequest(['forum' => '1'], ['emailIsMandatory' => '1', 'topic.' => ['hideAtCreation' => '1']]),
            $finisherContext,
        );
        $tokenService = $this->get(AnonymousAccessTokenService::class);
        $variables = $finisherContext?->getFinisherVariableProvider();

        self::assertSame(1, (int)$this->getRecord(self::TABLE, $topicUid)['hidden']);
        self::assertTrue($tokenService->isTokenValid('TopicActivation', $topicUid, (string)$variables?->get('PforumSaveTopic', 'activationToken')));
        self::assertTrue($tokenService->isTokenValid('Topic', $topicUid, (string)$variables?->get('PforumSaveTopic', 'accessToken')));
    }

    #[Test]
    public function executeProvidesNoActivationTokenIfAdminActivatesTopics(): void
    {
        $finisherContext = null;
        $this->executeFinisher(
            self::FORM_VALUES,
            $this->buildFormRequest(
                ['forum' => '1'],
                ['emailIsMandatory' => '1', 'topic.' => ['hideAtCreation' => '1', 'activateByAdmin' => '1']],
            ),
            $finisherContext,
        );

        self::assertSame('', $finisherContext?->getFinisherVariableProvider()->get('PforumSaveTopic', 'activationToken'));
    }

    #[Test]
    public function executeStoresNewTopicOfFrontendUserWithoutPforumUser(): void
    {
        $this->loginFrontendUser(6);

        $topicUid = $this->executeFinisher(self::FORM_VALUES, $this->buildFormRequest(['forum' => '1'], ['auth' => '2']));
        $topic = $this->getRecord(self::TABLE, $topicUid);

        self::assertSame(6, (int)$topic['frontend_user']);
        self::assertSame(0, (int)$topic['anonymous_user']);
    }

    #[Test]
    public function executeWithoutLoginInFrontendUserModeThrowsException(): void
    {
        $this->expectException(FinisherException::class);
        $this->expectExceptionCode(1763890801);

        $this->executeFinisher(self::FORM_VALUES, $this->buildFormRequest(['forum' => '1'], ['auth' => '2']));
    }

    #[Test]
    public function executeForNotExistingForumThrowsException(): void
    {
        $this->expectException(FinisherException::class);

        $this->executeFinisher(self::FORM_VALUES, $this->buildFormRequest(['forum' => '99']));
    }

    #[Test]
    public function executeUpdatesTopicOfAnonymousUserWithValidToken(): void
    {
        $this->executeFinisher(
            self::FORM_VALUES,
            $this->buildFormRequest(['topic' => '2', 'token' => $this->generateToken('Topic', 2)]),
        );
        $topic = $this->getRecord(self::TABLE, 2);

        self::assertSame('New topic', $topic['title']);
        self::assertSame(1, (int)$topic['hidden']);
        self::assertSame(1, (int)$topic['anonymous_user']);
        self::assertSame('John', $this->getRecord(self::USER_TABLE, 1)['name']);
    }

    #[Test]
    public function executeWithTokenOfOtherTopicThrowsException(): void
    {
        $this->expectException(FinisherException::class);

        $this->executeFinisher(
            self::FORM_VALUES,
            $this->buildFormRequest(['topic' => '2', 'token' => $this->generateToken('Topic', 1)]),
        );
    }

    #[Test]
    public function executeWithActivationTokenAsAccessTokenThrowsException(): void
    {
        $this->expectException(FinisherException::class);

        $this->executeFinisher(
            self::FORM_VALUES,
            $this->buildFormRequest(['topic' => '2', 'token' => $this->generateToken('TopicActivation', 2)]),
        );
    }

    #[Test]
    public function executeUpdatesTopicOfAuthor(): void
    {
        $this->loginFrontendUser(5);

        $this->executeFinisher(self::FORM_VALUES, $this->buildFormRequest(['topic' => '1'], ['auth' => '2']));

        self::assertSame('New topic', $this->getRecord(self::TABLE, 1)['title']);
        self::assertSame(5, (int)$this->getRecord(self::TABLE, 1)['frontend_user']);
    }

    #[Test]
    public function executeForTopicOfOtherFrontendUserThrowsException(): void
    {
        $this->loginFrontendUser(6);

        $this->expectException(FinisherException::class);

        $this->executeFinisher(self::FORM_VALUES, $this->buildFormRequest(['topic' => '1'], ['auth' => '2']));
    }

    /**
     * @param array<string, mixed> $formValues
     * @return int UID of the stored topic
     */
    private function executeFinisher(array $formValues, Request $request, ?FinisherContext &$finisherContext = null): int
    {
        $formState = new FormState();
        foreach ($formValues as $identifier => $value) {
            $formState->setFormValue($identifier, $value);
        }

        $formRuntime = $this->createMock(FormRuntime::class);
        $formRuntime->method('getFormDefinition')->willReturn($this->buildRecordFormDefinition('topic'));
        $formRuntime->method('getFormState')->willReturn($formState);
        $formRuntime->method('getRequest')->willReturn($request);
        $formRuntime->method('getRenderingOptions')->willReturn(['translation' => ['translationFiles' => []]]);

        $finisherContext = new FinisherContext($formRuntime, $request);
        $finisher = $this->get(SaveTopicFinisher::class);
        $finisher->setFinisherIdentifier('PforumSaveTopic');
        $finisher->setOptions(['elements' => $this->getElementsConfiguration('topic')]);
        $finisher->execute($finisherContext);

        return (int)$finisherContext->getFinisherVariableProvider()->get('PforumSaveTopic', 'recordUid');
    }

    /**
     * @return array<string, mixed>
     */
    private function getRecord(string $table, int $uid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $record = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($record) ? $record : [];
    }
}
