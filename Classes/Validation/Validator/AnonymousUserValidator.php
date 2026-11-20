<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Validation\Validator;

use JWeiland\Pforum\Domain\Model\AnonymousUser;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validates the pforum user (anonymous user) of a topic or post. The email
 * address has to be valid, if given. Username and email address are
 * mandatory, if the options are set. The errors are added to the
 * properties "anonymousUser.username" and "anonymousUser.email", so they
 * are shown at the form fields.
 */
final class AnonymousUserValidator extends AbstractValidator
{
    /**
     * @var array<string, array<int, mixed>>
     */
    protected $supportedOptions = [
        'usernameIsMandatory' => [false, 'Whether the username is mandatory', 'bool'],
        'emailIsMandatory' => [false, 'Whether the email address is mandatory', 'bool'],
    ];

    public function isValid(mixed $value): void
    {
        if (!$value instanceof Topic && !$value instanceof Post) {
            return;
        }

        $anonymousUser = $value->getAnonymousUser() ?? new AnonymousUser();
        if ($this->options['usernameIsMandatory'] && trim($anonymousUser->getUsername()) === '') {
            $this->addErrorForProperty('anonymousUser.username', $this->translate('validator.anonymousUser.username'), 1378304890);
        }

        $email = trim($anonymousUser->getEmail());
        if ($email === '' && $this->options['emailIsMandatory']) {
            $this->addErrorForProperty('anonymousUser.email', $this->translate('validator.anonymousUser.email'), 1378288238);
        } elseif ($email !== '' && !GeneralUtility::validEmail($email)) {
            $this->addErrorForProperty('anonymousUser.email', $this->translate('validator.anonymousUser.validEmail'), 1457431804);
        }
    }

    private function translate(string $key): string
    {
        return (string)LocalizationUtility::translate($key, 'pforum');
    }
}
