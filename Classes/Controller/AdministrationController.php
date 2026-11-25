<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Controller;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Module\ExtbaseModule;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Backend module to list and activate hidden topics and posts
 */
class AdministrationController extends ActionController
{
    private const LANGUAGE_FILE = 'LLL:EXT:pforum/Resources/Private/Language/locallang_mod_administration.xlf:';

    private ModuleTemplate $moduleTemplate;

    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly PostRepository $postRepository,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly ComponentFactory $componentFactory,
        private readonly LoggerInterface $logger,
    ) {}

    protected function initializeAction(): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->addDocHeaderButtons();
    }

    public function indexAction(): ResponseInterface
    {
        return $this->moduleTemplate->renderResponse('Backend/Administration/Index');
    }

    public function listHiddenTopicsAction(): ResponseInterface
    {
        $this->moduleTemplate->assign('topics', $this->topicRepository->findAllHidden()->toArray());

        return $this->moduleTemplate->renderResponse('Backend/Administration/ListHiddenTopics');
    }

    public function listHiddenPostsAction(): ResponseInterface
    {
        $this->moduleTemplate->assign('posts', $this->postRepository->findAllHidden()->toArray());

        return $this->moduleTemplate->renderResponse('Backend/Administration/ListHiddenPosts');
    }

    public function activateTopicAction(int $record): ResponseInterface
    {
        $this->activate(RecordType::Topic, $this->topicRepository->findHiddenObject($record), $record);

        return $this->redirect('listHiddenTopics');
    }

    public function activatePostAction(int $record): ResponseInterface
    {
        $this->activate(RecordType::Post, $this->postRepository->findHiddenObject($record), $record);

        return $this->redirect('listHiddenPosts');
    }

    private function activate(RecordType $recordType, Topic|Post|null $record, int $uid): void
    {
        if ($record === null) {
            $this->logger->warning('{type} {uid} to activate not found', ['type' => $recordType->getControllerName(), 'uid' => $uid]);
            $this->addTranslatedFlashMessage('recordNotFound', ContextualFeedbackSeverity::ERROR, $recordType, (string)$uid);

            return;
        }

        $record->setHidden(false);
        $record instanceof Topic ? $this->topicRepository->update($record) : $this->postRepository->update($record);

        $this->logger->info('{type} {uid} activated in backend module', [
            'type' => $recordType->getControllerName(),
            'uid' => $uid,
            'backendUser' => $this->getBackendUser()?->getUserId(),
        ]);
        $this->addTranslatedFlashMessage('recordActivated', ContextualFeedbackSeverity::OK, $recordType, $record->getTitle());
    }

    private function addDocHeaderButtons(): void
    {
        $docHeader = $this->moduleTemplate->getDocHeaderComponent();
        if ($this->request->getControllerActionName() !== 'index') {
            $docHeader->getButtonBar()->addButton(
                $this->componentFactory->createBackButton($this->uriBuilder->reset()->uriFor('index')),
                ButtonBar::BUTTON_POSITION_LEFT,
            );
        }

        $module = $this->request->getAttribute('module');
        if ($module instanceof ExtbaseModule) {
            $docHeader->setShortcutContext(
                $module->getIdentifier(),
                $this->translate('mlang_tabs_tab'),
                ['action' => $this->request->getControllerActionName()],
            );
        }
    }

    private function addTranslatedFlashMessage(
        string $key,
        ContextualFeedbackSeverity $severity,
        RecordType $recordType,
        string $value,
    ): void {
        $this->addFlashMessage(
            sprintf($this->translate($recordType->value . '.' . $key . '.message'), $value),
            $this->translate($recordType->value . '.' . $key . '.title'),
            $severity,
        );
    }

    private function translate(string $key): string
    {
        return (string)LocalizationUtility::translate(self::LANGUAGE_FILE . $key);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        return $backendUser instanceof BackendUserAuthentication ? $backendUser : null;
    }
}
