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
use JWeiland\Pforum\Configuration\ForumSettings;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Model\User;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Sends the links to edit and delete a new topic or post to its pforum user
 * (anonymous user). If the author activates the record, the mail contains
 * the link to activate it, too. Templates: PforumManageTopic and
 * PforumManagePost.
 */
readonly class ManageRecordMail
{
    public function __construct(
        private AnonymousAccessTokenService $anonymousAccessTokenService,
        private MailTemplatePathsFactory $mailTemplatePathsFactory,
        private ExtConf $extConf,
        private MailerInterface $mailer,
    ) {}

    /**
     * @param array<string, mixed> $settings Plugin settings
     */
    public function send(Topic|Post $record, array $settings, ServerRequestInterface $request): void
    {
        $user = $record->getUser();
        if (!$user instanceof User || !GeneralUtility::validEmail($user->getEmail())) {
            return;
        }

        $recordType = $record instanceof Topic ? RecordType::Topic : RecordType::Post;
        $email = GeneralUtility::makeInstance(FluidEmail::class, $this->mailTemplatePathsFactory->create($settings));
        $email
            ->to(new Address($user->getEmail(), $user->getName()))
            ->from(new Address($this->extConf->getEmailFromAddress(), $this->extConf->getEmailFromName()))
            ->subject((string)LocalizationUtility::translate('email.' . $recordType->value . '.subject', 'pforum'))
            ->setRequest($request)
            ->setTemplate('PforumManage' . $recordType->getControllerName())
            ->assignMultiple([
                'settings' => $settings,
                $recordType->value => $record,
                'token' => $this->anonymousAccessTokenService->generateToken($recordType->getAccessTokenType(), (int)$record->getUid()),
                'activationToken' => $this->getActivationToken($record, $recordType, $settings),
            ]);

        $this->mailer->send($email);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function getActivationToken(Topic|Post $record, RecordType $recordType, array $settings): string
    {
        if (!$record->getHidden() || !ForumSettings::fromArray($settings)->isActivatedByUser($recordType)) {
            return '';
        }

        return $this->anonymousAccessTokenService->generateToken($recordType->getActivationTokenType(), (int)$record->getUid());
    }
}
