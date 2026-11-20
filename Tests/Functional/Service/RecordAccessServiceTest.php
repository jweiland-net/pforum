<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Service;

use JWeiland\Pforum\Configuration\ForumSettings;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Service\RecordAccessService;
use JWeiland\Pforum\Tests\Functional\Traits\RecordFormRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case.
 */
class RecordAccessServiceTest extends FunctionalTestCase
{
    use RecordFormRequestTrait;

    protected array $coreExtensionsToLoad = [
        'form',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
    }

    #[Test]
    public function everyoneCanCreateInAnonymousMode(): void
    {
        self::assertSame('', $this->getSubject()->getCreationDenialReason(new ForumSettings(auth: 1)));
    }

    #[Test]
    public function frontendUserModeNeedsLogin(): void
    {
        self::assertSame('pluginNeedsLogin', $this->getSubject()->getCreationDenialReason(new ForumSettings(auth: 2)));
    }

    #[Test]
    public function frontendUserModeNeedsMembershipOfUserGroup(): void
    {
        $this->loginFrontendUser(6, [3]);

        self::assertSame(
            'notAllowedToCreate',
            $this->getSubject()->getCreationDenialReason(new ForumSettings(auth: 2, uidOfUserGroup: 1)),
        );
    }

    #[Test]
    public function memberOfUserGroupCanCreate(): void
    {
        $this->loginFrontendUser(6, [1]);

        self::assertSame('', $this->getSubject()->getCreationDenialReason(new ForumSettings(auth: 2, uidOfUserGroup: 1)));
    }

    #[Test]
    public function anonymousUserCanEditTopicWithValidToken(): void
    {
        self::assertSame(
            '',
            $this->getSubject()->getEditDenialReason(RecordType::Topic, 2, $this->generateToken('Topic', 2), new ForumSettings(auth: 1)),
        );
    }

    #[Test]
    public function anonymousUserCanNotEditPostWithTokenOfTopic(): void
    {
        self::assertSame(
            'unauthorizedUser',
            $this->getSubject()->getEditDenialReason(RecordType::Post, 2, $this->generateToken('Topic', 2), new ForumSettings(auth: 1)),
        );
    }

    #[Test]
    public function authorCanEditOwnTopicInFrontendUserMode(): void
    {
        $this->loginFrontendUser(5);

        self::assertSame('', $this->getSubject()->getEditDenialReason(RecordType::Topic, 1, '', new ForumSettings(auth: 2)));
    }

    #[Test]
    public function frontendUserCanNotEditTopicOfOtherUser(): void
    {
        $this->loginFrontendUser(6);

        self::assertSame(
            'unauthorizedUser',
            $this->getSubject()->getEditDenialReason(RecordType::Topic, 1, '', new ForumSettings(auth: 2)),
        );
    }

    #[Test]
    public function tokenOfAnonymousUserIsIgnoredInFrontendUserMode(): void
    {
        self::assertSame(
            'unauthorizedUser',
            $this->getSubject()->getEditDenialReason(RecordType::Topic, 2, $this->generateToken('Topic', 2), new ForumSettings(auth: 2)),
        );
    }

    #[Test]
    public function memberOfAdminGroupCanActivateWithoutToken(): void
    {
        $this->loginFrontendUser(7, [2]);

        self::assertTrue($this->getSubject()->isActivationAllowed(RecordType::Topic, 2, '', new ForumSettings(uidOfAdminGroup: 2)));
    }

    #[Test]
    public function authorCanActivateWithActivationToken(): void
    {
        self::assertTrue($this->getSubject()->isActivationAllowed(
            RecordType::Topic,
            2,
            $this->generateToken('TopicActivation', 2),
            $this->getUserActivationSettings(false),
        ));
    }

    #[Test]
    public function authorCanNotActivateWithTokenToEditTheRecord(): void
    {
        self::assertFalse($this->getSubject()->isActivationAllowed(
            RecordType::Topic,
            2,
            $this->generateToken('Topic', 2),
            $this->getUserActivationSettings(false),
        ));
    }

    #[Test]
    public function authorCanNotActivateIfAdminActivatesRecords(): void
    {
        self::assertFalse($this->getSubject()->isActivationAllowed(
            RecordType::Topic,
            2,
            $this->generateToken('TopicActivation', 2),
            $this->getUserActivationSettings(true),
        ));
    }

    #[Test]
    public function memberOfUserGroupCanNotActivate(): void
    {
        $this->loginFrontendUser(6, [1]);

        self::assertFalse($this->getSubject()->isActivationAllowed(RecordType::Topic, 2, '', new ForumSettings(uidOfAdminGroup: 2)));
    }

    private function getUserActivationSettings(bool $activateByAdmin): ForumSettings
    {
        return new ForumSettings(
            auth: 1,
            emailIsMandatory: true,
            hideAtCreation: ['topic' => true],
            activateByAdmin: ['topic' => $activateByAdmin],
        );
    }

    private function getSubject(): RecordAccessService
    {
        return $this->get(RecordAccessService::class);
    }
}
