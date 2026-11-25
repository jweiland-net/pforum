<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Mail;

use JWeiland\Pforum\Configuration\ForumSettings;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\Model\User;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use Psr\Http\Message\ServerRequestInterface;
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
        protected AnonymousAccessTokenService $anonymousAccessTokenService,
        protected PforumMailer $pforumMailer,
    ) {}

    /**
     * @param array<string, mixed> $settings Plugin settings
     * @return bool TRUE, if the mail was sent
     */
    public function send(Topic|Post $record, array $settings, ServerRequestInterface $request): bool
    {
        $user = $record->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $recordType = RecordType::fromRecord($record);

        return $this->pforumMailer->send(
            $user,
            (string)LocalizationUtility::translate('email.' . $recordType->value . '.subject', 'pforum'),
            'PforumManage' . $recordType->getControllerName(),
            [
                $recordType->value => $record,
                'token' => $this->anonymousAccessTokenService->generateToken($recordType->getAccessTokenType(), (int)$record->getUid()),
                'activationToken' => $this->getActivationToken($record, $recordType, $settings),
            ],
            $settings,
            $request,
        );
    }

    /**
     * @param array<string, mixed> $settings
     */
    protected function getActivationToken(Topic|Post $record, RecordType $recordType, array $settings): string
    {
        if (!$record->getHidden() || !ForumSettings::fromArray($settings)->isActivatedByUser($recordType)) {
            return '';
        }

        return $this->anonymousAccessTokenService->generateToken($recordType->getActivationTokenType(), (int)$record->getUid());
    }
}
