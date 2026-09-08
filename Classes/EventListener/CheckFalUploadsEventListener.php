<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\EventListener;

use JWeiland\Checkfaluploads\Validation\Validator\CheckFalUploadValidator;
use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\Traits\IsValidEventListenerRequestTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;

#[AsEventListener(
    identifier: 'pforum/assign-media-type-converter-for-topic',
)]
final readonly class CheckFalUploadsEventListener
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

    private const VALIDATOR = CheckFalUploadValidator::class;

    public function __construct(
        private ValidatorResolver $validatorResolver,
    ) {}

    public function __invoke(PreProcessControllerActionEvent $controllerActionEvent): void
    {
        if (!ExtensionManagementUtility::isLoaded('checkfaluploads')) {
            return;
        }

        if (!$this->isValidRequest($controllerActionEvent)) {
            return;
        }

        $argumentName = $this->getArgumentName($controllerActionEvent);
        if ($argumentName === '') {
            return;
        }

        $checkFalUploadsValidator = $this->getValidator(
            self::VALIDATOR,
            ['propertyPath' => $argumentName . '.images'],
            $controllerActionEvent->getRequest(),
        );

        $argument = $controllerActionEvent->getArguments()->getArgument($argumentName);
        $configuration = $argument
            ->getFileHandlingServiceConfiguration()
            ->getFileUploadConfigurationForProperty('images');
        $configuration?->addValidator($checkFalUploadsValidator);
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

    /**
     * @param array<string, mixed> $options
     */
    private function getValidator(
        string $className,
        array $options,
        RequestInterface $request,
    ): ValidatorInterface {
        return $this->validatorResolver->createValidator($className, $options, $request);
    }
}
