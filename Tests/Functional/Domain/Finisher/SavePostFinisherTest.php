<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Domain\Finisher;

use JWeiland\Pforum\Domain\Finisher\SavePostFinisher;
use JWeiland\Pforum\Form\RecordFormContextBuilder;
use JWeiland\Pforum\Form\RecordFormDataMapper;
use JWeiland\Pforum\Mail\NewPostMail;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Service\RecordAccessService;
use JWeiland\Pforum\Service\RecordService;
use JWeiland\Pforum\Tests\Functional\Traits\RecordFormDefinitionTrait;
use JWeiland\Pforum\Tests\Functional\Traits\RecordFormRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Domain\Runtime\FormState;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class SavePostFinisherTest extends FunctionalTestCase
{
    use RecordFormDefinitionTrait;
    use RecordFormRequestTrait;

    private const TABLE = 'tx_pforum_domain_model_post';

    private const FORM_VALUES = [
        'name' => 'John',
        'username' => '',
        'email' => 'john@example.com',
        'title' => '',
        'description' => 'My answer',
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
    public function executeStoresNewPostInFolderOfTopicAndInformsAuthorOfTopic(): void
    {
        $newPostMail = $this->createMock(NewPostMail::class);
        $newPostMail->expects($this->once())->method('send');

        $postUid = $this->executeFinisher($newPostMail, $this->buildFormRequest(['topic' => '1']));
        $post = $this->getRecord(self::TABLE, $postUid);

        self::assertSame(10, (int)$post['pid']);
        self::assertSame(1, (int)$post['topic']);
        self::assertSame(0, (int)$post['hidden']);
        self::assertSame('<p>My answer</p>', $post['description']);
        self::assertSame(3, (int)$this->getRecord('tx_pforum_domain_model_topic', 1)['posts']);
    }

    #[Test]
    public function executeDoesNotInformAuthorOfTopicAboutHiddenPost(): void
    {
        $newPostMail = $this->createMock(NewPostMail::class);
        $newPostMail->expects($this->never())->method('send');

        $postUid = $this->executeFinisher(
            $newPostMail,
            $this->buildFormRequest(['topic' => '1'], ['emailIsMandatory' => '1', 'post.' => ['hideAtCreation' => '1']]),
        );

        self::assertSame(1, (int)$this->getRecord(self::TABLE, $postUid)['hidden']);
    }

    #[Test]
    public function executeDoesNotInformAuthorOfTopicAboutEditedPost(): void
    {
        $newPostMail = $this->createMock(NewPostMail::class);
        $newPostMail->expects($this->never())->method('send');

        $this->executeFinisher($newPostMail, $this->buildFormRequest(['post' => '2', 'token' => $this->generateToken('Post', 2)]));

        self::assertSame('<p>My answer</p>', $this->getRecord(self::TABLE, 2)['description']);
        self::assertSame(1, (int)$this->getRecord(self::TABLE, 2)['hidden']);
    }

    private function executeFinisher(NewPostMail $newPostMail, Request $request): int
    {
        $formState = new FormState();
        foreach (self::FORM_VALUES as $identifier => $value) {
            $formState->setFormValue($identifier, $value);
        }

        $formRuntime = $this->createMock(FormRuntime::class);
        $formRuntime->method('getFormDefinition')->willReturn($this->buildRecordFormDefinition('post'));
        $formRuntime->method('getFormState')->willReturn($formState);
        $formRuntime->method('getRequest')->willReturn($request);
        $formRuntime->method('getRenderingOptions')->willReturn(['translation' => ['translationFiles' => []]]);

        $finisherContext = new FinisherContext($formRuntime, $request);
        $finisher = new SavePostFinisher(
            $this->get(RecordFormContextBuilder::class),
            $this->get(RecordAccessService::class),
            $this->get(RecordFormDataMapper::class),
            $this->get(RecordService::class),
            $this->get(AnonymousAccessTokenService::class),
            $newPostMail,
        );
        $finisher->setFinisherIdentifier('PforumSavePost');
        $finisher->setOptions(['elements' => $this->getElementsConfiguration('post')]);
        $finisher->execute($finisherContext);

        return (int)$finisherContext->getFinisherVariableProvider()->get('PforumSavePost', 'recordUid');
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
