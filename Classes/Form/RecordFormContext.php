<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Form;

use JWeiland\Pforum\Configuration\ForumSettings;

/**
 * Everything the form of a topic or post needs to know about the current
 * request: which record is created or edited and how the plugin is configured
 */
final readonly class RecordFormContext
{
    /**
     * @param int $recordUid UID of the record to edit. 0 for new records.
     * @param int $parentUid UID of the forum of a topic or the topic of a post
     * @param string $token Token of the mail link, which allows anonymous users to edit their record
     * @param int $pageUid UID of the current page
     */
    public function __construct(
        public RecordType $recordType,
        public int $recordUid,
        public int $parentUid,
        public string $token,
        public ForumSettings $settings,
        public int $pageUid = 0,
    ) {}

    public function isNew(): bool
    {
        return $this->recordUid === 0;
    }

    /**
     * The arguments of the plugin, which identify the record of the form
     *
     * @return array<string, int|string>
     */
    public function getPluginArguments(): array
    {
        if ($this->isNew()) {
            return [$this->recordType->getParentColumn() => $this->parentUid];
        }

        return array_filter([
            $this->recordType->getArgumentName() => $this->recordUid,
            'token' => $this->token,
        ]);
    }
}
