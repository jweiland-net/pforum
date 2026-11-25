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
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Core\Mail\FluidEmail;

/**
 * Mail transport, which keeps the sent mails in memory. Use it as
 * $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] or inject it into a mailer.
 */
final class CapturingTransport implements TransportInterface
{
    /**
     * @var CapturedMail[]
     */
    private static array $mails = [];

    /**
     * @param array<string, mixed> $mailSettings Settings of $GLOBALS['TYPO3_CONF_VARS']['MAIL']
     */
    public function __construct(array $mailSettings = []) {}

    /**
     * @return CapturedMail[]
     */
    public static function getMails(): array
    {
        return self::$mails;
    }

    public static function reset(): void
    {
        self::$mails = [];
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if ($message instanceof Email) {
            self::$mails[] = $this->capture($message);
        }

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'pforum-capturing://';
    }

    private function capture(Email $email): CapturedMail
    {
        $templateProperty = new \ReflectionProperty(FluidEmail::class, 'templateName');

        return new CapturedMail(
            array_map(static fn(Address $address): string => $address->getAddress(), $email->getTo()),
            array_map(static fn(Address $address): string => $address->getName(), $email->getTo()),
            ($email->getFrom()[0] ?? new Address('missing@example.com'))->getAddress(),
            ($email->getFrom()[0] ?? new Address('missing@example.com'))->getName(),
            (string)$email->getSubject(),
            $email instanceof FluidEmail ? (string)$templateProperty->getValue($email) : '',
            (string)$email->getTextBody(),
            (string)$email->getHtmlBody(),
        );
    }
}
