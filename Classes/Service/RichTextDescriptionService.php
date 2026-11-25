<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Service;

use JWeiland\Pforum\Converter\RteContentConverter;
use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Domain\RecordType;
use JWeiland\Pforum\Sanitizer\RichTextSanitizer;
use JWeiland\Pforum\Utility\TypeCaster;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The description of topics and posts is stored as HTML like the backend
 * RTE stores it. With the rich text editor (TypoScript
 * "settings.new.richText", site settings "pforum.form.richText.*") the
 * submitted HTML is sanitized. Without it, the plain text of the textarea
 * is converted into HTML.
 */
readonly class RichTextDescriptionService
{
    public const COLUMN = 'description';

    public function __construct(
        private PackageManager $packageManager,
        private RichTextService $richTextService,
        private RichTextSanitizer $richTextSanitizer,
        private RichTextEditorOptions $richTextEditorOptions,
        private RteContentConverter $rteContentConverter,
    ) {}

    /**
     * @param array<string, mixed> $settings Plugin settings
     */
    public function isEnabled(array $settings): bool
    {
        $richTextSettings = $this->getRichTextSettings($settings);

        return (bool)($richTextSettings['enable'] ?? false)
            && in_array(self::COLUMN, GeneralUtility::trimExplode(',', TypeCaster::toString($richTextSettings['elements'] ?? ''), true), true)
            && $this->packageManager->isPackageActive('rte_ckeditor');
    }

    /**
     * @param array<string, mixed> $settings Plugin settings
     */
    public function prepareForPersistence(Topic|Post $record, array $settings): void
    {
        $description = $record->getDescription();
        $record->setDescription(
            $this->isEnabled($settings)
                ? $this->richTextService->prepareForPersistence(
                    $description,
                    RecordType::fromRecord($record)->getTable(),
                    self::COLUMN,
                    (int)$record->getUid(),
                    (int)$record->getPid(),
                )
                : $this->rteContentConverter->convertPlainTextToHtml($description),
        );
    }

    /**
     * Variables of the form templates:
     * - richTextEditor.enabled: The textarea gets the rich text editor
     * - richTextEditor.options: The options of the editor as JSON
     * - descriptionPlainText: The stored description as plain text for the
     *   textarea without editor
     *
     * @param array<string, mixed> $settings Plugin settings
     * @return array<string, mixed>
     */
    public function getFormVariables(Topic|Post $record, array $settings, ServerRequestInterface $request): array
    {
        if (!$this->isEnabled($settings)) {
            return [
                'richTextEditor' => ['enabled' => false],
                'descriptionPlainText' => $record->getUid() === null
                    ? null
                    : $this->rteContentConverter->convertHtmlToPlainText($record->getDescription()),
            ];
        }

        $configuration = $this->richTextSanitizer->getConfiguration(RecordType::fromRecord($record)->getTable(), self::COLUMN);
        $options = $this->richTextEditorOptions->getEditorOptions(
            $request,
            $configuration,
            TypeCaster::toString($this->getRichTextSettings($settings)['translationsPath'] ?? ''),
        );

        return [
            'richTextEditor' => ['enabled' => true, 'options' => json_encode($options, JSON_THROW_ON_ERROR)],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function getRichTextSettings(array $settings): array
    {
        return TypeCaster::toStringKeyedArray(TypeCaster::toArray($settings['new'] ?? [])['richText'] ?? []);
    }
}
