<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Controller;

use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Mail\NewPostMail;
use Psr\Http\Message\ResponseInterface;

/**
 * Controller to manage posts. Posts are created and edited with the post
 * form based on EXT:form (EXT:pforum/Configuration/Form/Post.form.yaml).
 */
class PostController extends AbstractController
{
    protected NewPostMail $newPostMail;

    public function injectNewPostMail(NewPostMail $newPostMail): void
    {
        $this->newPostMail = $newPostMail;
    }

    /**
     * Renders the post form for a new post of the topic
     */
    public function newAction(Topic $topic): ResponseInterface
    {
        return $this->renderNewForm(['topic' => $topic]);
    }

    /**
     * Renders the post form to edit the post. Hidden posts can be edited, too.
     */
    public function editAction(int $post, string $token = ''): ResponseInterface
    {
        return $this->renderEditForm(RecordType::Post, $post, $token);
    }

    public function deleteAction(int $post, string $token = ''): ResponseInterface
    {
        return $this->processDeletion(RecordType::Post, $post, $token);
    }

    public function confirmActivationAction(int $post, string $token = ''): ResponseInterface
    {
        return $this->renderActivationConfirmation(RecordType::Post, $post, $token);
    }

    public function activateAction(int $post, string $token = ''): ResponseInterface
    {
        return $this->processActivation(RecordType::Post, $post, $token);
    }

    /**
     * The author of the topic gets a mail about the activated post
     */
    protected function afterActivation(RecordType $recordType, int $uid): void
    {
        $this->newPostMail->send($uid, $this->request);
    }
}
