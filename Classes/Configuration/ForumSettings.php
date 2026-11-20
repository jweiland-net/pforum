<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Configuration;

use JWeiland\Pforum\Form\RecordType;

/**
 * The plugin settings, which control who may create, edit and activate
 * topics and posts
 */
final readonly class ForumSettings
{
    /**
     * Everyone can create topics and posts. A pforum user record is created
     * with name, username and email of the form.
     */
    public const AUTH_ANONYMOUS = 1;

    /**
     * Logged-in frontend users can create topics and posts
     */
    public const AUTH_FRONTEND_USER = 2;

    /**
     * @param array<string, bool> $hideAtCreation Hide new records at creation by record type value
     * @param array<string, bool> $activateByAdmin Hidden records are activated by an admin, by record type value
     */
    public function __construct(
        public int $auth = self::AUTH_ANONYMOUS,
        public bool $emailIsMandatory = false,
        public bool $usernameIsMandatory = false,
        public bool $useImages = false,
        public int $uidOfAdminGroup = 0,
        public int $uidOfUserGroup = 0,
        public array $hideAtCreation = [],
        public array $activateByAdmin = [],
    ) {}

    /**
     * @param array<string, mixed> $settings Plugin settings (plugin.tx_pforum.settings)
     */
    public static function fromArray(array $settings): self
    {
        $hideAtCreation = [];
        $activateByAdmin = [];
        foreach (RecordType::cases() as $recordType) {
            $recordSettings = $settings[$recordType->value] ?? [];
            $recordSettings = is_array($recordSettings) ? $recordSettings : [];
            $hideAtCreation[$recordType->value] = (bool)($recordSettings['hideAtCreation'] ?? false);
            $activateByAdmin[$recordType->value] = (bool)($recordSettings['activateByAdmin'] ?? false);
        }

        return new self(
            (int)($settings['auth'] ?? self::AUTH_ANONYMOUS),
            (bool)($settings['emailIsMandatory'] ?? false),
            (bool)($settings['usernameIsMandatory'] ?? false),
            (bool)($settings['useImages'] ?? false),
            (int)($settings['uidOfAdminGroup'] ?? 0),
            (int)($settings['uidOfUserGroup'] ?? 0),
            $hideAtCreation,
            $activateByAdmin,
        );
    }

    public function isAnonymousMode(): bool
    {
        return $this->auth === self::AUTH_ANONYMOUS;
    }

    public function isFrontendUserMode(): bool
    {
        return $this->auth === self::AUTH_FRONTEND_USER;
    }

    public function isHiddenAtCreation(RecordType $recordType): bool
    {
        return $this->hideAtCreation[$recordType->value] ?? false;
    }

    public function isActivatedByAdmin(RecordType $recordType): bool
    {
        return $this->activateByAdmin[$recordType->value] ?? false;
    }

    /**
     * Anonymous users get a mail with links to edit and delete their record
     */
    public function isMailToUserEnabled(): bool
    {
        return $this->isAnonymousMode() && $this->emailIsMandatory;
    }

    /**
     * Hidden records are activated by their author with the link of the mail
     */
    public function isActivatedByUser(RecordType $recordType): bool
    {
        return $this->isHiddenAtCreation($recordType)
            && !$this->isActivatedByAdmin($recordType)
            && $this->isMailToUserEnabled();
    }

    /**
     * Hidden records, which are neither activated by an admin nor by their
     * author, would never be visible
     */
    public function hasUnreachableHiddenRecords(RecordType $recordType): bool
    {
        return $this->isHiddenAtCreation($recordType)
            && !$this->isActivatedByAdmin($recordType)
            && !$this->emailIsMandatory;
    }
}
