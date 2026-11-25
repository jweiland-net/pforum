<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Service;

use JWeiland\Pforum\Configuration\ForumSettings;
use JWeiland\Pforum\Domain\Model\FrontendUser;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Utility\TypeCaster;
use TYPO3\CMS\Core\Context\Context;

/**
 * Decides, who is allowed to create, edit, delete and activate topics and
 * posts in frontend. Methods returning a "denial reason" return the label
 * key of the reason (locallang.xlf) or an empty string, if access is allowed.
 */
readonly class RecordAccessService
{
    public const REASON_UNAUTHORIZED = 'unauthorizedUser';

    public const REASON_NEEDS_LOGIN = 'pluginNeedsLogin';

    public const REASON_NOT_ALLOWED_TO_CREATE = 'notAllowedToCreate';

    public function __construct(
        private Context $context,
        private AnonymousAccessTokenService $anonymousAccessTokenService,
    ) {}

    public function getCreationDenialReason(ForumSettings $settings): string
    {
        if ($settings->isAnonymousMode()) {
            return '';
        }

        return match (true) {
            !$settings->isFrontendUserMode() => self::REASON_NOT_ALLOWED_TO_CREATE,
            $this->getFrontendUserUid() <= 0 => self::REASON_NEEDS_LOGIN,
            $settings->uidOfUserGroup > 0 && !$this->isMemberOfGroup($settings->uidOfUserGroup) => self::REASON_NOT_ALLOWED_TO_CREATE,
            default => '',
        };
    }

    /**
     * Anonymous users need the token of the mail link, frontend users have
     * to be the author of the record. This is valid for deletion, too.
     */
    public function getEditDenialReason(Topic|Post|null $record, string $token, ForumSettings $settings): string
    {
        $isAllowed = match (true) {
            $record === null => false,
            $settings->isAnonymousMode() => $this->anonymousAccessTokenService->isTokenValid(
                RecordType::fromRecord($record)->getAccessTokenType(),
                (int)$record->getUid(),
                $token,
            ),
            $settings->isFrontendUserMode() => $this->isAuthor($record),
            default => false,
        };

        return $isAllowed ? '' : self::REASON_UNAUTHORIZED;
    }

    /**
     * Members of the admin group can activate all records. Authors can
     * activate their record with the token of the mail link, if they are
     * allowed to activate their records.
     */
    public function isActivationAllowed(Topic|Post $record, string $token, ForumSettings $settings): bool
    {
        if ($this->isAdmin($settings)) {
            return true;
        }

        $recordType = RecordType::fromRecord($record);

        return $settings->isActivatedByUser($recordType)
            && $this->anonymousAccessTokenService->isTokenValid(
                $recordType->getActivationTokenType(),
                (int)$record->getUid(),
                $token,
            );
    }

    public function isAdmin(ForumSettings $settings): bool
    {
        return $settings->uidOfAdminGroup > 0 && $this->isMemberOfGroup($settings->uidOfAdminGroup);
    }

    public function isAuthor(Topic|Post $record): bool
    {
        $frontendUser = $record->getFrontendUser();

        return $frontendUser instanceof FrontendUser
            && (int)$frontendUser->getUid() > 0
            && (int)$frontendUser->getUid() === $this->getFrontendUserUid();
    }

    /**
     * Token, which allows anonymous users to edit and delete their record
     */
    public function generateAccessToken(Topic|Post $record): string
    {
        return $this->anonymousAccessTokenService->generateToken(
            RecordType::fromRecord($record)->getAccessTokenType(),
            (int)$record->getUid(),
        );
    }

    public function getFrontendUserUid(): int
    {
        return TypeCaster::toInt($this->context->getPropertyFromAspect('frontend.user', 'id', 0));
    }

    private function isMemberOfGroup(int $groupUid): bool
    {
        $groupIds = $this->context->getPropertyFromAspect('frontend.user', 'groupIds', []);

        return in_array($groupUid, is_array($groupIds) ? array_map(TypeCaster::toInt(...), $groupIds) : [], true);
    }
}
