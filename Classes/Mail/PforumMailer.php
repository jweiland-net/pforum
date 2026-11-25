<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Mail;

use JWeiland\Pforum\Configuration\ExtConf;
use JWeiland\Pforum\Domain\Model\User;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sends the mails of pforum (Fluid templates "Pforum*") to a pforum user.
 * The sender is configured in the extension settings or in
 * $GLOBALS['TYPO3_CONF_VARS']['MAIL']. Failures are logged, they never
 * break the request.
 */
readonly class PforumMailer
{
    public function __construct(
        protected MailTemplatePathsFactory $mailTemplatePathsFactory,
        protected ExtConf $extConf,
        protected MailerInterface $mailer,
        protected LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $variables Variables of the template
     * @param array<string, mixed> $settings Plugin settings
     * @return bool TRUE, if the mail was sent
     */
    public function send(
        User $recipient,
        string $subject,
        string $template,
        array $variables,
        array $settings,
        ServerRequestInterface $request,
    ): bool {
        if (!GeneralUtility::validEmail($recipient->getEmail())) {
            $this->logger->info('Mail "{template}" not sent: pforum user {uid} has no valid email address', [
                'template' => $template,
                'uid' => $recipient->getUid(),
            ]);

            return false;
        }

        try {
            $email = GeneralUtility::makeInstance(FluidEmail::class, $this->mailTemplatePathsFactory->create($settings));
            $email
                ->to(new Address($recipient->getEmail(), $recipient->getName()))
                ->from(new Address($this->extConf->getEmailFromAddress(), $this->extConf->getEmailFromName()))
                ->subject($subject)
                ->setRequest($request)
                ->setTemplate($template)
                ->assignMultiple(array_replace(['settings' => $settings], $variables));

            $this->mailer->send($email);
        } catch (TransportExceptionInterface|\InvalidArgumentException|\RuntimeException $exception) {
            // Missing sender, transport errors and errors of the Fluid template (e.g. a missing template)
            $this->logger->error('Mail "{template}" could not be sent: {message}', [
                'template' => $template,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return false;
        }

        $this->logger->info('Mail "{template}" sent to pforum user {uid}', ['template' => $template, 'uid' => $recipient->getUid()]);

        return true;
    }
}
