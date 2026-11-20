<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Form;

use JWeiland\Pforum\Converter\RteContentConverter;
use JWeiland\Pforum\Service\FileReferenceService;
use JWeiland\Pforum\Service\RecordAccessService;
use JWeiland\Pforum\Service\RecordService;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;

/**
 * Fills the form with the values of the topic or post to edit. The
 * element property "dbMapping" defines the column of an element:
 *
 * dbMapping:
 *   column: images
 *   # Position of the file of a file column
 *   position: 1
 *   # Table of the column. Default: table of the topic or post
 *   table: tx_pforum_domain_model_anonymoususer
 *   # "plainText" converts the stored HTML into plain text
 *   dataType: plainText
 */
readonly class RecordFormPrefiller
{
    public const DATA_TYPE_PLAIN_TEXT = 'plainText';

    private const UPLOAD_TYPES = ['FileUpload', 'ImageUpload'];

    public function __construct(
        private RecordAccessService $recordAccessService,
        private RecordService $recordService,
        private FileReferenceService $fileReferenceService,
        private ResourceFactory $resourceFactory,
        private RteContentConverter $rteContentConverter,
    ) {}

    public function prefill(FormDefinition $formDefinition, RecordFormContext $formContext): void
    {
        if ($formContext->isNew() || $this->recordAccessService->getFormDenialReason($formContext) !== '') {
            return;
        }

        $record = $this->recordService->findRecord($formContext->recordType, $formContext->recordUid);
        $rows = [
            $formContext->recordType->getTable() => $record,
            RecordService::USER_TABLE => $this->recordService->findUser((int)($record['anonymous_user'] ?? 0)),
        ];

        foreach ($formDefinition->getRenderablesRecursively() as $element) {
            $dbMapping = $element instanceof FormElementInterface ? $element->getProperties()['dbMapping'] ?? [] : [];
            if (!$element instanceof FormElementInterface || !is_array($dbMapping)) {
                continue;
            }

            $table = (string)($dbMapping['table'] ?? $formContext->recordType->getTable());
            $value = $this->getDefaultValue($element, $dbMapping, $table, $rows[$table] ?? []);
            if ($value !== null) {
                $element->setDefaultValue($value);
            }
        }
    }

    /**
     * @param array<string, mixed> $dbMapping
     * @param array<string, mixed> $row
     */
    private function getDefaultValue(FormElementInterface $element, array $dbMapping, string $table, array $row): mixed
    {
        $column = (string)($dbMapping['column'] ?? '');
        if ($column === '' || !array_key_exists($column, $row)) {
            return null;
        }

        if (in_array($element->getType(), self::UPLOAD_TYPES, true)) {
            return $this->getFileReference($table, (int)$row['uid'], $column, max(1, (int)($dbMapping['position'] ?? 1)));
        }

        $value = is_scalar($row[$column]) ? (string)$row[$column] : '';

        return ($dbMapping['dataType'] ?? '') === self::DATA_TYPE_PLAIN_TEXT
            ? $this->rteContentConverter->convertHtmlToPlainText($value)
            : $value;
    }

    /**
     * EXT:form expects an Extbase file reference as value of upload elements
     */
    private function getFileReference(string $table, int $recordUid, string $column, int $position): ?FileReference
    {
        $referenceUid = array_keys($this->fileReferenceService->getFileUids($table, $recordUid, $column))[$position - 1] ?? 0;
        if ($referenceUid === 0) {
            return null;
        }

        try {
            $fileReference = new FileReference();
            $fileReference->setOriginalResource($this->resourceFactory->getFileReferenceObject($referenceUid));
        } catch (ResourceDoesNotExistException) {
            return null;
        }

        return $fileReference;
    }
}
