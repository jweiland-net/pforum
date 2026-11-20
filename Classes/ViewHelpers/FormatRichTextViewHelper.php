<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\ViewHelpers;

use JWeiland\Pforum\Converter\RteContentConverter;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Sanitizer\RichTextSanitizer;
use TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\AbstractRenderable;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Renders the submitted value of a rich text element of the topic and post forms
 * (properties.dbMapping.dataType: richText) for the mails of EXT:form.
 *
 * The value is sanitized with the same configuration as on saving
 * (see ModifyRichTextSanitizerConfigurationEvent). The table is
 * "properties.dbMapping.table" (default: table of the topic or post),
 * the column is "properties.dbMapping.column". So the moderator sees the
 * formatting which will be stored, without attributes of the editor and
 * without active content. t3:// links are removed, as they can not be
 * resolved in a mail.
 *
 * format "html" returns the sanitized HTML. format "plaintext" returns
 * plain text with line breaks for the text part of a mail. The plain text
 * is NOT escaped, so do not use it in HTML.
 *
 * Example:
 * <pf:formatRichText element="{formValue.element}" value="{formValue.processedValue}"/>
 * <pf:formatRichText element="{formValue.element}" value="{formValue.processedValue}" format="plaintext"/>
 */
final class FormatRichTextViewHelper extends AbstractViewHelper
{
    private const FORMAT_HTML = 'html';

    private const FORMAT_PLAINTEXT = 'plaintext';

    /**
     * The output is sanitized HTML or plain text for the text part of a mail
     *
     * @var bool
     */
    protected $escapeOutput = false;

    public function __construct(
        private readonly RichTextSanitizer $richTextSanitizer,
        private readonly RteContentConverter $rteContentConverter,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('element', 'object', 'The form element of the value', true);
        $this->registerArgument('value', 'mixed', 'The submitted value of the form element', true);
        $this->registerArgument('format', 'string', 'Output format: "html" or "plaintext"', false, self::FORMAT_HTML);
    }

    /**
     * @throws Exception
     */
    public function render(): string
    {
        $format = (string)$this->arguments['format'];
        if (!in_array($format, [self::FORMAT_HTML, self::FORMAT_PLAINTEXT], true)) {
            throw new Exception('Argument "format" must be "html" or "plaintext".', 1763890802);
        }

        $html = $this->sanitize($this->getValue());

        return $format === self::FORMAT_PLAINTEXT
            ? $this->rteContentConverter->convertHtmlToPlainText($html)
            : $html;
    }

    private function sanitize(string $value): string
    {
        if (trim($value) === '') {
            return '';
        }

        $dbMapping = $this->getDbMapping();

        return $this->richTextSanitizer->sanitize(
            $value,
            [],
            (string)($dbMapping['table'] ?? $this->getTableOfForm()),
            (string)($dbMapping['column'] ?? ''),
        );
    }

    private function getTableOfForm(): string
    {
        $element = $this->arguments['element'];
        try {
            $formDefinition = $element instanceof AbstractRenderable ? $element->getRootForm() : null;
        } catch (FormDefinitionConsistencyException) {
            return '';
        }

        return $formDefinition instanceof FormDefinition ? RecordType::getTableOfForm($formDefinition->getRenderingOptions()) : '';
    }

    private function getValue(): string
    {
        $value = $this->arguments['value'];

        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function getDbMapping(): array
    {
        $element = $this->arguments['element'];
        if (!$element instanceof FormElementInterface) {
            return [];
        }

        $dbMapping = $element->getProperties()['dbMapping'] ?? [];

        return is_array($dbMapping) ? $dbMapping : [];
    }
}
