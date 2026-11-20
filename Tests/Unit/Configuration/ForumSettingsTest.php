<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Configuration;

use JWeiland\Pforum\Configuration\ForumSettings;
use JWeiland\Pforum\Form\RecordType;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class ForumSettingsTest extends UnitTestCase
{
    #[Test]
    public function fromArrayReadsPluginSettings(): void
    {
        $settings = ForumSettings::fromArray([
            'auth' => '2',
            'emailIsMandatory' => '1',
            'usernameIsMandatory' => '0',
            'useImages' => '1',
            'uidOfAdminGroup' => '4',
            'uidOfUserGroup' => '3',
            'topic' => ['hideAtCreation' => '1', 'activateByAdmin' => '0'],
            'post' => ['hideAtCreation' => '0', 'activateByAdmin' => '1'],
        ]);

        self::assertTrue($settings->isFrontendUserMode());
        self::assertTrue($settings->emailIsMandatory);
        self::assertFalse($settings->usernameIsMandatory);
        self::assertTrue($settings->useImages);
        self::assertSame(4, $settings->uidOfAdminGroup);
        self::assertSame(3, $settings->uidOfUserGroup);
        self::assertTrue($settings->isHiddenAtCreation(RecordType::Topic));
        self::assertFalse($settings->isHiddenAtCreation(RecordType::Post));
        self::assertTrue($settings->isActivatedByAdmin(RecordType::Post));
    }

    #[Test]
    public function fromArrayWithoutSettingsUsesAnonymousMode(): void
    {
        $settings = ForumSettings::fromArray([]);

        self::assertTrue($settings->isAnonymousMode());
        self::assertFalse($settings->isHiddenAtCreation(RecordType::Topic));
    }

    #[Test]
    public function authorActivatesHiddenRecordWithLinkOfMail(): void
    {
        $settings = new ForumSettings(auth: 1, emailIsMandatory: true, hideAtCreation: ['topic' => true]);

        self::assertTrue($settings->isMailToUserEnabled());
        self::assertTrue($settings->isActivatedByUser(RecordType::Topic));
        self::assertFalse($settings->isActivatedByUser(RecordType::Post));
    }

    #[Test]
    public function authorDoesNotActivateRecordsActivatedByAdmin(): void
    {
        $settings = new ForumSettings(
            auth: 1,
            emailIsMandatory: true,
            hideAtCreation: ['topic' => true],
            activateByAdmin: ['topic' => true],
        );

        self::assertFalse($settings->isActivatedByUser(RecordType::Topic));
    }

    #[Test]
    public function frontendUsersGetNoMail(): void
    {
        $settings = new ForumSettings(auth: 2, emailIsMandatory: true, hideAtCreation: ['topic' => true]);

        self::assertFalse($settings->isMailToUserEnabled());
        self::assertFalse($settings->isActivatedByUser(RecordType::Topic));
    }

    #[Test]
    public function hiddenRecordsWithoutActivationAreUnreachable(): void
    {
        self::assertTrue((new ForumSettings(hideAtCreation: ['post' => true]))->hasUnreachableHiddenRecords(RecordType::Post));
        self::assertFalse(
            (new ForumSettings(emailIsMandatory: true, hideAtCreation: ['post' => true]))->hasUnreachableHiddenRecords(RecordType::Post),
        );
    }
}
