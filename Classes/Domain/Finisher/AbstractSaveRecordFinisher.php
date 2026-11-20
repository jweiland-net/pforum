<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Domain\Finisher;

use JWeiland\Pforum\Form\RecordFormContext;
use JWeiland\Pforum\Form\RecordFormContextBuilder;
use JWeiland\Pforum\Form\RecordFormData;
use JWeiland\Pforum\Form\RecordFormDataMapper;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Service\RecordAccessService;
use JWeiland\Pforum\Service\RecordService;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use TYPO3\CMS\Form\Domain\Finishers\Exception\FinisherException;

/**
 * Stores the topic or post of the form. The access is checked again, as
 * the form could be submitted without the controller action of the form.
 *
 * Finisher variables for the following finishers (e.g. the mail):
 * - recordUid: UID of the stored record
 * - accessToken: Token for the links to edit and delete the record
 * - activationToken: Token for the link to activate the record. Empty, if
 *   the author is not allowed to activate the record.
 */
abstract class AbstractSaveRecordFinisher extends AbstractFinisher
{
    public function __construct(
        protected readonly RecordFormContextBuilder $recordFormContextBuilder,
        protected readonly RecordAccessService $recordAccessService,
        protected readonly RecordFormDataMapper $recordFormDataMapper,
        protected readonly RecordService $recordService,
        protected readonly AnonymousAccessTokenService $anonymousAccessTokenService,
    ) {}

    abstract protected function getRecordType(): RecordType;

    /**
     * Called after the record was stored
     */
    protected function afterSave(RecordFormContext $formContext, int $recordUid): void {}

    /**
     * @throws FinisherException
     */
    protected function executeInternal(): ?string
    {
        $formContext = $this->recordFormContextBuilder->build($this->getRecordType(), $this->finisherContext->getRequest());
        $this->throwExceptionIfAccessIsDenied($this->recordAccessService->getFormDenialReason($formContext));

        $formData = $this->getRecordFormData($formContext);
        $recordUid = $formContext->recordUid;
        if ($formContext->isNew()) {
            $recordUid = $this->recordService->create(
                $formContext->recordType,
                $formData,
                $formContext->parentUid,
                $formContext->settings->isHiddenAtCreation($formContext->recordType),
                $formContext->settings->isFrontendUserMode() ? $this->recordAccessService->getFrontendUserUid() : 0,
            );
        } else {
            $this->recordService->update($formContext->recordType, $formData, $recordUid);
        }

        $this->addFinisherVariables($formContext, $recordUid);
        $this->afterSave($formContext, $recordUid);

        return null;
    }

    /**
     * @throws FinisherException
     */
    protected function throwExceptionIfAccessIsDenied(string $denialReason): void
    {
        if ($denialReason === '') {
            return;
        }

        $this->finisherContext->cancel();

        throw new FinisherException(
            'The current user is not allowed to create or edit this record in frontend: ' . $denialReason,
            1763890801,
        );
    }

    protected function getRecordFormData(RecordFormContext $formContext): RecordFormData
    {
        $elements = $this->parseOption('elements');

        return $this->recordFormDataMapper->map(
            $this->finisherContext->getFormRuntime()->getFormDefinition(),
            $this->finisherContext->getFormValues(),
            is_array($elements) ? $elements : [],
            $formContext,
        );
    }

    protected function addFinisherVariables(RecordFormContext $formContext, int $recordUid): void
    {
        $recordType = $formContext->recordType;
        $activationToken = $formContext->isNew() && $formContext->settings->isActivatedByUser($recordType)
            ? $this->anonymousAccessTokenService->generateToken($recordType->getActivationTokenType(), $recordUid)
            : '';

        $finisherVariableProvider = $this->finisherContext->getFinisherVariableProvider();
        $finisherVariableProvider->add($this->shortFinisherIdentifier, 'recordUid', $recordUid);
        $finisherVariableProvider->add(
            $this->shortFinisherIdentifier,
            'accessToken',
            $this->anonymousAccessTokenService->generateToken($recordType->getAccessTokenType(), $recordUid),
        );
        $finisherVariableProvider->add($this->shortFinisherIdentifier, 'activationToken', $activationToken);
    }
}
