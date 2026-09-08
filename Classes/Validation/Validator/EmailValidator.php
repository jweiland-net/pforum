<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Validation\Validator;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Email validator that will only execute if a fe_user created a topic or posting
 */
final class EmailValidator extends AbstractValidator
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
        'emailIsMandatory' => [false, 'Whether the email address is mandatory', 'bool'],
    ];

    /**
     * Checks if the email is given if configured in settings.
     */
    public function isValid(mixed $value): void
    {
        if (!$this->options['emailIsMandatory']) {
            return;
        }

        if ((string)$value === '') {
            $this->addError(
                LocalizationUtility::translate('validator.anonymousUser.email', 'pforum'),
                1378288238,
            );
        } elseif (!GeneralUtility::validEmail((string)$value)) {
            $this->addError(
                LocalizationUtility::translate('validator.anonymousUser.validEmail', 'pforum'),
                1457431804,
            );
        }
    }
}
