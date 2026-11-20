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
use JWeiland\Pforum\Form\RecordFormDataMapper;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Mail\NewPostMail;
use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use JWeiland\Pforum\Service\RecordAccessService;
use JWeiland\Pforum\Service\RecordService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Stores the post of the post form. Registered as "PforumSavePost".
 * The author of the topic gets a mail about a new visible post.
 */
#[Autoconfigure(public: true, shared: false)]
class SavePostFinisher extends AbstractSaveRecordFinisher
{
    public function __construct(
        RecordFormContextBuilder $recordFormContextBuilder,
        RecordAccessService $recordAccessService,
        RecordFormDataMapper $recordFormDataMapper,
        RecordService $recordService,
        AnonymousAccessTokenService $anonymousAccessTokenService,
        protected readonly NewPostMail $newPostMail,
    ) {
        parent::__construct(
            $recordFormContextBuilder,
            $recordAccessService,
            $recordFormDataMapper,
            $recordService,
            $anonymousAccessTokenService,
        );
    }

    protected function getRecordType(): RecordType
    {
        return RecordType::Post;
    }

    protected function afterSave(RecordFormContext $formContext, int $recordUid): void
    {
        if (!$formContext->isNew() || $formContext->settings->isHiddenAtCreation(RecordType::Post)) {
            return;
        }

        $this->newPostMail->send($recordUid, $this->finisherContext->getRequest());
    }
}
