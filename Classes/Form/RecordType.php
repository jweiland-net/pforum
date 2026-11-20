<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Form;

/**
 * The records, which can be created and edited with the forms of pforum.
 * The value is set as rendering option "recordType" in the form definition.
 */
enum RecordType: string
{
    case Topic = 'topic';
    case Post = 'post';

    public const RENDERING_OPTION = 'recordType';

    /**
     * Returns null for forms of other extensions and for unknown values
     *
     * @param array<string, mixed> $configuration Form definition
     */
    public static function fromConfiguration(array $configuration): ?self
    {
        $recordType = $configuration['renderingOptions'][self::RENDERING_OPTION] ?? '';

        return is_string($recordType) ? self::tryFrom($recordType) : null;
    }

    public function getTable(): string
    {
        return 'tx_pforum_domain_model_' . $this->value;
    }

    /**
     * Column with the UID of the parent record. It is the name of the plugin
     * argument with the UID of the parent record, too.
     */
    public function getParentColumn(): string
    {
        return match ($this) {
            self::Topic => 'forum',
            self::Post => 'topic',
        };
    }

    public function getParentTable(): string
    {
        return 'tx_pforum_domain_model_' . $this->getParentColumn();
    }

    /**
     * Column of the parent table, which counts the child records (TCA type "inline")
     */
    public function getParentCounterColumn(): string
    {
        return $this->value . 's';
    }

    /**
     * Name of the plugin argument with the UID of the record to edit
     */
    public function getArgumentName(): string
    {
        return $this->value;
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

    public function getControllerName(): string
    {
        return ucfirst($this->value);
    }
}
