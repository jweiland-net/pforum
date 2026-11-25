<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Traits;

use JWeiland\Pforum\Configuration\ExtConf;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Repository\PostRepository;
use JWeiland\Pforum\Domain\Repository\TopicRepository;
use JWeiland\Pforum\Mail\MailTemplatePathsFactory;
use JWeiland\Pforum\Mail\PforumMailer;
use JWeiland\Pforum\Tests\Functional\Fixtures\Mail\CapturingTransport;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\Transport\TransportInterface;
use TYPO3\CMS\Core\Mail\Mailer;

/**
 * Mails of pforum, which are sent to a test transport. The templates of
 * Fixtures/Mail/Templates print the variables of the mails, as links of
 * the real templates need a complete frontend request. The real templates
 * are rendered by the tests of the frontend flows.
 */
trait MailTestTrait
{
    private function setUpMailTest(): void
    {
        CapturingTransport::reset();
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'install-tool@example.com';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Install Tool';
    }

    /**
     * @return array<string, mixed>
     */
    private function getMailSettings(string $topicOrPost = 'topic', bool $hideAtCreation = false, bool $activateByAdmin = false): array
    {
        return [
            'auth' => 1,
            'emailIsMandatory' => 1,
            'pidOfDetailPage' => 0,
            $topicOrPost => ['hideAtCreation' => $hideAtCreation ? 1 : 0, 'activateByAdmin' => $activateByAdmin ? 1 : 0],
            'email' => ['templateRootPaths' => [0 => 'EXT:pforum/Tests/Functional/Fixtures/Mail/Templates/']],
        ];
    }

    private function createPforumMailer(TransportInterface $transport, ?ExtConf $extConf = null): PforumMailer
    {
        return new PforumMailer(
            $this->get(MailTemplatePathsFactory::class),
            $extConf ?? new ExtConf('forum@example.com', 'Forum'),
            new Mailer($transport),
            new NullLogger(),
        );
    }

    private function getTopic(int $uid): Topic
    {
        $topic = $this->get(TopicRepository::class)->findHiddenObject($uid);
        self::assertInstanceOf(Topic::class, $topic);

        return $topic;
    }

    private function getPost(int $uid): Post
    {
        $post = $this->get(PostRepository::class)->findHiddenObject($uid);
        self::assertInstanceOf(Post::class, $post);

        return $post;
    }
}
