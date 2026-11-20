<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * Reads the TypoScript settings of the Forum plugin outside of Extbase,
 * e.g. in the finishers and the factory of the forms based on EXT:form.
 * Like Extbase, plugin.tx_pforum_forum overrides plugin.tx_pforum.
 */
readonly class PluginSettingsService
{
    private const PLUGIN_KEYS = ['tx_pforum.', 'tx_pforum_forum.'];

    public function __construct(
        private TypoScriptService $typoScriptService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSettings(?ServerRequestInterface $request): array
    {
        $pluginSetup = $this->getPluginSetup($request);

        $settings = [];
        foreach (self::PLUGIN_KEYS as $pluginKey) {
            $pluginSettings = $pluginSetup[$pluginKey]['settings.'] ?? null;
            if (is_array($pluginSettings)) {
                ArrayUtility::mergeRecursiveWithOverrule(
                    $settings,
                    $this->typoScriptService->convertTypoScriptArrayToPlainArray($pluginSettings),
                );
            }
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPluginSetup(?ServerRequestInterface $request): array
    {
        $frontendTypoScript = $request?->getAttribute('frontend.typoscript');
        if (!$frontendTypoScript instanceof FrontendTypoScript) {
            return [];
        }

        try {
            $pluginSetup = $frontendTypoScript->getSetupArray()['plugin.'] ?? [];
        } catch (\RuntimeException) {
            // The setup is not available in cached frontend scope
            return [];
        }

        return is_array($pluginSetup) ? $pluginSetup : [];
    }
}
