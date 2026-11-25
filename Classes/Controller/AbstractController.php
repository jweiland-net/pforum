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
use JWeiland\Pforum\Configuration\PluginSettingsResolver;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Domain\Repository\ForumRepository;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Event\PostProcessFluidVariablesEvent;
use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\Event\RecordPublishedEvent;
use JWeiland\Pforum\Mail\ManageRecordMail;
use JWeiland\Pforum\Service\RecordAccessService;
use JWeiland\Pforum\Service\RecordPersistenceService;
use JWeiland\Pforum\Service\RichTextDescriptionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * The request flow of the frontend plugin: create, edit, delete and
 * activate topics and posts. Persistence, access checks, mails and rich
 * text are delegated to services.
 */
abstract class AbstractController extends ActionController
{
    private const MESSAGE_MAIL_NOT_SENT = 'mailNotSent';

    private ?ForumSettings $forumSettings = null;

    public function __construct(
        protected readonly ForumRepository $forumRepository,
        protected readonly TopicRepository $topicRepository,
        protected readonly PostRepository $postRepository,
        protected readonly RecordAccessService $recordAccessService,
        protected readonly RecordPersistenceService $recordPersistenceService,
        protected readonly ManageRecordMail $manageRecordMail,
        protected readonly RichTextDescriptionService $richTextDescriptionService,
        protected readonly PluginSettingsResolver $pluginSettingsResolver,
        protected readonly LoggerInterface $logger,
    ) {}

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
        $this->settings = $this->pluginSettingsResolver->resolve($configurationManager);
        $this->arguments = GeneralUtility::makeInstance(Arguments::class);
    }

    /**
     * @throws \RuntimeException if TypoScript would produce records, which are hidden forever
     */
    public function initializeAction(): void
    {
        $this->getForumSettings()->assertConsistency();
    }

    /**
     * The validation errors are shown at the form fields. Do not add the
     * generic "An error occurred while trying to call ..." message of Extbase.
     */
    protected function getErrorFlashMessage(): bool|string
    {
        return false;
    }

    /**
     * The settings are built once per request
     */
    protected function getForumSettings(): ForumSettings
    {
        return $this->forumSettings ??= ForumSettings::fromArray($this->settings);
    }

    /**
     * @param array<string, mixed> $variables
     */
    protected function postProcessAndAssignFluidVariables(array $variables = []): void
    {
        /** @var PostProcessFluidVariablesEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new PostProcessFluidVariablesEvent($this->request, $this->settings, $variables),
        );

        $this->view->assignMultiple($event->getFluidVariables());
    }

    protected function preProcessControllerAction(): void
    {
        $actionEvent = new PreProcessControllerActionEvent($this->request, $this->arguments, $this->settings);
        $this->eventDispatcher->dispatch($actionEvent);

        $this->request = $actionEvent->getRequest();
        $this->arguments = $actionEvent->getArguments();
        $this->actionMethodName = $this->resolveActionMethodName();
    }

    /**
     * Assigns the variables of the form of a topic or post
     *
     * @param array<string, mixed> $variables
     */
    protected function renderForm(Topic|Post $record, array $variables): ResponseInterface
    {
        $this->postProcessAndAssignFluidVariables(array_replace(
            $variables,
            $this->richTextDescriptionService->getFormVariables($record, $this->settings, $this->request),
        ));

        return $this->htmlResponse();
    }

    /**
     * Variables of the list of topics or posts
     *
     * @return array{isCreationAllowed: bool, isAdmin: bool}
     */
    protected function getPermissionVariables(): array
    {
        return [
            'isCreationAllowed' => $this->recordAccessService->getCreationDenialReason($this->getForumSettings()) === '',
            'isAdmin' => $this->recordAccessService->isAdmin($this->getForumSettings()),
        ];
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
            $this->logDenial($denialReason);

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
        $uid = (int)(is_array($argument) ? ($argument['__identity'] ?? 0) : $argument);
        $record = $this->recordPersistenceService->find($recordType, $uid);

        $denialReason = $this->recordAccessService->getEditDenialReason($record, $this->getToken(), $this->getForumSettings());
        if ($denialReason === '' && is_array($argument) && !$this->isAnonymousUserOfRecord($record, $argument)) {
            $denialReason = RecordAccessService::REASON_UNAUTHORIZED;
        }

        if ($record === null || $denialReason !== '') {
            $this->logDenial($denialReason ?: RecordAccessService::REASON_UNAUTHORIZED, $recordType, $uid);

            throw new PropagateResponseException(
                $this->redirectWithMessage($denialReason ?: RecordAccessService::REASON_UNAUTHORIZED, ContextualFeedbackSeverity::ERROR),
                1763890804,
            );
        }

        $this->recordPersistenceService->registerForPropertyMapping($record);
    }

    /**
     * Stores a new record. A preview hides the record and shows it in the
     * edit form. Otherwise, the record is published.
     */
    protected function processCreation(Topic|Post $record): ResponseInterface
    {
        $recordType = RecordType::fromRecord($record);
        $this->richTextDescriptionService->prepareForPersistence($record, $this->settings);
        if ($this->getForumSettings()->isFrontendUserMode()) {
            $this->recordPersistenceService->assignFrontendUser($record, $this->recordAccessService->getFrontendUserUid());
        }

        $isPreview = $this->request->hasArgument('preview');
        $record->setHidden($isPreview || $this->getForumSettings()->isHiddenAtCreation($recordType));
        $this->recordPersistenceService->persist($record, true);

        return $isPreview ? $this->redirectToPreview($record, true) : $this->publish($record);
    }

    /**
     * Stores an edited record. The visibility of the record is kept, except
     * for new records, which are published after their preview.
     */
    protected function processUpdate(Topic|Post $record, bool $isNew, string $token): ResponseInterface
    {
        $recordType = RecordType::fromRecord($record);
        $this->richTextDescriptionService->prepareForPersistence($record, $this->settings);
        if ($this->request->hasArgument('preview')) {
            $this->recordPersistenceService->persist($record, $isNew);

            return $this->redirectToPreview($record, $isNew, $token);
        }

        if (!$isNew) {
            $this->recordPersistenceService->persist($record, false);

            return $this->redirectWithMessage($recordType->getMessageKey('Updated'), ContextualFeedbackSeverity::OK, $record);
        }

        $record->setHidden($this->getForumSettings()->isHiddenAtCreation($recordType));
        $this->recordPersistenceService->persist($record, true);

        return $this->publish($record);
    }

    /**
     * The author of a new record gets a mail to edit, delete and maybe
     * activate the record
     */
    protected function publish(Topic|Post $record): ResponseInterface
    {
        if ($this->getForumSettings()->isMailToUserEnabled()
            && !$this->manageRecordMail->send($record, $this->settings, $this->request)
        ) {
            $this->addTranslatedFlashMessage(self::MESSAGE_MAIL_NOT_SENT, ContextualFeedbackSeverity::WARNING);
        }

        if (!$record->getHidden()) {
            $this->dispatchRecordPublishedEvent($record);
        }

        return $this->redirectWithMessage(
            $this->getForumSettings()->getCreationMessageKey(RecordType::fromRecord($record), $record->getHidden()),
            ContextualFeedbackSeverity::OK,
            $record,
        );
    }

    protected function redirectToPreview(Topic|Post $record, bool $isNew, string $token = ''): ResponseInterface
    {
        $recordType = RecordType::fromRecord($record);
        if ($token === '' && $this->getForumSettings()->isAnonymousMode()) {
            $token = $this->recordAccessService->generateAccessToken($record);
        }

        return $this->redirect('edit', $recordType->getControllerName(), null, array_filter([
            $recordType->getArgumentName() => $record->getUid(),
            'isPreview' => true,
            'isNew' => $isNew,
            'token' => $token,
        ]));
    }

    /**
     * Shows a confirmation for GET requests (e.g. the link of the mail) and
     * deletes the record with POST requests only.
     */
    protected function processDeletion(RecordType $recordType, int $uid, string $token): ResponseInterface
    {
        $record = $this->recordPersistenceService->find($recordType, $uid);
        $denialReason = $this->recordAccessService->getEditDenialReason($record, $token, $this->getForumSettings());
        if ($record === null || $denialReason !== '') {
            $this->logDenial(RecordAccessService::REASON_UNAUTHORIZED, $recordType, $uid);

            return $this->redirectWithMessage(RecordAccessService::REASON_UNAUTHORIZED, ContextualFeedbackSeverity::ERROR);
        }

        if ($this->request->getMethod() !== 'POST') {
            $this->postProcessAndAssignFluidVariables([$recordType->getArgumentName() => $record, 'token' => $token]);

            return $this->htmlResponse();
        }

        $this->recordPersistenceService->remove($record);

        return $this->redirectWithMessage($recordType->getMessageKey('Deleted'), ContextualFeedbackSeverity::OK, $record);
    }

    /**
     * Mail security scanners and link previews open the links of a mail
     * automatically. So the activation link of the mail shows a summary of
     * the record only. Its form activates the record via POST.
     */
    protected function renderActivationConfirmation(RecordType $recordType, int $uid, string $token): ResponseInterface
    {
        $record = $this->recordPersistenceService->find($recordType, $uid);
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

        $record = $this->recordPersistenceService->find($recordType, $uid);
        $errorResponse = $this->getActivationErrorResponse($record, $token);
        if ($errorResponse instanceof ResponseInterface || $record === null) {
            return $errorResponse ?? $this->redirect('list', 'Forum');
        }

        $this->recordPersistenceService->activate($record);
        $this->dispatchRecordPublishedEvent($record);

        return $this->redirectWithMessage($recordType->getMessageKey('Activated'), ContextualFeedbackSeverity::OK, $record);
    }

    /**
     * Without a valid token or admin rights the record can not be
     * activated. A visible record was activated before, e.g. as the link
     * was used already.
     */
    protected function getActivationErrorResponse(Topic|Post|null $record, string $token): ?ResponseInterface
    {
        if ($record === null || !$this->recordAccessService->isActivationAllowed($record, $token, $this->getForumSettings())) {
            $this->logDenial('activation.invalidToken', $record === null ? null : RecordType::fromRecord($record), (int)$record?->getUid());

            return $this->redirectWithMessage('activation.invalidToken', ContextualFeedbackSeverity::ERROR);
        }

        return $record->getHidden()
            ? null
            : $this->redirectWithMessage('activation.alreadyActivated', ContextualFeedbackSeverity::INFO, $record);
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
        $this->addTranslatedFlashMessage($labelKey, $severity);

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

    protected function addTranslatedFlashMessage(string $labelKey, ContextualFeedbackSeverity $severity): void
    {
        $this->addFlashMessage((string)LocalizationUtility::translate($labelKey, 'pforum'), '', $severity);
    }

    /**
     * Observers (e.g. the mail to the author of a topic about a new post)
     * listen to RecordPublishedEvent
     */
    private function dispatchRecordPublishedEvent(Topic|Post $record): void
    {
        $this->eventDispatcher->dispatch(new RecordPublishedEvent($record, $this->settings, $this->request));
    }

    private function getToken(): string
    {
        $token = $this->request->hasArgument('token') ? $this->request->getArgument('token') : '';

        return is_string($token) ? $token : '';
    }

    /**
     * The edit form submits the identity of the pforum user (anonymous user)
     * of the record. Another identity would overwrite the name and email of
     * the author of a foreign record.
     *
     * @param array<string, mixed> $argument
     */
    private function isAnonymousUserOfRecord(Topic|Post|null $record, array $argument): bool
    {
        $anonymousUser = $argument['anonymousUser'] ?? null;
        if (!is_array($anonymousUser) || !array_key_exists('__identity', $anonymousUser)) {
            return true;
        }

        return $record !== null
            && (int)$anonymousUser['__identity'] === (int)$record->getAnonymousUser()?->getUid();
    }

    private function logDenial(string $reason, ?RecordType $recordType = null, int $uid = 0): void
    {
        $this->logger->warning('Access denied to action "{action}": {reason}', [
            'action' => $this->request->getControllerName() . '::' . $this->request->getControllerActionName(),
            'reason' => $reason,
            'type' => $recordType?->getControllerName() ?? '',
            'uid' => $uid,
            'frontendUser' => $this->recordAccessService->getFrontendUserUid(),
        ]);
    }
}
