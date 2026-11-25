<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Service;

use JWeiland\Pforum\Configuration\ForumSettings;
use JWeiland\Pforum\Domain\Model\FrontendUser;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Service\RecordAccessService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class RecordAccessServiceTest extends UnitTestCase
{
    private Context&MockObject $context;

    private HashService $hashService;

    private AnonymousAccessTokenService $tokenService;

    private RecordAccessService $subject;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $sysConfigurationBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sysConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['SYS'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'unit-test-encryption-key-0123456789abcdef';

        $this->context = $this->createMock(Context::class);
        $this->hashService = new HashService();
        $this->tokenService = new AnonymousAccessTokenService($this->hashService, $this->createMock(LoggerInterface::class));
        $this->subject = new RecordAccessService($this->context, $this->tokenService);
    }

    protected function tearDown(): void
    {
        if ($this->sysConfigurationBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['SYS']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['SYS'] = $this->sysConfigurationBackup;
        }

        parent::tearDown();
    }

    /**
     * @param int[] $groupIds
     */
    private function loginAs(int $userUid, array $groupIds = []): void
    {
        $this->context
            ->method('getPropertyFromAspect')
            ->willReturnCallback(
                static fn(string $aspect, string $property, mixed $default = null): mixed => match ([$aspect, $property]) {
                    ['frontend.user', 'id'] => $userUid,
                    ['frontend.user', 'groupIds'] => $groupIds,
                    default => $default,
                },
            );
    }

    private function createFrontendUser(int $uid): FrontendUser
    {
        $frontendUser = new FrontendUser();
        $frontendUser->_setProperty('uid', $uid);

        return $frontendUser;
    }

    private function createTopic(int $uid = 5, ?FrontendUser $author = null): Topic
    {
        $topic = new Topic();
        $topic->_setProperty('uid', $uid);
        if ($author instanceof FrontendUser) {
            $topic->setFrontendUser($author);
        }

        return $topic;
    }

    private function createPost(int $uid = 6, ?FrontendUser $author = null): Post
    {
        $post = new Post();
        $post->_setProperty('uid', $uid);
        if ($author instanceof FrontendUser) {
            $post->setFrontendUser($author);
        }

        return $post;
    }

    #[Test]
    public function creationIsAllowedInAnonymousMode(): void
    {
        $this->loginAs(0);

        self::assertSame('', $this->subject->getCreationDenialReason(new ForumSettings(auth: 1)));
    }

    #[Test]
    public function creationIsDeniedForUnknownAuthMode(): void
    {
        $this->loginAs(3, [1]);

        self::assertSame(
            RecordAccessService::REASON_NOT_ALLOWED_TO_CREATE,
            $this->subject->getCreationDenialReason(new ForumSettings(auth: 3)),
        );
    }

    #[Test]
    public function creationNeedsLoginInFrontendUserMode(): void
    {
        $this->loginAs(0);

        self::assertSame(
            RecordAccessService::REASON_NEEDS_LOGIN,
            $this->subject->getCreationDenialReason(new ForumSettings(auth: 2)),
        );
    }

    /**
     * @return array<string, array{int[], int, string}>
     */
    public static function userGroupDataProvider(): array
    {
        return [
            'no user group required' => [[1, 2], 0, ''],
            'member of user group' => [[1, 7], 7, ''],
            'member of user group given as strings' => [['1', '7'], 7, ''],
            'not a member of user group' => [[1, 2], 7, RecordAccessService::REASON_NOT_ALLOWED_TO_CREATE],
            'no groups at all' => [[], 7, RecordAccessService::REASON_NOT_ALLOWED_TO_CREATE],
        ];
    }

    /**
     * @param int[] $groupIds
     */
    #[Test]
    #[DataProvider('userGroupDataProvider')]
    public function creationOfFrontendUserDependsOnUserGroup(array $groupIds, int $uidOfUserGroup, string $expected): void
    {
        $this->loginAs(4, $groupIds);

        self::assertSame(
            $expected,
            $this->subject->getCreationDenialReason(new ForumSettings(auth: 2, uidOfUserGroup: $uidOfUserGroup)),
        );
    }

    #[Test]
    public function getFrontendUserUidReturnsUidOfAspect(): void
    {
        $this->loginAs(42);

        self::assertSame(42, $this->subject->getFrontendUserUid());
    }

    #[Test]
    public function editIsDeniedWithoutRecord(): void
    {
        $this->loginAs(1);

        self::assertSame(
            RecordAccessService::REASON_UNAUTHORIZED,
            $this->subject->getEditDenialReason(null, 'token', new ForumSettings(auth: 1)),
        );
        self::assertSame(
            RecordAccessService::REASON_UNAUTHORIZED,
            $this->subject->getEditDenialReason(null, '', new ForumSettings(auth: 2)),
        );
    }

    #[Test]
    public function anonymousUserMayEditWithValidTopicToken(): void
    {
        $this->loginAs(0);
        $topic = $this->createTopic(5);

        self::assertSame(
            '',
            $this->subject->getEditDenialReason($topic, $this->subject->generateAccessToken($topic), new ForumSettings(auth: 1)),
        );
    }

    #[Test]
    public function anonymousUserMayEditWithValidPostToken(): void
    {
        $this->loginAs(0);
        $post = $this->createPost(6);

        self::assertSame(
            '',
            $this->subject->getEditDenialReason($post, $this->subject->generateAccessToken($post), new ForumSettings(auth: 1)),
        );
    }

    #[Test]
    public function anonymousUserMayNotEditWithTokenOfOtherType(): void
    {
        $this->loginAs(0);
        $topic = $this->createTopic(5);
        $postWithSameUid = $this->createPost(5);

        self::assertSame(
            RecordAccessService::REASON_UNAUTHORIZED,
            $this->subject->getEditDenialReason(
                $topic,
                $this->subject->generateAccessToken($postWithSameUid),
                new ForumSettings(auth: 1),
            ),
        );
    }

    #[Test]
    public function anonymousUserMayNotEditWithActivationToken(): void
    {
        $this->loginAs(0);
        $topic = $this->createTopic(5);
        $activationToken = $this->tokenService->generateToken('TopicActivation', 5);

        self::assertSame(
            RecordAccessService::REASON_UNAUTHORIZED,
            $this->subject->getEditDenialReason($topic, $activationToken, new ForumSettings(auth: 1)),
        );
    }

    #[Test]
    public function anonymousUserMayNotEditWithEmptyOrInvalidToken(): void
    {
        $this->loginAs(0);
        $topic = $this->createTopic(5);

        self::assertSame(
            RecordAccessService::REASON_UNAUTHORIZED,
            $this->subject->getEditDenialReason($topic, '', new ForumSettings(auth: 1)),
        );
        self::assertSame(
            RecordAccessService::REASON_UNAUTHORIZED,
            $this->subject->getEditDenialReason($topic, 'invalid', new ForumSettings(auth: 1)),
        );
    }

    #[Test]
    public function anonymousUserMayEditWithLegacySha1Token(): void
    {
        $this->loginAs(0);
        $legacyToken = $this->hashService->appendHmac('Topic:5', 'pforum-anonymous-access', HashAlgo::SHA1);

        self::assertSame(
            '',
            $this->subject->getEditDenialReason($this->createTopic(5), $legacyToken, new ForumSettings(auth: 1)),
        );
    }

    #[Test]
    public function frontendUserMayEditOwnRecordWithoutToken(): void
    {
        $this->loginAs(9);

        self::assertSame(
            '',
            $this->subject->getEditDenialReason(
                $this->createTopic(5, $this->createFrontendUser(9)),
                '',
                new ForumSettings(auth: 2),
            ),
        );
    }

    #[Test]
    public function frontendUserMayNotEditRecordOfOtherUserEvenWithToken(): void
    {
        $this->loginAs(9);
        $topic = $this->createTopic(5, $this->createFrontendUser(10));

        self::assertSame(
            RecordAccessService::REASON_UNAUTHORIZED,
            $this->subject->getEditDenialReason($topic, $this->subject->generateAccessToken($topic), new ForumSettings(auth: 2)),
        );
    }

    #[Test]
    public function editIsDeniedForUnknownAuthMode(): void
    {
        $this->loginAs(9);
        $topic = $this->createTopic(5, $this->createFrontendUser(9));

        self::assertSame(
            RecordAccessService::REASON_UNAUTHORIZED,
            $this->subject->getEditDenialReason($topic, $this->subject->generateAccessToken($topic), new ForumSettings(auth: 3)),
        );
    }

    /**
     * @return array<string, array{int, int[], bool}>
     */
    public static function adminDataProvider(): array
    {
        return [
            'member of admin group' => [4, [1, 4], true],
            'not a member of admin group' => [4, [1, 2], false],
            'no admin group configured' => [0, [0, 1], false],
            'negative admin group' => [-1, [-1], false],
            'no groups' => [4, [], false],
            'groups as strings' => [4, ['4'], true],
        ];
    }

    /**
     * @param int[] $groupIds
     */
    #[Test]
    #[DataProvider('adminDataProvider')]
    public function isAdminChecksMembershipOfAdminGroup(int $uidOfAdminGroup, array $groupIds, bool $expected): void
    {
        $this->loginAs(1, $groupIds);

        self::assertSame($expected, $this->subject->isAdmin(new ForumSettings(uidOfAdminGroup: $uidOfAdminGroup)));
    }

    #[Test]
    public function isAdminIsFalseIfGroupIdsAreNotAnArray(): void
    {
        $this->context->method('getPropertyFromAspect')->willReturn('4');

        self::assertFalse($this->subject->isAdmin(new ForumSettings(uidOfAdminGroup: 4)));
    }

    #[Test]
    public function adminMayActivateEveryRecord(): void
    {
        $this->loginAs(1, [4]);

        self::assertTrue($this->subject->isActivationAllowed($this->createTopic(), '', new ForumSettings(uidOfAdminGroup: 4)));
        self::assertTrue($this->subject->isActivationAllowed($this->createPost(), '', new ForumSettings(uidOfAdminGroup: 4)));
    }

    #[Test]
    public function authorMayActivateWithValidActivationToken(): void
    {
        $this->loginAs(0);
        $topic = $this->createTopic(5);
        $settings = new ForumSettings(auth: 1, emailIsMandatory: true, hideAtCreation: ['topic' => true]);
        $token = $this->tokenService->generateToken('TopicActivation', 5);

        self::assertTrue($this->subject->isActivationAllowed($topic, $token, $settings));
    }

    #[Test]
    public function authorMayActivatePostWithValidActivationToken(): void
    {
        $this->loginAs(0);
        $post = $this->createPost(6);
        $settings = new ForumSettings(auth: 1, emailIsMandatory: true, hideAtCreation: ['post' => true]);
        $token = $this->tokenService->generateToken('PostActivation', 6);

        self::assertTrue($this->subject->isActivationAllowed($post, $token, $settings));
    }

    #[Test]
    public function authorMayNotActivateWithEditToken(): void
    {
        $this->loginAs(0);
        $topic = $this->createTopic(5);
        $settings = new ForumSettings(auth: 1, emailIsMandatory: true, hideAtCreation: ['topic' => true]);

        self::assertFalse($this->subject->isActivationAllowed($topic, $this->subject->generateAccessToken($topic), $settings));
    }

    #[Test]
    public function authorMayNotActivateWithTokenOfOtherUid(): void
    {
        $this->loginAs(0);
        $settings = new ForumSettings(auth: 1, emailIsMandatory: true, hideAtCreation: ['topic' => true]);
        $token = $this->tokenService->generateToken('TopicActivation', 99);

        self::assertFalse($this->subject->isActivationAllowed($this->createTopic(5), $token, $settings));
    }

    /**
     * @return array<string, array{ForumSettings}>
     */
    public static function noUserActivationDataProvider(): array
    {
        return [
            'records are not hidden' => [new ForumSettings(auth: 1, emailIsMandatory: true)],
            'activated by admin' => [
                new ForumSettings(auth: 1, emailIsMandatory: true, hideAtCreation: ['topic' => true], activateByAdmin: ['topic' => true]),
            ],
            'no mail to user' => [new ForumSettings(auth: 1, emailIsMandatory: false, hideAtCreation: ['topic' => true])],
            'frontend user mode' => [new ForumSettings(auth: 2, emailIsMandatory: true, hideAtCreation: ['topic' => true])],
        ];
    }

    #[Test]
    #[DataProvider('noUserActivationDataProvider')]
    public function authorMayNotActivateIfSettingsDoNotAllowIt(ForumSettings $settings): void
    {
        $this->loginAs(0);
        $token = $this->tokenService->generateToken('TopicActivation', 5);

        self::assertFalse($this->subject->isActivationAllowed($this->createTopic(5), $token, $settings));
    }

    #[Test]
    public function isAuthorIsTrueForUserWithSameUid(): void
    {
        $this->loginAs(9);

        self::assertTrue($this->subject->isAuthor($this->createTopic(5, $this->createFrontendUser(9))));
        self::assertTrue($this->subject->isAuthor($this->createPost(6, $this->createFrontendUser(9))));
    }

    #[Test]
    public function isAuthorIsFalseForOtherUser(): void
    {
        $this->loginAs(9);

        self::assertFalse($this->subject->isAuthor($this->createTopic(5, $this->createFrontendUser(10))));
    }

    #[Test]
    public function isAuthorIsFalseWithoutFrontendUserOfRecord(): void
    {
        $this->loginAs(9);

        self::assertFalse($this->subject->isAuthor($this->createTopic(5)));
        self::assertFalse($this->subject->isAuthor($this->createPost(6)));
    }

    #[Test]
    public function isAuthorIsFalseIfNobodyIsLoggedInAndAuthorHasNoUid(): void
    {
        $this->loginAs(0);
        $author = new FrontendUser();
        $topic = new Topic();
        $topic->setFrontendUser($author);

        self::assertFalse($this->subject->isAuthor($topic));
    }

    #[Test]
    public function isAuthorIsFalseIfNobodyIsLoggedIn(): void
    {
        $this->loginAs(0);

        self::assertFalse($this->subject->isAuthor($this->createTopic(5, $this->createFrontendUser(9))));
    }

    #[Test]
    public function generateAccessTokenUsesTypeOfRecord(): void
    {
        $topic = $this->createTopic(5);
        $post = $this->createPost(5);

        self::assertSame($this->tokenService->generateToken('Topic', 5), $this->subject->generateAccessToken($topic));
        self::assertSame($this->tokenService->generateToken('Post', 5), $this->subject->generateAccessToken($post));
        self::assertNotSame($this->subject->generateAccessToken($topic), $this->subject->generateAccessToken($post));
    }
}
