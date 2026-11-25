<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\EventListener;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Event\RecordPublishedEvent;
use JWeiland\Pforum\Mail\NewPostMail;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * The author of a topic gets a mail about a new visible post of the topic
 */
#[AsEventListener(
    identifier: 'pforum/send-new-post-mail',
)]
final readonly class SendNewPostMailEventListener
{
    public function __construct(
        private NewPostMail $newPostMail,
    ) {}

    public function __invoke(RecordPublishedEvent $event): void
    {
        $record = $event->getRecord();
        if (!$record instanceof Post || $record->getHidden()) {
            return;
        }

        $this->newPostMail->send($record, $event->getSettings(), $event->getRequest());
    }
}
