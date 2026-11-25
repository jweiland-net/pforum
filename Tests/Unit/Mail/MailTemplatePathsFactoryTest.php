<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Mail;

use JWeiland\Pforum\Mail\MailTemplatePathsFactory;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class MailTemplatePathsFactoryTest extends UnitTestCase
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $mailConfigurationBackup = null;

    private MailTemplatePathsFactory $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['MAIL'] ?? null;
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = [
            'templateRootPaths' => [0 => '/var/mail/core-templates/', 10 => '/var/mail/templates/'],
            'layoutRootPaths' => [0 => '/var/mail/layouts/'],
            'partialRootPaths' => [0 => '/var/mail/partials/'],
        ];

        $this->subject = new MailTemplatePathsFactory();
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
    public function createUsesGlobalMailPathsWithoutPluginSettings(): void
    {
        $templatePaths = $this->subject->create([]);

        self::assertSame(
            ['/var/mail/core-templates/', '/var/mail/templates/'],
            array_values($templatePaths->getTemplateRootPaths()),
        );
        self::assertSame(['/var/mail/layouts/'], array_values($templatePaths->getLayoutRootPaths()));
        self::assertSame(['/var/mail/partials/'], array_values($templatePaths->getPartialRootPaths()));
    }

    #[Test]
    public function createAppendsTemplateRootPathsOfPluginSortedByKey(): void
    {
        $templatePaths = $this->subject->create([
            'email' => ['templateRootPaths' => [20 => '/own/b/', 5 => '/own/a/']],
        ]);

        self::assertSame(
            ['/var/mail/core-templates/', '/var/mail/templates/', '/own/a/', '/own/b/'],
            array_values($templatePaths->getTemplateRootPaths()),
        );
    }

    #[Test]
    public function createIgnoresInvalidPluginTemplateRootPaths(): void
    {
        $templatePaths = $this->subject->create(['email' => ['templateRootPaths' => 'not-an-array']]);

        self::assertCount(2, $templatePaths->getTemplateRootPaths());
    }

    #[Test]
    public function createDoesNotChangeLayoutAndPartialPathsByPluginSettings(): void
    {
        $templatePaths = $this->subject->create([
            'email' => ['layoutRootPaths' => [10 => '/own/layouts/'], 'partialRootPaths' => [10 => '/own/partials/']],
        ]);

        self::assertSame(['/var/mail/layouts/'], array_values($templatePaths->getLayoutRootPaths()));
        self::assertSame(['/var/mail/partials/'], array_values($templatePaths->getPartialRootPaths()));
    }

    #[Test]
    public function createWorksWithoutGlobalMailPaths(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL'] = [];

        $templatePaths = $this->subject->create(['email' => ['templateRootPaths' => [0 => '/own/']]]);

        self::assertSame(['/own/'], array_values($templatePaths->getTemplateRootPaths()));
        self::assertSame([], $templatePaths->getLayoutRootPaths());
        self::assertSame([], $templatePaths->getPartialRootPaths());
    }
}
