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
use JWeiland\Pforum\Validation\Validator\EmailValidator;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Validation\Validator\ConjunctionValidator;
use TYPO3\CMS\Extbase\Validation\Validator\GenericObjectValidator;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;

/**
 * Add validator for email in topic- / post-records if it was configured in TypoScript
 */
#[AsEventListener(
    identifier: 'pforum/apply-email-as-mandatory-if-needed',
)]
final readonly class ApplyEmailAsMandatoryIfNeededEventListener
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

    private const VALIDATOR = EmailValidator::class;

    public function __construct(
        private ValidatorResolver $validatorResolver,
    ) {}

    public function __invoke(PreProcessControllerActionEvent $controllerActionEvent): void
    {
        if (!$this->isValidRequest($controllerActionEvent)) {
            return;
        }

        $usernameIsMandatory = (bool)($controllerActionEvent->getSettings()['emailIsMandatory'] ?? false);
        if (!$usernameIsMandatory) {
            return;
        }

        $argumentName = $this->getArgumentName($controllerActionEvent);
        if ($argumentName === '') {
            return;
        }

        $emailIsMandatoryValidator = $this->getValidator(
            self::VALIDATOR,
            [],
            $controllerActionEvent->getRequest(),
        );

        /** @var ConjunctionValidator $eventValidator */
        $eventValidator = $controllerActionEvent->getArguments()->getArgument($argumentName)->getValidator();
        /** @var ConjunctionValidator $conjunctionValidator */
        $conjunctionValidator = $eventValidator->getValidators()->current();
        /** @var GenericObjectValidator $genericEventValidator */
        $genericEventValidator = $conjunctionValidator->getValidators()->current();
        $genericEventValidator->addPropertyValidator(
            $this->getUsersPropertyName($controllerActionEvent->getRequest(), $argumentName),
            $emailIsMandatoryValidator,
        );
    }

    private function getUsersPropertyName(RequestInterface $request, string $argumentName): string
    {
        $requestedArgument = $this->getRequestedArgument($request, $argumentName);
        if ($requestedArgument === []) {
            return '';
        }

        if (array_key_exists('anonymousUser', $requestedArgument)) {
            return 'anonymousUser.email';
        }

        if (array_key_exists('frontendUser', $requestedArgument)) {
            return 'frontendUser.email';
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestedArgument(RequestInterface $request, string $argumentName): array
    {
        if ($argumentName === '') {
            return [];
        }

        if ($request->hasArgument($argumentName)) {
            return $request->getArgument($argumentName);
        }

        return [];
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
