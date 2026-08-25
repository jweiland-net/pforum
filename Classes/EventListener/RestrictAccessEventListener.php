<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\EventListener;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Restrict access to certain controller actions if logged-in user tries to access other user's records.
 */
class RestrictAccessEventListener extends AbstractControllerEventListener
{
    protected $allowedControllerActions = [
        'Topic' => [
            'edit',
            'update',
            'delete',
            'activate',
        ],
        'Post' => [
            'edit',
            'update',
            'delete',
            'activate',
        ],
    ];

    /**
     * @var FlashMessageService
     */
    protected $flashMessageService;

    /**
     * @var TopicRepository
     */
    protected $topicRepository;

    /**
     * @var PostRepository
     */
    protected $postRepository;

    /**
     * @var ExtensionService
     */
    protected $extensionService;

    /**
     * @var AnonymousAccessTokenService
     */
    protected $anonymousAccessTokenService;

    /**
     * @var Request|null
     */
    protected $request;

    public function __construct(
        FlashMessageService $flashMessageService,
        TopicRepository $topicRepository,
        PostRepository $postRepository,
        ExtensionService $extensionService,
        AnonymousAccessTokenService $anonymousAccessTokenService
    ) {
        $this->flashMessageService = $flashMessageService;
        $this->topicRepository = $topicRepository;
        $this->postRepository = $postRepository;
        $this->extensionService = $extensionService;
        $this->anonymousAccessTokenService = $anonymousAccessTokenService;
    }

    public function __invoke(PreProcessControllerActionEvent $controllerActionEvent): void
    {
        if (!$this->isValidRequest($controllerActionEvent)) {
            return;
        }

        $this->request = $controllerActionEvent->getRequest();

        if ($this->isAccessAllowed($controllerActionEvent)) {
            return;
        }

        // The extbase Request of this TYPO3 version is still mutable. Changing the controller
        // action name here is enough, ActionController re-resolves its action method name
        // afterwards (see AbstractController::preProcessControllerAction()).
        $this->request->setControllerActionName('error');

        $controllerActionEvent->setArguments(
            GeneralUtility::makeInstance(Arguments::class)
        );
    }

    private function isAccessAllowed(PreProcessControllerActionEvent $controllerActionEvent): bool
    {
        $request = $controllerActionEvent->getRequest();
        $controllerName = $controllerActionEvent->getControllerName();
        $isAnonymousMode = (int)($controllerActionEvent->getSettings()['auth'] ?? 0) === 1;

        if ($controllerName === 'Topic') {
            return $this->isTopicAccessAllowed($request, $isAnonymousMode);
        }

        if ($controllerName === 'Post') {
            return $this->isPostAccessAllowed($request, $isAnonymousMode);
        }

        return true;
    }

    private function isTopicAccessAllowed(Request $request, bool $isAnonymousMode): bool
    {
        if (!$request->hasArgument('topic')) {
            return true;
        }

        $topicUid = $this->extractUid($request->getArgument('topic'));

        if ($topicUid <= 0) {
            return true;
        }

        if ($isAnonymousMode) {
            if (!$this->isAnonymousTokenValid($request, 'Topic', $topicUid)) {
                $this->addFlashMessage(LocalizationUtility::translate('unauthorizedUser', 'pforum'));

                return false;
            }

            return true;
        }

        $topic = $this->topicRepository->findHiddenObject($topicUid);
        if (
            $topic instanceof Topic
            && $topic->getHasValidUser() === false
        ) {
            $this->addFlashMessage(LocalizationUtility::translate('unauthorizedUser', 'pforum'));

            return false;
        }

        return true;
    }

    private function isPostAccessAllowed(Request $request, bool $isAnonymousMode): bool
    {
        if (!$request->hasArgument('post')) {
            return true;
        }

        $postUid = $this->extractUid($request->getArgument('post'));

        if ($postUid <= 0) {
            return true;
        }

        if ($isAnonymousMode) {
            if (!$this->isAnonymousTokenValid($request, 'Post', $postUid)) {
                $this->addFlashMessage(LocalizationUtility::translate('unauthorizedUser', 'pforum'));

                return false;
            }

            return true;
        }

        $post = $this->postRepository->findHiddenObject($postUid);
        if (
            $post instanceof Post
            && $post->getHasValidUser() === false
        ) {
            $this->addFlashMessage(LocalizationUtility::translate('unauthorizedUser', 'pforum'));

            return false;
        }

        return true;
    }

    private function isAnonymousTokenValid(Request $request, string $type, int $uid): bool
    {
        $token = $request->hasArgument('token') ? (string)$request->getArgument('token') : '';

        return $this->anonymousAccessTokenService->isTokenValid($type, $uid, $token);
    }

    /**
     * @param mixed $argument
     */
    private function extractUid($argument): int
    {
        return is_array($argument)
            ? (int)($argument['__identity'] ?? 0)
            : (int)$argument;
    }

    private function addFlashMessage(string $messageBody): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageBody,
            '',
            AbstractMessage::ERROR,
            true
        );

        $this->getFlashMessageQueue()->enqueue($flashMessage);
    }

    private function getFlashMessageQueue(): FlashMessageQueue
    {
        $pluginNamespace = $this->extensionService->getPluginNamespace(
            $this->request->getControllerExtensionName(),
            $this->request->getPluginName()
        );

        return $this->flashMessageService->getMessageQueueByIdentifier('extbase.flashmessages.' . $pluginNamespace);
    }
}
