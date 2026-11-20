<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Mail;

use TYPO3\CMS\Fluid\View\TemplatePaths;

/**
 * Template paths of the mails of pforum: the paths of
 * $GLOBALS['TYPO3_CONF_VARS']['MAIL'] and the template root paths of
 * TypoScript "settings.email.templateRootPaths". The paths of pforum are
 * added to the mails of pforum only.
 */
readonly class MailTemplatePathsFactory
{
    /**
     * @param array<string, mixed> $settings Plugin settings
     */
    public function create(array $settings): TemplatePaths
    {
        $ownTemplateRootPaths = $settings['email']['templateRootPaths'] ?? [];
        $ownTemplateRootPaths = is_array($ownTemplateRootPaths) ? $ownTemplateRootPaths : [];
        ksort($ownTemplateRootPaths);

        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths([
            ...array_values($GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'] ?? []),
            ...array_values($ownTemplateRootPaths),
        ]);
        $templatePaths->setLayoutRootPaths($GLOBALS['TYPO3_CONF_VARS']['MAIL']['layoutRootPaths'] ?? []);
        $templatePaths->setPartialRootPaths($GLOBALS['TYPO3_CONF_VARS']['MAIL']['partialRootPaths'] ?? []);

        return $templatePaths;
    }
}
