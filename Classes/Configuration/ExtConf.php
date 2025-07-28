<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Configuration;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class ExtConf
 */
#[Autoconfigure(constructor: 'create')]
readonly class ExtConf implements SingletonInterface
{
    private const EXT_KEY = 'pforum';

    private const DEFAULT_SETTINGS = [
        'emailFromAddress' => '',
        'emailFromName' => '',
    ];

    public function __construct(
        private string $emailFromAddress = self::DEFAULT_SETTINGS['emailFromAddress'],
        private string $emailFromName = self::DEFAULT_SETTINGS['emailFromName'],
    ) {}

    public static function create(ExtensionConfiguration $extensionConfiguration): self
    {
        $extensionSettings = self::DEFAULT_SETTINGS;

        // Overwrite default extension settings with values from EXT_CONF
        try {
            $extensionSettings = array_merge(
                $extensionSettings,
                $extensionConfiguration->get(self::EXT_KEY),
            );
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
        }

        return new self(
            emailFromAddress: (string)$extensionSettings['emailFromAddress'],
            emailFromName: (string)$extensionSettings['emailFromName'],
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getEmailFromAddress(): string
    {
        if ($this->emailFromAddress === '') {
            $senderMail = (string)$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'];
            if ($senderMail === '') {
                throw new \InvalidArgumentException('You have forgotten to set a sender email address in extension settings of pforum or in install tool', 1604694223);
            }

            return $senderMail;
        }

        return $this->emailFromAddress;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function getEmailFromName(): string
    {
        if ($this->emailFromName === '') {
            $senderName = (string)$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'];
            if ($senderName === '') {
                throw new \InvalidArgumentException('You have forgotten to set a sender name in extension settings of pforum or in install tool', 1604694279);
            }

            return $senderName;
        }

        return $this->emailFromName;
    }
}
