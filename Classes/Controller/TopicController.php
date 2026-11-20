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
use JWeiland\Pforum\Form\RecordType;
use Psr\Http\Message\ResponseInterface;

/**
 * Controller to show topics. Topics are created and edited with the topic
 * form based on EXT:form (EXT:pforum/Configuration/Form/Topic.form.yaml).
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

    /**
     * Renders the topic form for a new topic of the forum
     */
    public function newAction(Forum $forum): ResponseInterface
    {
        return $this->renderNewForm(['forum' => $forum]);
    }

    /**
     * Renders the topic form to edit the topic. Hidden topics can be edited, too.
     */
    public function editAction(int $topic, string $token = ''): ResponseInterface
    {
        return $this->renderEditForm(RecordType::Topic, $topic, $token);
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
