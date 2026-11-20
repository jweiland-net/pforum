<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Controller;

use JWeiland\Pforum\Configuration\ForumSettings;
use JWeiland\Pforum\Domain\Model\FrontendUser;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Domain\Repository\ForumRepository;
use JWeiland\Pforum\Domain\Repository\FrontendUserRepository;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Event\PostProcessFluidVariablesEvent;
use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\Mail\ManageRecordMail;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Service\RecordAccessService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Abstract class with useful methods for all other extending classes
 */
class AbstractController extends ActionController
{
    public function __construct(
        protected readonly Session $session,
        protected readonly ForumRepository $forumRepository,
        protected readonly TopicRepository $topicRepository,
        protected readonly PostRepository $postRepository,
        protected readonly FrontendUserRepository $frontendUserRepository,
        protected readonly PersistenceManager $persistenceManager,
        protected readonly RecordAccessService $recordAccessService,
        protected readonly AnonymousAccessTokenService $anonymousAccessTokenService,
        protected readonly ManageRecordMail $manageRecordMail,
    ) {}

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
        $tsSettings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'pforum',
            'doNotLoadFlexFormSettings',
        );
        $mergedSettings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
        );

        foreach ($mergedSettings as $key => $value) {
            if (!is_array($value) && empty($value)) {
                $mergedSettings[$key] = $tsSettings[$key];
            }
        }

        $this->settings = $mergedSettings;
        $this->arguments = GeneralUtility::makeInstance(Arguments::class);
    }

    public function initializeAction(): void
    {
        $this->checkForMisconfiguration();
    }

    protected function getForumSettings(): ForumSettings
    {
        return ForumSettings::fromArray($this->settings);
    }

    /**
     * If there is a misconfiguration in TS this will throw an Exception.
     */
    protected function checkForMisconfiguration(): void
    {
        $forumSettings = $this->getForumSettings();
        if ($forumSettings->hasUnreachableHiddenRecords(RecordType::Topic)) {
            throw new \RuntimeException(
                "You can't hide topics at creation, deactivate admin activation and mark email as NOT mandatory."
                . 'This would produce hidden records which will never be visible',
                1378371532,
            );
        }

        if ($forumSettings->hasUnreachableHiddenRecords(RecordType::Post)) {
            throw new \RuntimeException(
                "You can't hide posts at creation, deactivate admin activation and mark email "
                . 'as NOT mandatory. This would produce hidden records which will never be visible',
                1378371541,
            );
        }
    }

    /**
     * @param array<string, mixed> $variables
     */
    protected function postProcessAndAssignFluidVariables(array $variables = []): void
    {
        /** @var PostProcessFluidVariablesEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new PostProcessFluidVariablesEvent(
                $this->request,
                $this->settings,
                $variables,
            ),
        );

        $this->view->assignMultiple($event->getFluidVariables());
    }

    protected function preProcessControllerAction(): void
    {
        $actionEvent = new PreProcessControllerActionEvent(
            $this->request,
            $this->arguments,
            $this->settings,
        );
        $this->eventDispatcher->dispatch($actionEvent);

        $this->request = $actionEvent->getRequest();
        $this->arguments = $actionEvent->getArguments();
        $this->actionMethodName = $this->resolveActionMethodName();
    }

    /**
     * Frontend users have to be logged in (and member of the user group, if
     * configured) to create records
     *
     * @throws PropagateResponseException
     */
    protected function denyCreationUnlessAllowed(): void
    {
        $denialReason = $this->recordAccessService->getCreationDenialReason($this->getForumSettings());
        if ($denialReason !== '') {
            throw new PropagateResponseException($this->redirectWithMessage($denialReason, ContextualFeedbackSeverity::ERROR), 1763890803);
        }
    }

    /**
     * Hidden records can not be found by the property mapper of Extbase. So
     * the record of the request is registered in the persistence session.
     * The access is checked before the request is mapped to the record, so
     * a denied record is never changed.
     *
     * @throws PropagateResponseException
     */
    protected function registerRecordOfRequestAndDenyEditUnlessAllowed(RecordType $recordType): void
    {
        $argument = $this->request->hasArgument($recordType->getArgumentName())
            ? $this->request->getArgument($recordType->getArgumentName())
            : 0;
        $record = $this->findRecord($recordType, (int)(is_array($argument) ? ($argument['__identity'] ?? 0) : $argument));
        $token = $this->request->hasArgument('token') ? (string)$this->request->getArgument('token') : '';

        $denialReason = $this->recordAccessService->getEditDenialReason($record, $token, $this->getForumSettings());
        if ($record === null || $denialReason !== '') {
            throw new PropagateResponseException($this->redirectWithMessage($denialReason, ContextualFeedbackSeverity::ERROR), 1763890804);
        }

        $this->session->registerObject($record, (string)$record->getUid());
    }

    /**
     * Stores a new record. A preview hides the record and shows it in the
     * edit form. Otherwise, the record is published.
     */
    protected function processCreation(Topic|Post $record): ResponseInterface
    {
        $recordType = $record instanceof Topic ? RecordType::Topic : RecordType::Post;
        $frontendUser = $this->frontendUserRepository->findByIdentifier($this->recordAccessService->getFrontendUserUid());
        if ($this->getForumSettings()->isFrontendUserMode() && $frontendUser instanceof FrontendUser) {
            $record->setFrontendUser($frontendUser);
        }

        if ($this->request->hasArgument('preview')) {
            $record->setHidden(true);
            $this->persistenceManager->persistAll();

            return $this->redirectToPreview($record, true);
        }

        $record->setHidden($this->getForumSettings()->isHiddenAtCreation($recordType));
        $this->persistenceManager->persistAll();

        return $this->publish($record);
    }

    /**
     * Stores an edited record. The visibility of the record is kept, except
     * for new records, which are published after their preview.
     */
    protected function processUpdate(Topic|Post $record, bool $isNew, string $token): ResponseInterface
    {
        $recordType = $record instanceof Topic ? RecordType::Topic : RecordType::Post;
        if ($this->request->hasArgument('preview')) {
            $this->persistenceManager->update($record);

            return $this->redirectToPreview($record, $isNew, $token);
        }

        if (!$isNew) {
            $this->persistenceManager->update($record);

            return $this->redirectWithMessage($recordType->value . 'Updated', ContextualFeedbackSeverity::OK, $record);
        }

        $record->setHidden($this->getForumSettings()->isHiddenAtCreation($recordType));
        $this->persistenceManager->update($record);
        $this->persistenceManager->persistAll();

        return $this->publish($record);
    }

    /**
     * The author of a new record gets a mail to edit, delete and maybe
     * activate the record
     */
    protected function publish(Topic|Post $record): ResponseInterface
    {
        if ($this->getForumSettings()->isMailToUserEnabled()) {
            $this->manageRecordMail->send($record, $this->settings, $this->request);
        }

        $this->afterPublishing($record);

        return $this->redirectWithMessage($this->getCreationMessageKey($record), ContextualFeedbackSeverity::OK, $record);
    }

    /**
     * Called after a new record was published or a hidden record was activated
     */
    protected function afterPublishing(Topic|Post $record): void {}

    protected function redirectToPreview(Topic|Post $record, bool $isNew, string $token = ''): ResponseInterface
    {
        $recordType = $record instanceof Topic ? RecordType::Topic : RecordType::Post;
        if ($token === '' && $this->getForumSettings()->isAnonymousMode()) {
            $token = $this->anonymousAccessTokenService->generateToken($recordType->getAccessTokenType(), (int)$record->getUid());
        }

        return $this->redirect('edit', $recordType->getControllerName(), null, array_filter([
            $recordType->getArgumentName() => $record->getUid(),
            'isPreview' => true,
            'isNew' => $isNew,
            'token' => $token,
        ]));
    }

    protected function getCreationMessageKey(Topic|Post $record): string
    {
        $recordType = $record instanceof Topic ? RecordType::Topic : RecordType::Post;

        return match (true) {
            !$record->getHidden() => $recordType->value . 'Created',
            $this->getForumSettings()->isActivatedByAdmin($recordType) => 'hidden' . $recordType->getControllerName() . 'CreatedAndActivateByAdmin',
            default => 'hidden' . $recordType->getControllerName() . 'CreatedAndActivateByUser',
        };
    }

    /**
     * Shows a confirmation for GET requests (e.g. the link of the mail) and
     * deletes the record with POST requests only.
     */
    protected function processDeletion(RecordType $recordType, int $uid, string $token): ResponseInterface
    {
        $record = $this->findRecord($recordType, $uid);
        $denialReason = $this->recordAccessService->getEditDenialReason($record, $token, $this->getForumSettings());
        if ($record === null || $denialReason !== '') {
            return $this->redirectWithMessage(RecordAccessService::REASON_UNAUTHORIZED, ContextualFeedbackSeverity::ERROR);
        }

        if ($this->request->getMethod() !== 'POST') {
            $this->postProcessAndAssignFluidVariables([$recordType->getArgumentName() => $record, 'token' => $token]);

            return $this->htmlResponse();
        }

        $record instanceof Topic ? $this->topicRepository->remove($record) : $this->postRepository->remove($record);

        return $this->redirectWithMessage($recordType->value . 'Deleted', ContextualFeedbackSeverity::OK, $record);
    }

    /**
     * Mail security scanners and link previews open the links of a mail
     * automatically. So the activation link of the mail shows a summary of
     * the record only. Its form activates the record via POST.
     */
    protected function renderActivationConfirmation(RecordType $recordType, int $uid, string $token): ResponseInterface
    {
        $record = $this->findRecord($recordType, $uid);
        $errorResponse = $this->getActivationErrorResponse($record, $token);
        if ($errorResponse instanceof ResponseInterface) {
            return $errorResponse;
        }

        $this->postProcessAndAssignFluidVariables([$recordType->getArgumentName() => $record, 'token' => $token]);

        return $this->htmlResponse();
    }

    /**
     * The record is activated via POST only. GET requests are redirected to
     * the confirmation page.
     */
    protected function processActivation(RecordType $recordType, int $uid, string $token): ResponseInterface
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->redirect('confirmActivation', null, null, [$recordType->getArgumentName() => $uid, 'token' => $token]);
        }

        $record = $this->findRecord($recordType, $uid);
        $errorResponse = $this->getActivationErrorResponse($record, $token);
        if ($errorResponse instanceof ResponseInterface || $record === null) {
            return $errorResponse ?? $this->redirect('list', 'Forum');
        }

        $record->setHidden(false);
        $this->persistenceManager->update($record);
        $this->afterPublishing($record);

        return $this->redirectWithMessage($recordType->value . 'Activated', ContextualFeedbackSeverity::OK, $record);
    }

    /**
     * Without a valid token or admin rights the record can not be
     * activated. A visible record was activated before, e.g. as the link
     * was used already.
     */
    protected function getActivationErrorResponse(Topic|Post|null $record, string $token): ?ResponseInterface
    {
        return match (true) {
            $record === null,
            !$this->recordAccessService->isActivationAllowed($record, $token, $this->getForumSettings())
                => $this->redirectWithMessage('activation.invalidToken', ContextualFeedbackSeverity::ERROR),
            !$record->getHidden() => $this->redirectWithMessage('activation.alreadyActivated', ContextualFeedbackSeverity::INFO, $record),
            default => null,
        };
    }

    /**
     * Topics and posts are hidden in frontend until they are activated
     */
    protected function findRecord(RecordType $recordType, int $uid): Topic|Post|null
    {
        return match ($recordType) {
            RecordType::Topic => $this->topicRepository->findHiddenObject($uid),
            RecordType::Post => $this->postRepository->findHiddenObject($uid),
        };
    }

    /**
     * Shows the forum of a topic or the topic of a post. Without a record
     * the list of forums is shown.
     */
    protected function redirectWithMessage(
        string $labelKey,
        ContextualFeedbackSeverity $severity,
        Topic|Post|null $record = null,
    ): ResponseInterface {
        $this->addFlashMessage((string)LocalizationUtility::translate($labelKey, 'pforum'), '', $severity);

        $parent = match (true) {
            $record instanceof Topic => $record->getForum(),
            $record instanceof Post => $record->getTopic(),
            default => null,
        };
        if ($parent === null) {
            return $this->redirect('list', 'Forum');
        }

        $parentArgumentName = $parent instanceof Topic ? 'topic' : 'forum';

        return $this->redirect('show', ucfirst($parentArgumentName), null, [$parentArgumentName => $parent->getUid()]);
    }
}
