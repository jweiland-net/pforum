<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Configuration;

use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Merges the settings of the FlexForm of the plugin with the settings of
 * TypoScript. Empty values of the FlexForm do not override the values of
 * TypoScript.
 */
final readonly class PluginSettingsResolver
{
    private const EXTENSION_NAME = 'pforum';

    /**
     * The settings of TypoScript only: a plugin name, which has no FlexForm
     */
    private const PLUGIN_NAME_WITHOUT_FLEX_FORM = 'doNotLoadFlexFormSettings';

    /**
     * @return array<string, mixed>
     */
    public function resolve(ConfigurationManagerInterface $configurationManager): array
    {
        $typoScriptSettings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            self::EXTENSION_NAME,
            self::PLUGIN_NAME_WITHOUT_FLEX_FORM,
        );

        return $this->merge(
            $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS),
            $typoScriptSettings,
        );
    }

    /**
     * @param array<string, mixed> $mergedSettings Settings of TypoScript overridden by the FlexForm
     * @param array<string, mixed> $typoScriptSettings Settings of TypoScript only
     * @return array<string, mixed>
     */
    public function merge(array $mergedSettings, array $typoScriptSettings): array
    {
        foreach ($mergedSettings as $key => $value) {
            if (!is_array($value) && empty($value) && array_key_exists($key, $typoScriptSettings)) {
                $mergedSettings[$key] = $typoScriptSettings[$key];
            }
        }

        return $mergedSettings;
    }
}
