<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Domain\Repository;

use JWeiland\Pforum\Domain\Model\AnonymousUser;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Repo to handle anonymous users
 *
 * @extends Repository<AnonymousUser>
 */
class AnonymousUserRepository extends Repository {}
