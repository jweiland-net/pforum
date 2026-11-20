<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Controller;

use JWeiland\Pforum\Domain\Model\Forum;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Annotation as Extbase;

/**
 * Controller to show, create, edit, activate and delete topics
 */
class TopicController extends AbstractController
{
    public function showAction(Topic $topic): ResponseInterface
    {
        $isAdmin = $this->recordAccessService->isAdmin($this->getForumSettings());
        $posts = $this->postRepository->findByTopic($topic);
        if ($isAdmin) {
            $posts->getQuery()
                ->getQuerySettings()
                ->setIgnoreEnableFields(true)
                ->setEnableFieldsToBeIgnored(['disabled']);
        }

        $this->postProcessAndAssignFluidVariables([
            'topic' => $topic,
            'posts' => $posts,
            'isCreationAllowed' => $this->recordAccessService->getCreationDenialReason($this->getForumSettings()) === '',
            'isAdmin' => $isAdmin,
        ]);

        return $this->htmlResponse();
    }

    public function initializeNewAction(): void
    {
        $this->denyCreationUnlessAllowed();
    }

    #[Extbase\IgnoreValidation(['value' => 'topic'])]
    public function newAction(Forum $forum, ?Topic $topic = null): ResponseInterface
    {
        $topic ??= new Topic();

        return $this->renderForm($topic, [
            'forum' => $forum,
            'topic' => $topic,
        ]);
    }

    public function initializeCreateAction(): void
    {
        $this->denyCreationUnlessAllowed();
        $this->preProcessControllerAction();
    }

    public function createAction(Forum $forum, Topic $topic): ResponseInterface
    {
        $topic->setForum($forum);
        $forum->addTopic($topic);
        $this->forumRepository->update($forum);

        return $this->processCreation($topic);
    }

    public function initializeEditAction(): void
    {
        $this->registerRecordOfRequestAndDenyEditUnlessAllowed(RecordType::Topic);
        $this->preProcessControllerAction();
    }

    /**
     * @param bool $isPreview Shows the topic above the form
     * @param bool $isNew The topic is published, when the form is saved
     */
    #[Extbase\IgnoreValidation(['value' => 'topic'])]
    public function editAction(Topic $topic, bool $isPreview = false, bool $isNew = false, string $token = ''): ResponseInterface
    {
        return $this->renderForm($topic, [
            'topic' => $topic,
            'isPreview' => $isPreview,
            'isNew' => $isNew,
            'token' => $token,
        ]);
    }

    public function initializeUpdateAction(): void
    {
        $this->registerRecordOfRequestAndDenyEditUnlessAllowed(RecordType::Topic);
        $this->preProcessControllerAction();
    }

    public function updateAction(Topic $topic, bool $isNew = false, string $token = ''): ResponseInterface
    {
        return $this->processUpdate($topic, $isNew, $token);
    }

    public function deleteAction(int $topic, string $token = ''): ResponseInterface
    {
        return $this->processDeletion(RecordType::Topic, $topic, $token);
    }

    public function confirmActivationAction(int $topic, string $token = ''): ResponseInterface
    {
        return $this->renderActivationConfirmation(RecordType::Topic, $topic, $token);
    }

    public function activateAction(int $topic, string $token = ''): ResponseInterface
    {
        return $this->processActivation(RecordType::Topic, $topic, $token);
    }
}
