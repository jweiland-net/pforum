<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Mail;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Model\User;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Informs the author of a topic about a new visible post of the topic.
 * Template: PforumNewPost
 */
readonly class NewPostMail
{
    public function __construct(
        protected PforumMailer $pforumMailer,
    ) {}

    /**
     * @param array<string, mixed> $settings Plugin settings
     * @return bool TRUE, if the mail was sent
     */
    public function send(Post $post, array $settings, ServerRequestInterface $request): bool
    {
        $topic = $post->getTopic();
        $author = $topic instanceof Topic ? $topic->getUser() : null;
        if (!$topic instanceof Topic || !$author instanceof User) {
            return false;
        }

        return $this->pforumMailer->send(
            $author,
            sprintf((string)LocalizationUtility::translate('email.newPost.subject', 'pforum'), $topic->getTitle()),
            'PforumNewPost',
            [
                'author' => $author,
                'topic' => $topic,
                'post' => $post,
            ],
            $settings,
            $request,
        );
    }
}
