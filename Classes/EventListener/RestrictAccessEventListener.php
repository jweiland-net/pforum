<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\EventListener;

use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Traits\IsValidEventListenerRequestTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Restrict access to certain controller actions if logged-in user tries to access other user's records.
 */
#[AsEventListener(
    identifier: 'pforum/restrictAccess',
)]
final readonly class RestrictAccessEventListener
{
    use IsValidEventListenerRequestTrait;

    private const ALLOWED_CONTROLLER_ACTIONS = [
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

    public function __construct(
        private FlashMessageService $flashMessageService,
        private TopicRepository $topicRepository,
        private PostRepository $postRepository,
        private ExtensionService $extensionService,
        private AnonymousAccessTokenService $anonymousAccessTokenService,
    ) {}

    public function __invoke(PreProcessControllerActionEvent $controllerActionEvent): void
    {
        if (!$this->isValidRequest($controllerActionEvent)) {
            return;
        }

        if ($this->isAccessAllowed($controllerActionEvent)) {
            return;
        }

        $controllerActionEvent->setRequest(
            $controllerActionEvent->getRequest()->withControllerActionName('error'),
        );

        $controllerActionEvent->setArguments(
            GeneralUtility::makeInstance(Arguments::class),
        );
    }

    private function isAccessAllowed(PreProcessControllerActionEvent $controllerActionEvent): bool
    {
        $request = $controllerActionEvent->getRequest();
        $controllerName = $controllerActionEvent->getControllerName();
        $isAnonymousMode = (int)($controllerActionEvent->getSettings()['auth'] ?? 0) === 1;

        if ($controllerName === 'Topic') {
            return $this->isTopicAccessAllowed($isAnonymousMode, $request);
        }

        if ($controllerName === 'Post') {
            return $this->isPostAccessAllowed($isAnonymousMode, $request);
        }

        return true;
    }

    private function isTopicAccessAllowed(bool $isAnonymousMode, RequestInterface $request): bool
    {
        if (!$request->hasArgument('topic')) {
            return true;
        }

        $topicUid = $this->extractUid($request->getArgument('topic'));

        if ($topicUid <= 0) {
            return true;
        }

        if ($isAnonymousMode) {
            if (!$this->isAnonymousTokenValid('Topic', $topicUid, $request)) {
                $this->addFlashMessage(
                    LocalizationUtility::translate('unauthorizedUser', 'pforum'),
                    $request,
                );

                return false;
            }

            return true;
        }

        if (
            ($topic = $this->topicRepository->findHiddenObject($topicUid))
            && $topic->getHasValidUser() === false
        ) {
            $this->addFlashMessage(
                LocalizationUtility::translate('unauthorizedUser', 'pforum'),
                $request,
            );

            return false;
        }

        return true;
    }

    private function isPostAccessAllowed(bool $isAnonymousMode, RequestInterface $request): bool
    {
        if (!$request->hasArgument('post')) {
            return true;
        }

        $postUid = $this->extractUid($request->getArgument('post'));

        if ($postUid <= 0) {
            return true;
        }

        if ($isAnonymousMode) {
            if (!$this->isAnonymousTokenValid('Post', $postUid, $request)) {
                $this->addFlashMessage(
                    LocalizationUtility::translate('unauthorizedUser', 'pforum'),
                    $request,
                );

                return false;
            }

            return true;
        }

        if (
            ($post = $this->postRepository->findHiddenObject($postUid))
            && $post->getHasValidUser() === false
        ) {
            $this->addFlashMessage(
                LocalizationUtility::translate('unauthorizedUser', 'pforum'),
                $request,
            );

            return false;
        }

        return true;
    }

    private function isAnonymousTokenValid(string $type, int $uid, RequestInterface $request): bool
    {
        $token = $request->hasArgument('token') ? (string)$request->getArgument('token') : '';

        return $this->anonymousAccessTokenService->isTokenValid($type, $uid, $token);
    }

    private function extractUid(mixed $argument): int
    {
        return is_array($argument)
            ? (int)($argument['__identity'] ?? 0)
            : (int)$argument;
    }

    private function addFlashMessage(string $messageBody, RequestInterface $request): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageBody,
            '',
            ContextualFeedbackSeverity::ERROR,
            true,
        );

        $this->getFlashMessageQueue($request)->enqueue($flashMessage);
    }

    private function getFlashMessageQueue(
        RequestInterface $request,
    ): FlashMessageQueue {
        $pluginNamespace = $this->extensionService->getPluginNamespace(
            $request->getControllerExtensionName(),
            $request->getPluginName(),
        );

        $identifier = 'extbase.flashmessages.' . $pluginNamespace;

        return $this->flashMessageService->getMessageQueueByIdentifier($identifier);
    }
}
