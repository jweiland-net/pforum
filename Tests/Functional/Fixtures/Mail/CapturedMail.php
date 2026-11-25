<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Fixtures\Mail;

/**
 * A mail, which was sent to the CapturingTransport
 */
final readonly class CapturedMail
{
    /**
     * @param string[] $recipients Email addresses
     * @param string[] $recipientNames Names of the recipients
     */
    public function __construct(
        public array $recipients,
        public array $recipientNames,
        public string $senderAddress,
        public string $senderName,
        public string $subject,
        public string $template,
        public string $textBody,
        public string $htmlBody,
    ) {}
}
