<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Domain;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;

/**
 * The records, which can be created, edited, activated and deleted in frontend
 */
enum RecordType: string
{
    case Topic = 'topic';
    case Post = 'post';

    public static function fromRecord(Topic|Post $record): self
    {
        return $record instanceof Topic ? self::Topic : self::Post;
    }

    /**
     * @param string $controllerName Name of the Extbase controller, e.g. "Topic"
     */
    public static function tryFromControllerName(string $controllerName): ?self
    {
        return self::tryFrom(lcfirst($controllerName));
    }

    public function getTable(): string
    {
        return 'tx_pforum_domain_model_' . $this->value;
    }

    /**
     * Name of the plugin argument of the record
     */
    public function getArgumentName(): string
    {
        return $this->value;
    }

    public function getControllerName(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Type of the token, which allows anonymous users to edit and delete their record
     */
    public function getAccessTokenType(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Type of the token, which allows anonymous users to activate their record
     */
    public function getActivationTokenType(): string
    {
        return ucfirst($this->value) . 'Activation';
    }

    /**
     * Label key (locallang.xlf) of a message about the record, e.g. "topicDeleted"
     *
     * @param string $action Past tense of the action, e.g. "Created", "Updated", "Deleted", "Activated"
     */
    public function getMessageKey(string $action): string
    {
        return $this->value . $action;
    }
}
