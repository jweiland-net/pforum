<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Helper;

use TYPO3\CMS\Core\Context\Context;

/**
 * Helper to check FE groups for existing UID
 */
class FrontendGroupHelper
{
    public function __construct(
        private readonly Context $context,
    ) {}

    public function uidExistsInGroupData(int $groupUid): bool
    {
        if ($groupUid === 0) {
            return false;
        }

        return in_array($groupUid, $this->context->getPropertyFromAspect('frontend.user', 'groupIds'), true);
    }
}
