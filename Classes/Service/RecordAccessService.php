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
use JWeiland\Pforum\Form\RecordFormContext;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Decides, who is allowed to create, edit, delete and activate topics and
 * posts in frontend. Methods returning a "denial reason" return the label
 * key of the reason (locallang.xlf) or an empty string, if access is allowed.
 */
readonly class RecordAccessService
{
    public const PLUGIN_NAMESPACE = 'tx_pforum_forum';

    public const REASON_UNAUTHORIZED = 'unauthorizedUser';

    public const REASON_NEEDS_LOGIN = 'pluginNeedsLogin';

    public const REASON_NOT_ALLOWED_TO_CREATE = 'notAllowedToCreate';

    public const REASON_PARENT_NOT_FOUND = 'parentNotFound';

    public function __construct(
        private Context $context,
        private RecordService $recordService,
        private AnonymousAccessTokenService $anonymousAccessTokenService,
    ) {}

    /**
     * Returns the UID of the record to edit. 0 means: create a new record.
     */
    public function getRecordUid(ServerRequestInterface $request, RecordType $recordType): int
    {
        return $this->getIntegerArgument($request, $recordType->getArgumentName());
    }

    /**
     * Returns the UID of the forum of a new topic or of the topic of a new post
     */
    public function getParentUid(ServerRequestInterface $request, RecordType $recordType): int
    {
        return $this->getIntegerArgument($request, $recordType->getParentColumn());
    }

    public function getToken(ServerRequestInterface $request): string
    {
        $token = $this->getPluginQueryParameters($request)['token'] ?? '';

        return is_string($token) ? $token : '';
    }

    public function getFormDenialReason(RecordFormContext $formContext): string
    {
        if (!$formContext->isNew()) {
            return $this->getEditDenialReason(
                $formContext->recordType,
                $formContext->recordUid,
                $formContext->token,
                $formContext->settings,
            );
        }

        if ($this->recordService->findParentRecord($formContext->recordType, $formContext->parentUid) === []) {
            return self::REASON_PARENT_NOT_FOUND;
        }

        return $this->getCreationDenialReason($formContext->settings);
    }

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
    public function getEditDenialReason(RecordType $recordType, int $uid, string $token, ForumSettings $settings): string
    {
        $record = $this->recordService->findRecord($recordType, $uid);
        $isAllowed = match (true) {
            $record === [] => false,
            $settings->isAnonymousMode() => $this->anonymousAccessTokenService->isTokenValid(
                $recordType->getAccessTokenType(),
                $uid,
                $token,
            ),
            $settings->isFrontendUserMode() => (int)$record['frontend_user'] > 0
                && (int)$record['frontend_user'] === $this->getFrontendUserUid(),
            default => false,
        };

        return $isAllowed ? '' : self::REASON_UNAUTHORIZED;
    }

    /**
     * Members of the admin group can activate all records. Authors can
     * activate their record with the token of the mail link, if they are
     * allowed to activate their records.
     */
    public function isActivationAllowed(RecordType $recordType, int $uid, string $token, ForumSettings $settings): bool
    {
        if ($this->isAdmin($settings)) {
            return true;
        }

        return $settings->isActivatedByUser($recordType)
            && $this->anonymousAccessTokenService->isTokenValid($recordType->getActivationTokenType(), $uid, $token);
    }

    public function isAdmin(ForumSettings $settings): bool
    {
        return $settings->uidOfAdminGroup > 0 && $this->isMemberOfGroup($settings->uidOfAdminGroup);
    }

    public function getFrontendUserUid(): int
    {
        return (int)$this->context->getPropertyFromAspect('frontend.user', 'id', 0);
    }

    private function isMemberOfGroup(int $groupUid): bool
    {
        $groupIds = $this->context->getPropertyFromAspect('frontend.user', 'groupIds', []);

        return in_array($groupUid, is_array($groupIds) ? array_map(intval(...), $groupIds) : [], true);
    }

    private function getIntegerArgument(ServerRequestInterface $request, string $argumentName): int
    {
        $value = $this->getPluginQueryParameters($request)[$argumentName] ?? 0;

        return MathUtility::canBeInterpretedAsInteger($value) ? max(0, (int)$value) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPluginQueryParameters(ServerRequestInterface $request): array
    {
        $parameters = $request->getQueryParams()[self::PLUGIN_NAMESPACE] ?? [];

        return is_array($parameters) ? $parameters : [];
    }
}
