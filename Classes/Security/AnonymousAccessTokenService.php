<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Security;

use TYPO3\CMS\Extbase\Security\Cryptography\HashService;
use TYPO3\CMS\Extbase\Security\Exception\InvalidArgumentForHashGenerationException;
use TYPO3\CMS\Extbase\Security\Exception\InvalidHashException;

class AnonymousAccessTokenService
{
    private const ADDITIONAL_SECRET = 'pforum-anonymous-access';

    public function __construct(
        private readonly HashService $hashService,
    ) {}

    public function generateToken(string $type, int $uid): string
    {
        return $this->hashService->appendHmac($this->buildPayload($type, $uid));
    }

    public function isTokenValid(string $type, int $uid, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $payload = $this->hashService->validateAndStripHmac($token);
        } catch (InvalidArgumentForHashGenerationException | InvalidHashException) {
            return false;
        }

        return $payload === $this->buildPayload($type, $uid);
    }

    private function buildPayload(string $type, int $uid): string
    {
        return self::ADDITIONAL_SECRET . ':' . $type . ':' . $uid;
    }
}
