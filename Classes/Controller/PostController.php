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
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Mail\NewPostMail;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Annotation as Extbase;

/**
 * Controller to create, edit, activate and delete posts
 */
class PostController extends AbstractController
{
    protected NewPostMail $newPostMail;

    public function injectNewPostMail(NewPostMail $newPostMail): void
    {
        $this->newPostMail = $newPostMail;
    }

    public function initializeNewAction(): void
    {
        $this->denyCreationUnlessAllowed();
    }

    #[Extbase\IgnoreValidation(['value' => 'post'])]
    public function newAction(Topic $topic, ?Post $post = null): ResponseInterface
    {
        $post ??= new Post();

        return $this->renderForm($post, [
            'topic' => $topic,
            'post' => $post,
        ]);
    }

    public function initializeCreateAction(): void
    {
        $this->denyCreationUnlessAllowed();
        $this->preProcessControllerAction();
    }

    public function createAction(Topic $topic, Post $post): ResponseInterface
    {
        $post->setTopic($topic);
        $topic->addPost($post);
        $this->topicRepository->update($topic);

        return $this->processCreation($post);
    }

    public function initializeEditAction(): void
    {
        $this->registerRecordOfRequestAndDenyEditUnlessAllowed(RecordType::Post);
        $this->preProcessControllerAction();
    }

    /**
     * @param bool $isPreview Shows the post above the form
     * @param bool $isNew The post is published, when the form is saved
     */
    #[Extbase\IgnoreValidation(['value' => 'post'])]
    public function editAction(Post $post, bool $isPreview = false, bool $isNew = false, string $token = ''): ResponseInterface
    {
        return $this->renderForm($post, [
            'post' => $post,
            'isPreview' => $isPreview,
            'isNew' => $isNew,
            'token' => $token,
        ]);
    }

    public function initializeUpdateAction(): void
    {
        $this->registerRecordOfRequestAndDenyEditUnlessAllowed(RecordType::Post);
        $this->preProcessControllerAction();
    }

    public function updateAction(Post $post, bool $isNew = false, string $token = ''): ResponseInterface
    {
        return $this->processUpdate($post, $isNew, $token);
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
     * The author of the topic gets a mail about a new visible post
     */
    protected function afterPublishing(Topic|Post $record): void
    {
        if ($record instanceof Post && !$record->getHidden()) {
            $this->newPostMail->send($record, $this->settings, $this->request);
        }
    }
}
