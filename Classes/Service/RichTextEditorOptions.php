<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Service;

use JWeiland\Pforum\Sanitizer\RichTextSanitizerConfiguration;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\SystemResource\Exception\SystemResourceException;
use TYPO3\CMS\Core\SystemResource\Publishing\SystemResourcePublisherInterface;
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Builds the options of the CKEditor 5 of a rich text element, which are
 * added as JSON to the textarea. The JavaScript module and the style sheets
 * are loaded by the partial RichTextEditor/LoadAssets.html.
 */
readonly class RichTextEditorOptions
{
    public function __construct(
        protected SystemResourceFactory $systemResourceFactory,
        protected SystemResourcePublisherInterface $resourcePublisher,
        protected LoggerInterface $logger,
    ) {}

    /**
     * The headings and toolbar items of the editor are based on the allowed tags.
     *
     * @param string $translationsPath Folder of the translations of CKEditor, e.g. "EXT:rte_ckeditor/Resources/Public/Contrib/translations/"
     * @return array<string, mixed>
     */
    public function getEditorOptions(
        ServerRequestInterface $request,
        RichTextSanitizerConfiguration $configuration,
        string $translationsPath,
    ): array {
        $languageCode = $this->getLanguageCode($request);

        return [
            'language' => $languageCode,
            'allowedTags' => $configuration->allowedTags,
            'translationUrl' => $this->getTranslationUrl($request, $languageCode, $translationsPath),
            'labels' => [
                'openInNewTab' => LocalizationUtility::translate('richText.openInNewTab', 'pforum') ?? 'Open in a new tab',
            ],
        ];
    }

    protected function getLanguageCode(ServerRequestInterface $request): string
    {
        $siteLanguage = $request->getAttribute('language');
        $languageCode = $siteLanguage instanceof SiteLanguage ? $siteLanguage->getLocale()->getLanguageCode() : 'en';

        return preg_match('/^[a-z]{2,3}$/', $languageCode) === 1 ? $languageCode : 'en';
    }

    /**
     * English is the built-in language of CKEditor and has no translation file
     */
    protected function getTranslationUrl(ServerRequestInterface $request, string $languageCode, string $translationsPath): string
    {
        if ($languageCode === 'en' || trim($translationsPath) === '') {
            return '';
        }

        $translationFile = rtrim(trim($translationsPath), '/') . '/' . $languageCode . '.js';
        try {
            $resource = $this->systemResourceFactory->createPublicResource($translationFile);

            return (string)$this->resourcePublisher->generateUri($resource, $request);
        } catch (SystemResourceException $exception) {
            $this->logger->debug('No CKEditor translation "{file}" available', ['file' => $translationFile, 'exception' => $exception]);

            return '';
        }
    }
}
