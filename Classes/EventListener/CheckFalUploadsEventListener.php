<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\EventListener;

use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\Traits\IsValidEventListenerRequestTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;

/**
 * Adds the validator of EXT:checkfaluploads (mandatory rights checkbox) to
 * the image uploads of topics and posts, if the extension is installed
 */
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

    private const EXTENSION_KEY = 'checkfaluploads';

    /**
     * The validator of EXT:checkfaluploads. A string, as the extension is optional.
     */
    private const VALIDATOR = 'JWeiland\\Checkfaluploads\\Validation\\Validator\\CheckFalUploadValidator';

    public function __construct(
        private ValidatorResolver $validatorResolver,
        private PackageManager $packageManager,
    ) {}

    public function __invoke(PreProcessControllerActionEvent $controllerActionEvent): void
    {
        if (!$this->packageManager->isPackageActive(self::EXTENSION_KEY) || !$this->isValidRequest($controllerActionEvent)) {
            return;
        }

        $argumentName = RecordType::tryFromControllerName($controllerActionEvent->getControllerName())?->getArgumentName();
        if ($argumentName === null || !$controllerActionEvent->getArguments()->hasArgument($argumentName)) {
            return;
        }

        $checkFalUploadsValidator = $this->getValidator(
            ['propertyPath' => $argumentName . '.images'],
            $controllerActionEvent->getRequest(),
        );

        $argument = $controllerActionEvent->getArguments()->getArgument($argumentName);
        $configuration = $argument
            ->getFileHandlingServiceConfiguration()
            ->getFileUploadConfigurationForProperty('images');
        $configuration?->addValidator($checkFalUploadsValidator);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getValidator(array $options, RequestInterface $request): ValidatorInterface
    {
        /** @var class-string<ValidatorInterface> $className */
        $className = $this->getValidatorClassName();
        $validator = $this->validatorResolver->createValidator($className, $options, $request);
        if (!$validator instanceof ValidatorInterface) {
            throw new \RuntimeException(
                sprintf('The validator %s could not be created.', self::VALIDATOR),
                1764000002,
            );
        }

        return $validator;
    }

    private function getValidatorClassName(): string
    {
        return self::VALIDATOR;
    }
}
