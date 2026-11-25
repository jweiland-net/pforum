<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Configuration;

use JWeiland\Pforum\Configuration\ExtConf;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class ExtConfTest extends UnitTestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $mailConfigurationBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['MAIL'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = ['defaultMailFromAddress' => '', 'defaultMailFromName' => ''];
    }

    protected function tearDown(): void
    {
        if ($this->mailConfigurationBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['MAIL']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = $this->mailConfigurationBackup;
        }

        parent::tearDown();
    }

    #[Test]
    public function createReadsExtensionConfiguration(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration
            ->expects($this->once())
            ->method('get')
            ->with('pforum')
            ->willReturn(['emailFromAddress' => 'forum@example.com', 'emailFromName' => 'Forum']);

        $subject = ExtConf::create($extensionConfiguration);

        self::assertSame('forum@example.com', $subject->getEmailFromAddress());
        self::assertSame('Forum', $subject->getEmailFromName());
    }

    #[Test]
    public function createKeepsDefaultsForMissingKeys(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Install Tool';
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn(['emailFromAddress' => 'forum@example.com']);

        $subject = ExtConf::create($extensionConfiguration);

        self::assertSame('forum@example.com', $subject->getEmailFromAddress());
        self::assertSame('Install Tool', $subject->getEmailFromName());
    }

    #[Test]
    public function createCastsValuesToString(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn(['emailFromAddress' => 123, 'emailFromName' => 456]);

        $subject = ExtConf::create($extensionConfiguration);

        self::assertSame('123', $subject->getEmailFromAddress());
        self::assertSame('456', $subject->getEmailFromName());
    }

    #[Test]
    public function createUsesDefaultsIfExtensionIsNotConfigured(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willThrowException(new ExtensionConfigurationExtensionNotConfiguredException('', 1));
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'install@example.com';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Install Tool';

        $subject = ExtConf::create($extensionConfiguration);

        self::assertSame('install@example.com', $subject->getEmailFromAddress());
        self::assertSame('Install Tool', $subject->getEmailFromName());
    }

    #[Test]
    public function createUsesDefaultsIfPathDoesNotExist(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willThrowException(new ExtensionConfigurationPathDoesNotExistException('', 1));
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'install@example.com';

        $subject = ExtConf::create($extensionConfiguration);

        self::assertSame('install@example.com', $subject->getEmailFromAddress());
    }

    #[Test]
    public function extensionSettingsTakePrecedenceOverInstallToolSettings(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'install@example.com';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Install Tool';

        $subject = new ExtConf('forum@example.com', 'Forum');

        self::assertSame('forum@example.com', $subject->getEmailFromAddress());
        self::assertSame('Forum', $subject->getEmailFromName());
    }

    #[Test]
    public function getEmailFromAddressFallsBackToInstallToolSetting(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'install@example.com';

        self::assertSame('install@example.com', (new ExtConf())->getEmailFromAddress());
    }

    #[Test]
    public function getEmailFromAddressThrowsExceptionIfNoAddressIsConfigured(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1604694223);

        (new ExtConf(emailFromName: 'Forum'))->getEmailFromAddress();
    }

    #[Test]
    public function getEmailFromAddressThrowsExceptionIfInstallToolAddressIsEmptyString(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = '';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1604694223);

        (new ExtConf())->getEmailFromAddress();
    }

    #[Test]
    public function getEmailFromNameFallsBackToInstallToolSetting(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromName'] = 'Install Tool';

        self::assertSame('Install Tool', (new ExtConf())->getEmailFromName());
    }

    #[Test]
    public function getEmailFromNameThrowsExceptionIfNoNameIsConfigured(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1604694279);

        (new ExtConf(emailFromAddress: 'forum@example.com'))->getEmailFromName();
    }
}
