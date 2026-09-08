<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\EventListener;

use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\Traits\IsValidEventListenerRequestTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Overrides the uploadFolder of the #[FileUpload] attribute in Post/Topic with the value configured in
 * TypoScript/FlexForm, as attribute arguments cannot be filled with settings.
 *
 * @link https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/13.3/Feature-103511-IntroduceExtbaseFileUploadHandling.html#modifying-existing-configuration
 */
#[AsEventListener(
    identifier: 'pforum/apply-upload-folder',
)]
final readonly class ApplyUploadFolderEventListener
{
    use IsValidEventListenerRequestTrait;

    private const ALLOWED_CONTROLLER_ACTIONS = [
        'Topic' => [
            'create',
            'update',
        ],
        'Post' => [
            'create',
            'update',
        ],
    ];

    public function __invoke(PreProcessControllerActionEvent $controllerActionEvent): void
    {
        if (!$this->isValidRequest($controllerActionEvent)) {
            return;
        }

        $uploadFolder = (string)($controllerActionEvent->getSettings()['new']['uploadFolder'] ?? '');
        if ($uploadFolder === '') {
            return;
        }

        $argumentName = $this->getArgumentName($controllerActionEvent);
        if ($argumentName === '') {
            return;
        }

        $argument = $controllerActionEvent->getArguments()->getArgument($argumentName);
        $configuration = $argument
            ->getFileHandlingServiceConfiguration()
            ->getFileUploadConfigurationForProperty('images');
        $configuration?->setUploadFolder($uploadFolder);
    }

    private function getArgumentName(PreProcessControllerActionEvent $event): string
    {
        if ($event->getControllerName() === 'Topic') {
            return 'topic';
        }

        if ($event->getControllerName() === 'Post') {
            return 'post';
        }

        return '';
    }
}
