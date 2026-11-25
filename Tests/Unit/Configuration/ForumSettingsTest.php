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
use JWeiland\Pforum\Domain\RecordType;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{ForumSettings, RecordType, bool, string}>
     */
    public static function creationMessageKeyDataProvider(): array
    {
        return [
            'visible topic' => [new ForumSettings(), RecordType::Topic, false, 'topicCreated'],
            'visible post' => [new ForumSettings(), RecordType::Post, false, 'postCreated'],
            'visible record ignores admin activation' => [
                new ForumSettings(activateByAdmin: ['topic' => true]),
                RecordType::Topic,
                false,
                'topicCreated',
            ],
            'hidden topic activated by admin' => [
                new ForumSettings(activateByAdmin: ['topic' => true]),
                RecordType::Topic,
                true,
                'hiddenTopicCreatedAndActivateByAdmin',
            ],
            'hidden post activated by admin' => [
                new ForumSettings(activateByAdmin: ['post' => true]),
                RecordType::Post,
                true,
                'hiddenPostCreatedAndActivateByAdmin',
            ],
            'hidden topic activated by user' => [
                new ForumSettings(emailIsMandatory: true),
                RecordType::Topic,
                true,
                'hiddenTopicCreatedAndActivateByUser',
            ],
            'admin activation of other type does not count' => [
                new ForumSettings(activateByAdmin: ['topic' => true]),
                RecordType::Post,
                true,
                'hiddenPostCreatedAndActivateByUser',
            ],
        ];
    }

    #[Test]
    #[DataProvider('creationMessageKeyDataProvider')]
    public function getCreationMessageKeyReturnsKeyOfCreation(
        ForumSettings $settings,
        RecordType $recordType,
        bool $isHidden,
        string $expected,
    ): void {
        self::assertSame($expected, $settings->getCreationMessageKey($recordType, $isHidden));
    }

    /**
     * @return array<string, array{ForumSettings, int}>
     */
    public static function inconsistentSettingsDataProvider(): array
    {
        return [
            'topics hidden forever' => [new ForumSettings(hideAtCreation: ['topic' => true]), 1378371532],
            'posts hidden forever' => [new ForumSettings(hideAtCreation: ['post' => true]), 1378371541],
            'topics and posts hidden forever reports topic first' => [
                new ForumSettings(hideAtCreation: ['topic' => true, 'post' => true]),
                1378371532,
            ],
            'posts hidden forever, topics are fine' => [
                new ForumSettings(hideAtCreation: ['topic' => true, 'post' => true], activateByAdmin: ['topic' => true]),
                1378371541,
            ],
        ];
    }

    #[Test]
    #[DataProvider('inconsistentSettingsDataProvider')]
    public function assertConsistencyThrowsExceptionForUnreachableRecords(ForumSettings $settings, int $code): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode($code);

        $settings->assertConsistency();
    }

    #[Test]
    public function assertConsistencyNamesRecordTypeInMessage(): void
    {
        try {
            (new ForumSettings(hideAtCreation: ['post' => true]))->assertConsistency();
            self::fail('Expected exception was not thrown');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('hide posts at creation', $exception->getMessage());
        }
    }

    /**
     * @return array<string, array{ForumSettings}>
     */
    public static function consistentSettingsDataProvider(): array
    {
        return [
            'defaults' => [new ForumSettings()],
            'hidden with mandatory email' => [
                new ForumSettings(emailIsMandatory: true, hideAtCreation: ['topic' => true, 'post' => true]),
            ],
            'hidden with admin activation' => [
                new ForumSettings(
                    hideAtCreation: ['topic' => true, 'post' => true],
                    activateByAdmin: ['topic' => true, 'post' => true],
                ),
            ],
            'admin activation without hiding' => [new ForumSettings(activateByAdmin: ['topic' => true])],
        ];
    }

    #[Test]
    #[DataProvider('consistentSettingsDataProvider')]
    public function assertConsistencyAcceptsReachableRecords(ForumSettings $settings): void
    {
        $settings->assertConsistency();

        self::assertFalse($settings->hasUnreachableHiddenRecords(RecordType::Topic));
        self::assertFalse($settings->hasUnreachableHiddenRecords(RecordType::Post));
    }
}
