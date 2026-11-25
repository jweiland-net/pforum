<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Updates;

use JWeiland\Pforum\Updates\PluginToContentElementUpdate;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardRegistry;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The column "list_type" of tt_content does not exist anymore in TYPO3 14.
 * So there is nothing to migrate in the test database.
 */
class PluginToContentElementUpdateTest extends FunctionalTestCase
{
    private const IDENTIFIER = 'pforum_migratePluginsToContentElementsUpdate';

    protected array $coreExtensionsToLoad = [
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
    }

    #[Test]
    public function wizardIsRegisteredWithIdentifier(): void
    {
        $registry = $this->get(UpgradeWizardRegistry::class);

        self::assertTrue($registry->hasUpgradeWizard(self::IDENTIFIER));
        self::assertInstanceOf(PluginToContentElementUpdate::class, $registry->getUpgradeWizard(self::IDENTIFIER));
    }

    #[Test]
    public function wizardHasTitleAndDescription(): void
    {
        $subject = $this->get(PluginToContentElementUpdate::class);

        self::assertSame('[pforum] Migrate plugins to Content Elements', $subject->getTitle());
        self::assertStringContainsString('content element (CType)', $subject->getDescription());
    }

    #[Test]
    public function wizardRequiresUpdatedDatabase(): void
    {
        self::assertContains(
            DatabaseUpdatedPrerequisite::class,
            $this->get(PluginToContentElementUpdate::class)->getPrerequisites(),
        );
    }

    #[Test]
    public function updateIsNotNecessaryIfThereIsNothingToMigrate(): void
    {
        self::assertFalse($this->get(PluginToContentElementUpdate::class)->updateNecessary());
    }

    #[Test]
    public function executeUpdateSucceedsIfThereIsNothingToMigrate(): void
    {
        $subject = $this->get(PluginToContentElementUpdate::class);

        self::assertTrue($subject->executeUpdate());
        self::assertFalse($subject->updateNecessary());
    }
}
