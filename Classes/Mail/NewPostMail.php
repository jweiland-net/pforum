<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Mail;

use JWeiland\Pforum\Configuration\ExtConf;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Model\User;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Informs the author of a topic about a new visible post of the topic.
 * Template: PforumNewPost
 */
readonly class NewPostMail
{
    public function __construct(
        private MailTemplatePathsFactory $mailTemplatePathsFactory,
        private ExtConf $extConf,
        private MailerInterface $mailer,
    ) {}

    /**
     * @param array<string, mixed> $settings Plugin settings
     */
    public function send(Post $post, array $settings, ServerRequestInterface $request): void
    {
        $topic = $post->getTopic();
        $author = $topic instanceof Topic ? $topic->getUser() : null;
        if (!$topic instanceof Topic || !$author instanceof User || !GeneralUtility::validEmail($author->getEmail())) {
            return;
        }

        $email = GeneralUtility::makeInstance(FluidEmail::class, $this->mailTemplatePathsFactory->create($settings));
        $email
            ->to(new Address($author->getEmail(), $author->getName()))
            ->from(new Address($this->extConf->getEmailFromAddress(), $this->extConf->getEmailFromName()))
            ->subject(sprintf((string)LocalizationUtility::translate('email.newPost.subject', 'pforum'), $topic->getTitle()))
            ->setRequest($request)
            ->setTemplate('PforumNewPost')
            ->assignMultiple([
                'settings' => $settings,
                'author' => $author,
                'topic' => $topic,
                'post' => $post,
            ]);

        $this->mailer->send($email);
    }
}
