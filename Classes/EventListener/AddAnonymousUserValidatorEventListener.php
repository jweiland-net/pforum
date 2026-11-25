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
use JWeiland\Pforum\Validation\Validator\AnonymousUserValidator;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Validation\Validator\ConjunctionValidator;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;

/**
 * Adds the AnonymousUserValidator to the topic or post of the actions
 * "create" and "update", if topics and posts are created by pforum users
 * (anonymous users, settings.auth = 1).
 */
#[AsEventListener(
    identifier: 'pforum/add-anonymous-user-validator',
)]
final readonly class AddAnonymousUserValidatorEventListener
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

    public function __construct(
        private ValidatorResolver $validatorResolver,
    ) {}

    public function __invoke(PreProcessControllerActionEvent $event): void
    {
        $settings = $event->getSettings();
        $argumentName = strtolower($event->getControllerName());
        if (
            !$this->isValidRequest($event)
            || (int)($settings['auth'] ?? 1) !== 1
            || !$event->getArguments()->hasArgument($argumentName)
        ) {
            return;
        }

        $anonymousUserValidator = $this->validatorResolver->createValidator(
            AnonymousUserValidator::class,
            [
                'usernameIsMandatory' => (bool)($settings['usernameIsMandatory'] ?? false),
                'emailIsMandatory' => (bool)($settings['emailIsMandatory'] ?? false),
            ],
            $event->getRequest(),
        );

        $argument = $event->getArguments()->getArgument($argumentName);
        $validator = $argument->getValidator();
        if ($validator instanceof ConjunctionValidator) {
            $validator->addValidator($anonymousUserValidator);
            return;
        }

        $conjunctionValidator = GeneralUtility::makeInstance(ConjunctionValidator::class);
        if ($validator instanceof ValidatorInterface) {
            $conjunctionValidator->addValidator($validator);
        }
        $conjunctionValidator->addValidator($anonymousUserValidator);
        $argument->setValidator($conjunctionValidator);
    }
}
