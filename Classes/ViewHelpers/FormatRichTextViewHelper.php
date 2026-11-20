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
use JWeiland\Pforum\Sanitizer\RichTextSanitizer;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Renders the rich text of a topic or post, e.g. in the preview of a new
 * record and in mails. The value is sanitized with the same configuration
 * as on saving (see ModifyRichTextSanitizerConfigurationEvent), so the
 * preview shows the formatting, which will be stored, without active
 * content. t3:// links are removed, as they are not resolved here.
 *
 * Format "html" returns the sanitized HTML. Format "plaintext" returns
 * plain text with line breaks for the text part of a mail. The plain text
 * is NOT escaped, so do not use it in HTML.
 *
 * Example:
 * <pf:formatRichText value="{topic.description}" table="tx_pforum_domain_model_topic" column="description"/>
 * <pf:formatRichText value="{post.description}" table="tx_pforum_domain_model_post" column="description" format="plaintext"/>
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
        $this->registerArgument('value', 'mixed', 'The rich text', true);
        $this->registerArgument('table', 'string', 'Table of the column of the rich text', true);
        $this->registerArgument('column', 'string', 'Column of the rich text', true);
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

        // Plain text of former versions of pforum
        if (trim(strip_tags($value)) === trim($value)) {
            $value = $this->rteContentConverter->convertPlainTextToHtml($value);
        }

        return $this->richTextSanitizer->sanitize(
            $value,
            [],
            (string)$this->arguments['table'],
            (string)$this->arguments['column'],
        );
    }

    private function getValue(): string
    {
        $value = $this->arguments['value'];

        return is_scalar($value) ? (string)$value : '';
    }
}
