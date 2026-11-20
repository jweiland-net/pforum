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
use JWeiland\Pforum\Service\PluginSettingsService;
use JWeiland\Pforum\Service\RecordAccessService;
use JWeiland\Pforum\Service\RecordService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Routing\PageArguments;

/**
 * Builds the RecordFormContext of the form of a topic or post from the
 * arguments and the TypoScript settings of the Forum plugin
 */
readonly class RecordFormContextBuilder
{
    public function __construct(
        private PluginSettingsService $pluginSettingsService,
        private RecordAccessService $recordAccessService,
        private RecordService $recordService,
    ) {}

    public function build(RecordType $recordType, ServerRequestInterface $request): RecordFormContext
    {
        $recordUid = $this->recordAccessService->getRecordUid($request, $recordType);
        $pageArguments = $request->getAttribute('routing');

        return new RecordFormContext(
            $recordType,
            $recordUid,
            $recordUid > 0
                ? (int)($this->recordService->findRecord($recordType, $recordUid)[$recordType->getParentColumn()] ?? 0)
                : $this->recordAccessService->getParentUid($request, $recordType),
            $recordUid > 0 ? $this->recordAccessService->getToken($request) : '',
            ForumSettings::fromArray($this->pluginSettingsService->getSettings($request)),
            $pageArguments instanceof PageArguments ? $pageArguments->getPageId() : 0,
        );
    }
}
