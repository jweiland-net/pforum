<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Controller;

use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
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
            $this->createDocHeaderActionButtons();
            $this->createShortcutButton();
        }
    }

    protected function createDocHeaderActionButtons(): void
    {
        // Initializind Backend Module Template
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        // Back Button Rendering for the Backend Module
        $uriBuilder = $this->uriBuilder;
        $button = $buttonBar->makeLinkButton()
            ->setHref($uriBuilder->reset()->uriFor('index', [], 'Administration'))
            ->setTitle('Back')
            ->setIcon($this->iconFactory->getIcon('actions-view-go-back', IconSize::SMALL));
        $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT);
    }

    protected function createShortcutButton(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $shortcutButton = $buttonBar->makeShortcutButton()
            ->setRouteIdentifier($this->request->getAttribute('module')->getIdentifier())
            ->setDisplayName('Shortcut Button');
        $buttonBar->addButton($shortcutButton, ButtonBar::BUTTON_POSITION_RIGHT);
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

    public function activateTopicAction(): ResponseInterface
    {
        $topicRecord = $this->topicRepository->findHiddenObject($this->request->getArgument('record'));
        $topicRecord->setHidden(false);
        $this->topicRepository->update($topicRecord);
        $this->addFlashMessage(
            'Topic "' . $topicRecord->getTitle() . '" was activated.',
            'Topic activated',
            ContextualFeedbackSeverity::INFO,
            true,
        );

        return $this->redirect('listHiddenTopics');
    }

    public function activatePostAction(): ResponseInterface
    {
        $postRecord = $this->postRepository->findHiddenObject($this->request->getArgument('record'));
        $postRecord->setHidden(false);
        $this->postRepository->update($postRecord);
        $this->addFlashMessage(
            'Post "' . $postRecord->getTitle() . '" was activated.',
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
