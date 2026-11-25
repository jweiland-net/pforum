<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Security;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Exception\Crypto\InvalidHashStringException;

/**
 * Tokens of the links in the mails of anonymous users. They allow editing,
 * deleting and activating one record.
 *
 * New tokens are signed with SHA3-256. Tokens of mails sent by former
 * versions of pforum are signed with SHA1 and stay valid.
 */
final readonly class AnonymousAccessTokenService
{
    private const ADDITIONAL_SECRET = 'pforum-anonymous-access';

    private const ALGORITHM = HashAlgo::SHA3_256;

    private const LEGACY_ALGORITHM = HashAlgo::SHA1;

    public function __construct(
        private HashService $hashService,
        private LoggerInterface $logger,
    ) {}

    public function generateToken(string $type, int $uid): string
    {
        return $this->hashService->appendHmac($this->buildPayload($type, $uid), self::ADDITIONAL_SECRET, self::ALGORITHM);
    }

    public function isTokenValid(string $type, int $uid, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $expectedPayload = $this->buildPayload($type, $uid);
        foreach ([self::ALGORITHM, self::LEGACY_ALGORITHM] as $algorithm) {
            if ($this->stripHmac($token, $algorithm) === $expectedPayload) {
                return true;
            }
        }

        $this->logger->notice('Invalid access token for {type} {uid}', ['type' => $type, 'uid' => $uid]);

        return false;
    }

    private function stripHmac(string $token, HashAlgo $algorithm): ?string
    {
        try {
            return $this->hashService->validateAndStripHmac($token, self::ADDITIONAL_SECRET, $algorithm);
        } catch (InvalidHashStringException) {
            return null;
        }
    }

    private function buildPayload(string $type, int $uid): string
    {
        return $type . ':' . $uid;
    }
}
