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
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\ForumRepository;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Event\PostProcessFluidVariablesEvent;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Service\RecordAccessService;
use JWeiland\Pforum\Service\RecordService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Abstract class with useful methods for all other extending classes
 */
class AbstractController extends ActionController
{
    public function __construct(
        protected readonly ForumRepository $forumRepository,
        protected readonly TopicRepository $topicRepository,
        protected readonly PostRepository $postRepository,
        protected readonly RecordAccessService $recordAccessService,
        protected readonly RecordService $recordService,
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

    /**
     * Renders the form of a new topic or post. The form is built by the
     * ArrayFormFactory of pforum and checks the access on its own, too.
     *
     * @param array<string, mixed> $variables
     */
    protected function renderNewForm(array $variables): ResponseInterface
    {
        $this->postProcessAndAssignFluidVariables(array_replace($variables, [
            'denialReason' => $this->recordAccessService->getCreationDenialReason($this->getForumSettings()),
        ]));

        return $this->htmlResponse();
    }

    /**
     * Renders the form to edit a topic or post. Anonymous users need the
     * token of their mail.
     */
    protected function renderEditForm(RecordType $recordType, int $uid, string $token): ResponseInterface
    {
        $this->postProcessAndAssignFluidVariables([
            $recordType->getArgumentName() => $this->findRecordObject($recordType, $uid),
            'denialReason' => $this->recordAccessService->getEditDenialReason(
                $recordType,
                $uid,
                $token,
                $this->getForumSettings(),
            ),
        ]);

        return $this->htmlResponse();
    }

    /**
     * Shows a confirmation for GET requests (e.g. the link of the mail) and
     * deletes the record with POST requests only.
     */
    protected function processDeletion(RecordType $recordType, int $uid, string $token): ResponseInterface
    {
        $record = $this->findRecordObject($recordType, $uid);
        if ($record === null || $this->recordAccessService->getEditDenialReason($recordType, $uid, $token, $this->getForumSettings()) !== '') {
            return $this->redirectWithMessage(RecordAccessService::REASON_UNAUTHORIZED, ContextualFeedbackSeverity::ERROR);
        }

        if ($this->request->getMethod() !== 'POST') {
            $this->postProcessAndAssignFluidVariables([
                $recordType->getArgumentName() => $record,
                'token' => $token,
            ]);

            return $this->htmlResponse();
        }

        $this->recordService->delete($recordType, $uid);

        return $this->redirectWithMessage($recordType->value . 'Deleted', ContextualFeedbackSeverity::OK, $record);
    }

    /**
     * Mail security scanners and link previews open the links of a mail
     * automatically. So the activation link of the mail shows a summary of
     * the record only. Its form activates the record via POST.
     */
    protected function renderActivationConfirmation(RecordType $recordType, int $uid, string $token): ResponseInterface
    {
        $record = $this->findRecordObject($recordType, $uid);
        $errorResponse = $this->getActivationErrorResponse($recordType, $record, $token);
        if ($errorResponse instanceof ResponseInterface) {
            return $errorResponse;
        }

        $this->postProcessAndAssignFluidVariables([
            $recordType->getArgumentName() => $record,
            'token' => $token,
        ]);

        return $this->htmlResponse();
    }

    /**
     * The record is activated via POST only. GET requests are redirected to
     * the confirmation page.
     */
    protected function processActivation(RecordType $recordType, int $uid, string $token): ResponseInterface
    {
        if ($this->request->getMethod() !== 'POST') {
            return $this->redirect('confirmActivation', null, null, [
                $recordType->getArgumentName() => $uid,
                'token' => $token,
            ]);
        }

        $record = $this->findRecordObject($recordType, $uid);
        $errorResponse = $this->getActivationErrorResponse($recordType, $record, $token);
        if ($errorResponse instanceof ResponseInterface) {
            return $errorResponse;
        }

        $this->recordService->activate($recordType, $uid);
        $this->afterActivation($recordType, $uid);

        return $this->redirectWithMessage($recordType->value . 'Activated', ContextualFeedbackSeverity::OK, $record);
    }

    /**
     * Called after a topic or post was activated
     */
    protected function afterActivation(RecordType $recordType, int $uid): void {}

    /**
     * Without a valid token or admin rights the record can not be
     * activated. A visible record was activated before, e.g. as the link
     * was used already.
     */
    protected function getActivationErrorResponse(RecordType $recordType, Topic|Post|null $record, string $token): ?ResponseInterface
    {
        $isAllowed = $record !== null && $this->recordAccessService->isActivationAllowed(
            $recordType,
            (int)$record->getUid(),
            $token,
            $this->getForumSettings(),
        );

        return match (true) {
            !$isAllowed => $this->redirectWithMessage('activation.invalidToken', ContextualFeedbackSeverity::ERROR),
            !$record->getHidden() => $this->redirectWithMessage('activation.alreadyActivated', ContextualFeedbackSeverity::INFO, $record),
            default => null,
        };
    }

    /**
     * Topics and posts are hidden in frontend until they are activated
     */
    protected function findRecordObject(RecordType $recordType, int $uid): Topic|Post|null
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
