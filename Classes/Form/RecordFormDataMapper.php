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
use JWeiland\Pforum\Service\RecordService;
use JWeiland\Pforum\Service\RichTextService;
use TYPO3\CMS\Core\Resource\FileReference as CoreFileReference;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;

/**
 * Maps the submitted values of the form of a topic or post to the columns
 * of the record and its pforum user. Configured by the option "elements"
 * of the save finisher:
 *
 * elements:
 *   title:
 *     mapOnDatabaseColumn: title
 *   name:
 *     mapOnDatabaseColumn: name
 *     table: tx_pforum_domain_model_anonymoususer
 */
readonly class RecordFormDataMapper
{
    public function __construct(
        private RteContentConverter $rteContentConverter,
        private RichTextService $richTextService,
        private RecordService $recordService,
        private TcaSchemaFactory $tcaSchemaFactory,
    ) {}

    /**
     * @param array<string, mixed> $formValues
     * @param array<string, mixed> $elementsConfiguration
     */
    public function map(
        FormDefinition $formDefinition,
        array $formValues,
        array $elementsConfiguration,
        RecordFormContext $formContext,
    ): RecordFormData {
        $data = ['columns' => [], 'userColumns' => [], 'files' => []];
        foreach ($elementsConfiguration as $identifier => $configuration) {
            $column = is_array($configuration) ? (string)($configuration['mapOnDatabaseColumn'] ?? '') : '';
            if ($column === ''
                || !$formDefinition->getElementByIdentifier((string)$identifier) instanceof FormElementInterface
                || !array_key_exists($identifier, $formValues)
            ) {
                continue;
            }

            $data = $this->addValue($data, $column, $formValues[$identifier], $configuration, $formContext);
        }

        return new RecordFormData($data['columns'], $data['userColumns'], $data['files']);
    }

    /**
     * @param array{columns: array<string, string>, userColumns: array<string, string>, files: array<string, int[]>} $data
     * @param array<string, mixed> $configuration
     * @return array{columns: array<string, string>, userColumns: array<string, string>, files: array<string, int[]>}
     */
    private function addValue(array $data, string $column, mixed $value, array $configuration, RecordFormContext $formContext): array
    {
        $table = (string)($configuration['table'] ?? $formContext->recordType->getTable());
        if ($table === RecordService::USER_TABLE) {
            $data['userColumns'][$column] = $this->convertText($this->getText($value), $configuration, $column, $formContext);
        } elseif ($table === $formContext->recordType->getTable() && $this->isFileColumn($table, $column)) {
            $data['files'][$column] = [...($data['files'][$column] ?? []), ...$this->getFileUids($value)];
        } elseif ($table === $formContext->recordType->getTable()) {
            $data['columns'][$column] = $this->convertText($this->getText($value), $configuration, $column, $formContext);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function convertText(string $text, array $configuration, string $column, RecordFormContext $formContext): string
    {
        return match (true) {
            (bool)($configuration['convertPlainTextToHtml'] ?? false) => $this->rteContentConverter->convertPlainTextToHtml($text),
            (bool)($configuration['normalizePlainText'] ?? false) => $this->rteContentConverter->normalizePlainText($text),
            (bool)($configuration['sanitizeRichText'] ?? false) => $this->richTextService->prepareForPersistence(
                $text,
                $formContext->recordType->getTable(),
                $column,
                $formContext->recordUid,
                (int)($this->recordService->findParentRecord($formContext->recordType, $formContext->parentUid)['pid'] ?? 0),
            ),
            default => trim($text),
        };
    }

    private function isFileColumn(string $table, string $column): bool
    {
        if (!$this->tcaSchemaFactory->has($table)) {
            return false;
        }

        $schema = $this->tcaSchemaFactory->get($table);

        return $schema->hasField($column) && ($schema->getField($column)->getConfiguration()['type'] ?? '') === 'file';
    }

    /**
     * @return int[]
     */
    private function getFileUids(mixed $value): array
    {
        $fileReference = $value instanceof FileReference ? $value->getOriginalResource() : $value;

        return $fileReference instanceof CoreFileReference ? [$fileReference->getOriginalFile()->getUid()] : [];
    }

    private function getText(mixed $value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}
