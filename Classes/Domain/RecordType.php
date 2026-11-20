<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Domain;

/**
 * The records, which can be created, edited, activated and deleted in frontend
 */
enum RecordType: string
{
    case Topic = 'topic';
    case Post = 'post';

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
}
