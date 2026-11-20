<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Domain\Finisher;

use JWeiland\Pforum\Form\RecordType;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Stores the topic of the topic form. Registered as "PforumSaveTopic".
 */
#[Autoconfigure(public: true, shared: false)]
class SaveTopicFinisher extends AbstractSaveRecordFinisher
{
    protected function getRecordType(): RecordType
    {
        return RecordType::Topic;
    }
}
