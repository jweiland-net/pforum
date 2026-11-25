<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Security;

use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class AnonymousAccessTokenServiceTest extends UnitTestCase
{
    private const SECRET = 'pforum-anonymous-access';

    private HashService $hashService;

    private LoggerInterface&MockObject $logger;

    private AnonymousAccessTokenService $subject;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $sysConfigurationBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sysConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['SYS'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'unit-test-encryption-key-0123456789abcdef';

        $this->hashService = new HashService();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subject = new AnonymousAccessTokenService($this->hashService, $this->logger);
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

    #[Test]
    public function generatedTokenIsValidForSameTypeAndUid(): void
    {
        $this->logger->expects($this->never())->method('notice');

        $token = $this->subject->generateToken('Topic', 12);

        self::assertTrue($this->subject->isTokenValid('Topic', 12, $token));
    }

    #[Test]
    public function generatedTokenStartsWithPayloadAndEndsWithSha3Hmac(): void
    {
        $token = $this->subject->generateToken('Topic', 12);

        self::assertStringStartsWith('Topic:12', $token);
        // SHA3-256 results in 64 hex characters
        self::assertMatchesRegularExpression('/^Topic:12[0-9a-f]{64}$/', $token);
    }

    #[Test]
    public function generatedTokenDiffersForTypeAndUid(): void
    {
        self::assertNotSame($this->subject->generateToken('Topic', 1), $this->subject->generateToken('Topic', 2));
        self::assertNotSame($this->subject->generateToken('Topic', 1), $this->subject->generateToken('Post', 1));
        self::assertSame($this->subject->generateToken('Topic', 1), $this->subject->generateToken('Topic', 1));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function wrongTypeOrUidDataProvider(): array
    {
        return [
            'wrong type' => ['Post', 12],
            'activation type' => ['TopicActivation', 12],
            'wrong uid' => ['Topic', 13],
            'both wrong' => ['Post', 1],
        ];
    }

    #[Test]
    #[DataProvider('wrongTypeOrUidDataProvider')]
    public function tokenIsInvalidForOtherTypeOrUid(string $type, int $uid): void
    {
        $token = $this->subject->generateToken('Topic', 12);

        self::assertFalse($this->subject->isTokenValid($type, $uid, $token));
    }

    #[Test]
    public function emptyTokenIsInvalidWithoutLogging(): void
    {
        $this->logger->expects($this->never())->method('notice');

        self::assertFalse($this->subject->isTokenValid('Topic', 12, ''));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function garbageTokenDataProvider(): array
    {
        return [
            'text' => ['garbage'],
            'payload only' => ['Topic:12'],
            'payload with wrong hmac' => ['Topic:12' . '0000000000000000000000000000000000000000000000000000000000000000'],
            'short hmac' => ['Topic:12abc'],
            'whitespace' => [' '],
        ];
    }

    #[Test]
    #[DataProvider('garbageTokenDataProvider')]
    public function garbageTokenIsInvalidAndNoticeIsLogged(string $token): void
    {
        $this->logger
            ->expects($this->once())
            ->method('notice')
            ->with('Invalid access token for {type} {uid}', ['type' => 'Topic', 'uid' => 12]);

        self::assertFalse($this->subject->isTokenValid('Topic', 12, $token));
    }

    #[Test]
    public function tokenOfOtherEncryptionKeyIsInvalid(): void
    {
        $token = $this->subject->generateToken('Topic', 12);
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = 'another-encryption-key-0123456789abcdef';
        $this->logger->expects($this->once())->method('notice');

        self::assertFalse($this->subject->isTokenValid('Topic', 12, $token));
    }

    #[Test]
    public function legacySha1TokenIsStillValid(): void
    {
        $legacyToken = $this->hashService->appendHmac('Topic:12', self::SECRET, HashAlgo::SHA1);
        $this->logger->expects($this->never())->method('notice');

        // SHA1 results in 40 hex characters
        self::assertMatchesRegularExpression('/^Topic:12[0-9a-f]{40}$/', $legacyToken);
        self::assertTrue($this->subject->isTokenValid('Topic', 12, $legacyToken));
    }

    #[Test]
    public function legacySha1TokenIsInvalidForOtherRecord(): void
    {
        $legacyToken = $this->hashService->appendHmac('Topic:12', self::SECRET, HashAlgo::SHA1);
        $this->logger->expects($this->once())->method('notice');

        self::assertFalse($this->subject->isTokenValid('Topic', 99, $legacyToken));
    }

    #[Test]
    public function tokenSignedWithOtherSecretIsInvalid(): void
    {
        $token = $this->hashService->appendHmac('Topic:12', 'another-secret', HashAlgo::SHA3_256);
        $this->logger->expects($this->once())->method('notice');

        self::assertFalse($this->subject->isTokenValid('Topic', 12, $token));
    }
}
