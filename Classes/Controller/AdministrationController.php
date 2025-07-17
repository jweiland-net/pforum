<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Controller;

use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use Psr\Http\Message\ResponseInterface;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3Fluid\Fluid\View\TemplateAwareViewInterface;

/**
 * Main controller to list and show postings/questions
 */
class AdministrationController extends ActionController
{
    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly PostRepository $postRepository,
        private readonly ModuleTemplateFactory $moduleTemplateFactory
    ) {}

    /**
     * Set up the doc header properly here
     */
    protected function initializeView($view): void
    {
        if ($view instanceof TemplateAwareViewInterface) {
            //$view->getModuleTemplate()->getDocHeaderComponent()->setMetaInformation([]);
            $this->createDocheaderActionButtons();
            $this->createShortcutButton();
        }
    }

    protected function createDocheaderActionButtons(): void
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if (!in_array($this->actionMethodName, ['indexAction', 'listHiddenTopicsAction', 'listHiddenPostsAction'], true)) {
            return;
        }

        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $uriBuilder = $this->controllerContext->getUriBuilder();

        $button = $buttonBar->makeLinkButton()
            ->setHref($uriBuilder->reset()->uriFor('index', [], 'Administration'))
            ->setTitle('Back')
            ->setIcon($moduleTemplate->getIconFactory()->getIcon('actions-view-go-back', Icon::SIZE_SMALL));
        $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT);
    }

    protected function createShortcutButton(): void
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();

        // Shortcut
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setModuleName('web_PforumAdministration')
            ->setGetVariables(['route', 'module', 'id'])
            ->setDisplayName('Shortcut');
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

    public function indexAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    public function listHiddenTopicsAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->view->assign('topics', $this->topicRepository->findAllHidden()->toArray());
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    public function listHiddenPostsAction(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->view->assign('posts', $this->postRepository->findAllHidden()->toArray());
        $moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * @param Topic $record
     */
    public function activateTopicAction(Topic $record): void
    {
        $record->setHidden(false);
        $this->topicRepository->update($record);
        $this->addFlashMessage(
            'Topic "' . $record->getTitle() . '" was activated.',
            'Topic activated',
            ContextualFeedbackSeverity::INFO
        );
        $this->redirect('listHiddenTopics');
    }

    /**
     * @param Post $record
     */
    public function activatePostAction(Post $record): void
    {
        $record->setHidden(false);
        $this->postRepository->update($record);
        $this->addFlashMessage(
            'Post "' . $record->getTitle() . '" was activated.',
            'Post activated',
            ContextualFeedbackSeverity::INFO
        );
        $this->redirect('listHiddenPosts');
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
