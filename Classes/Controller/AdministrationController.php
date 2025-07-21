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
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3Fluid\Fluid\View\TemplateAwareViewInterface;

/**
 * Main controller to list and show postings/questions
 */
class AdministrationController extends ActionController
{
    private ModuleTemplate $moduleTemplate;

    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly PostRepository $postRepository,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly IconFactory $iconFactory,
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
        if (!in_array($this->actionMethodName, ['indexAction', 'listHiddenTopicsAction', 'listHiddenPostsAction'], true)) {
            return;
        }
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $uriBuilder = $this->uriBuilder;
        $button = $buttonBar->makeLinkButton()
            ->setHref($uriBuilder->reset()->uriFor('index', [], 'Administration'))
            ->setTitle('Back')
            ->setIcon($this->iconFactory->getIcon('actions-view-go-back', Icon::SIZE_SMALL));
        $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT);
    }

    protected function createShortcutButton(): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $pageId = (int)($this->request->getQueryParams()['id'] ?? 0);

        // Shortcut
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier('pforumBackendModule')
            ->setDisplayName('Shortcut')
            ->setArguments([
                'id' => $pageId,
            ]);
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

    public function indexAction(): ResponseInterface
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function listHiddenTopicsAction(): ResponseInterface
    {
        $this->view->assign('topics', $this->topicRepository->findAllHidden()->toArray());
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function listHiddenPostsAction(): ResponseInterface
    {
        $this->view->assign('posts', $this->postRepository->findAllHidden()->toArray());
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->moduleTemplate->setContent($this->view->render());

        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function activateTopicAction(Topic $record): ResponseInterface
    {
        $record->setHidden(false);
        $this->topicRepository->update($record);
        $this->addFlashMessage(
            'Topic "' . $record->getTitle() . '" was activated.',
            'Topic activated',
            ContextualFeedbackSeverity::INFO,
        );

        return $this->redirect('listHiddenTopics');
    }

    public function activatePostAction(Post $record): ResponseInterface
    {
        $record->setHidden(false);
        $this->postRepository->update($record);
        $this->addFlashMessage(
            'Post "' . $record->getTitle() . '" was activated.',
            'Post activated',
            ContextualFeedbackSeverity::INFO,
        );

        return $this->redirect('listHiddenPosts');
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
