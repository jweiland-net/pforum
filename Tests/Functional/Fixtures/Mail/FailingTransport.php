<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Fixtures\Mail;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Mail transport, which can not send mails like an unreachable SMTP server
 */
final class FailingTransport implements TransportInterface
{
    /**
     * @param array<string, mixed> $mailSettings Settings of $GLOBALS['TYPO3_CONF_VARS']['MAIL']
     */
    public function __construct(array $mailSettings = []) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        throw new TransportException('Connection refused by the test transport', 1764000001);
    }

    public function __toString(): string
    {
        return 'pforum-failing://';
    }
}
