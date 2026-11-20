<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Hook\Form;

use JWeiland\Pforum\Form\FormOwner;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Form\RichTextEditorAssets;
use JWeiland\Pforum\Form\RichTextFormElementConfigurator;
use JWeiland\Pforum\Sanitizer\RichTextSanitizer;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Adds the CKEditor to the rich text elements (dataType "richText"), which
 * are rendered on the current page of a form of pforum (see
 * FormOwner). So the assets are loaded on
 * these pages only. Each textarea gets its own options as JSON.
 *
 * The toolbar of the editor is based on the tags, which the sanitizer allows
 * for the column of the element (see ModifyRichTextSanitizerConfigurationEvent).
 * The column is "properties.dbMapping.column", the table is
 * "properties.dbMapping.table" (default: table of the topic or post of the
 * form, see RecordType).
 */
#[Autoconfigure(public: true)]
final readonly class RichTextEditorHook
{
    public const DATA_ATTRIBUTE = 'data-pforum-rich-text';

    public function __construct(
        private RichTextEditorAssets $richTextEditorAssets,
        private RichTextSanitizer $richTextSanitizer,
    ) {}

    public function beforeRendering(FormRuntime $formRuntime, RootRenderableInterface $renderable): void
    {
        if (
            !FormOwner::isPforumForm($formRuntime->getFormDefinition())
            || !$renderable instanceof FormElementInterface
            || ($renderable->getProperties()['dbMapping']['dataType'] ?? '') !== RichTextFormElementConfigurator::DATA_TYPE
        ) {
            return;
        }

        $dbMapping = $renderable->getProperties()['dbMapping'];
        $configuration = $this->richTextSanitizer->getConfiguration(
            (string)($dbMapping['table'] ?? RecordType::getTableOfForm($formRuntime->getFormDefinition()->getRenderingOptions())),
            (string)($dbMapping['column'] ?? ''),
        );

        $renderable->setProperty('fluidAdditionalAttributes', [
            self::DATA_ATTRIBUTE => json_encode(
                $this->richTextEditorAssets->getEditorOptions($formRuntime->getRequest(), $configuration),
                JSON_THROW_ON_ERROR,
            ),
        ]);
        $this->richTextEditorAssets->load();
    }
}
