<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Checkfaluploads\Validation\Validator;

use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Test double of the validator of EXT:checkfaluploads. pforum only
 * configures it with the option "propertyPath".
 */
class CheckFalUploadValidator extends AbstractValidator
{
    protected $supportedOptions = [
        'propertyPath' => ['', 'Path of the property of the uploaded files in the submitted form', 'string', true],
    ];

    protected function isValid(mixed $value): void {}
}
