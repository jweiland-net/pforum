<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Configuration;

use JWeiland\Pforum\Configuration\PluginSettingsResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class PluginSettingsResolverTest extends UnitTestCase
{
    private PluginSettingsResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new PluginSettingsResolver();
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>, array<string, mixed>}>
     */
    public static function mergeDataProvider(): array
    {
        return [
            'flexform value is kept' => [['auth' => '2'], ['auth' => '1'], ['auth' => '2']],
            'empty string falls back to typoscript' => [['auth' => ''], ['auth' => '1'], ['auth' => '1']],
            'zero falls back to typoscript' => [['uidOfAdminGroup' => 0], ['uidOfAdminGroup' => 5], ['uidOfAdminGroup' => 5]],
            'string zero falls back to typoscript' => [['uidOfAdminGroup' => '0'], ['uidOfAdminGroup' => '5'], ['uidOfAdminGroup' => '5']],
            'null falls back to typoscript' => [['useImages' => null], ['useImages' => '1'], ['useImages' => '1']],
            'empty value without typoscript key stays empty' => [['auth' => ''], [], ['auth' => '']],
            'typoscript only keys are not added' => [[], ['auth' => '1'], []],
            'empty array is not replaced' => [['topic' => []], ['topic' => ['hideAtCreation' => '1']], ['topic' => []]],
            'filled array is not replaced' => [
                ['topic' => ['hideAtCreation' => '0']],
                ['topic' => ['hideAtCreation' => '1']],
                ['topic' => ['hideAtCreation' => '0']],
            ],
            'mixed' => [
                ['auth' => '', 'uidOfUserGroup' => '3', 'emailIsMandatory' => ''],
                ['auth' => '2', 'uidOfUserGroup' => '9', 'emailIsMandatory' => '1'],
                ['auth' => '2', 'uidOfUserGroup' => '3', 'emailIsMandatory' => '1'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $mergedSettings
     * @param array<string, mixed> $typoScriptSettings
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('mergeDataProvider')]
    public function mergeFillsEmptyValuesFromTypoScript(array $mergedSettings, array $typoScriptSettings, array $expected): void
    {
        self::assertSame($expected, $this->subject->merge($mergedSettings, $typoScriptSettings));
    }

    #[Test]
    public function resolveMergesFlexFormSettingsWithTypoScriptOnlySettings(): void
    {
        $configurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $configurationManager
            ->expects($this->exactly(2))
            ->method('getConfiguration')
            ->willReturnCallback(
                static function (string $type, ?string $extensionName = null, ?string $pluginName = null): array {
                    if ($extensionName === 'pforum' && $pluginName === 'doNotLoadFlexFormSettings') {
                        return ['auth' => '1', 'uidOfAdminGroup' => '7'];
                    }

                    return ['auth' => '', 'uidOfAdminGroup' => '8', 'useImages' => '1'];
                },
            );

        self::assertSame(
            ['auth' => '1', 'uidOfAdminGroup' => '8', 'useImages' => '1'],
            $this->subject->resolve($configurationManager),
        );
    }

    #[Test]
    public function resolveRequestsSettingsConfigurationType(): void
    {
        $types = [];
        $configurationManager = $this->createMock(ConfigurationManagerInterface::class);
        $configurationManager
            ->method('getConfiguration')
            ->willReturnCallback(
                static function (string $type) use (&$types): array {
                    $types[] = $type;

                    return [];
                },
            );

        self::assertSame([], $this->subject->resolve($configurationManager));
        self::assertSame(
            [ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS, ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS],
            $types,
        );
    }
}
