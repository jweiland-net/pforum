<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Security;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Exception\Crypto\InvalidHashStringException;

class AnonymousAccessTokenService
{
    private const ADDITIONAL_SECRET = 'pforum-anonymous-access';

    public function __construct(
        private readonly HashService $hashService,
    ) {}

    public function generateToken(string $type, int $uid): string
    {
        return $this->hashService->appendHmac($this->buildPayload($type, $uid), self::ADDITIONAL_SECRET);
    }

    public function isTokenValid(string $type, int $uid, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $payload = $this->hashService->validateAndStripHmac($token, self::ADDITIONAL_SECRET);
        } catch (InvalidHashStringException) {
            return false;
        }

        return $payload === $this->buildPayload($type, $uid);
    }

    private function buildPayload(string $type, int $uid): string
    {
        return $type . ':' . $uid;
    }
}
