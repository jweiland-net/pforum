<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\ViewHelper;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * ViewHelper to simplify the condition to show create button for topics and posts
 */
class IsCreateButtonAllowedViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('authType', 'int', 'The authentication type. 1 = None, 2 = Needs authentication.');
        $this->registerArgument('userGroupUid', 'int', 'The usergroup UID.');
    }

    public function render(): bool
    {
        $authenticationType = (int)$this->arguments['authType'];

        // Everyone can create new topics/posts
        if ($authenticationType === 1) {
            return true;
        }

        // Values other than 1 or 2 are not allowed
        if ($authenticationType !== 2) {
            return false;
        }

        // Following is a reduced version of f:security.isHasRole()
        $userGroupUid = (int)$this->arguments['userGroupUid'];

        $userAspect = self::getUserAspect();
        if (!$userAspect instanceof UserAspect) {
            return false;
        }

        if (!$userAspect->isLoggedIn()) {
            return false;
        }

        return in_array($userGroupUid, $userAspect->getGroupIds(), true);
    }

    protected static function getUserAspect(): ?UserAspect
    {
        try {
            return GeneralUtility::makeInstance(Context::class)->getAspect('frontend.user');
        } catch (AspectNotFoundException $aspectNotFoundException) {
            return null;
        }
    }
}
