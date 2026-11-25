<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Mail;

use JWeiland\Pforum\Utility\TypeCaster;
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
        $ownTemplateRootPaths = TypeCaster::toArray(
            TypeCaster::toArray($settings['email'] ?? [])['templateRootPaths'] ?? [],
        );
        ksort($ownTemplateRootPaths);

        $mailConfiguration = TypeCaster::toArray(TypeCaster::toArray($GLOBALS['TYPO3_CONF_VARS'] ?? [])['MAIL'] ?? []);

        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths($this->toStringList([
            ...array_values(TypeCaster::toArray($mailConfiguration['templateRootPaths'] ?? [])),
            ...array_values($ownTemplateRootPaths),
        ]));
        $templatePaths->setLayoutRootPaths($this->toStringList($mailConfiguration['layoutRootPaths'] ?? []));
        $templatePaths->setPartialRootPaths($this->toStringList($mailConfiguration['partialRootPaths'] ?? []));

        return $templatePaths;
    }

    /**
     * @return array<string>
     */
    private function toStringList(mixed $paths): array
    {
        return array_map(TypeCaster::toString(...), TypeCaster::toArray($paths));
    }
}
