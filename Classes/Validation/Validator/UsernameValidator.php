<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Validation\Validator;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Username validator will only be executed if set in TypoScript
 */
final class UsernameValidator extends AbstractValidator
{
    /**
     * This validator always needs to be executed, even if the given value is empty.
     * See AbstractValidator::validate().
     */
    protected $acceptsEmptyValues = false;

    /**
     * @var array<string, array<int, mixed>>
     */
    protected $supportedOptions = [
        'usernameIsMandatory' => [false, 'Whether the email address is mandatory', 'bool'],
    ];

    /**
     * Checks if the username is given if configured in settings.
     */
    public function isValid(mixed $value): void
    {
        if (!$this->options['usernameIsMandatory']) {
            return;
        }

        if ((string)$value === '') {
            $this->addError(
                LocalizationUtility::translate(
                    'validator.anonymousUser.username',
                    'pforum',
                ),
                1378304890,
            );
        }
    }
}
