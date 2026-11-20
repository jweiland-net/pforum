<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Form;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Mvc\Configuration\ConfigurationManagerInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * The prototype configuration of pforum is used for all forms of the
 * installation. So it must not change anything else than registering the
 * finishers of pforum. Templates, partials and translations of the topic
 * and post forms are set in their form definitions.
 */
class FormSetupTest extends FunctionalTestCase
{
    private const CORE_FORM_SETUP = 'EXT:form/Configuration/Yaml/FormSetup.yaml';

    private const FORM_SETUP = 'EXT:pforum/Configuration/Form/OverrideFormSettings.yaml';

    private const TEMPLATE_PATH = 'EXT:pforum/Resources/Private/FormFramework/Templates/Finishers/Email/';

    protected array $coreExtensionsToLoad = [
        'form',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    #[Test]
    public function formSetupChangesNothingButFinishersOfPforum(): void
    {
        $corePrototype = $this->getPrototype([10 => self::CORE_FORM_SETUP]);
        $prototype = $this->getPrototype([10 => self::CORE_FORM_SETUP, 1763890800 => self::FORM_SETUP]);

        $addedFinishers = array_diff_key($prototype['finishersDefinition'], $corePrototype['finishersDefinition']);
        unset($prototype['finishersDefinition']);
        unset($corePrototype['finishersDefinition']);

        self::assertSame(['PforumSaveTopic', 'PforumSavePost'], array_keys($addedFinishers));
        self::assertSame($corePrototype, $prototype);
    }

    #[Test]
    public function formSetupKeepsFinishersOfCore(): void
    {
        $corePrototype = $this->getPrototype([10 => self::CORE_FORM_SETUP]);
        $prototype = $this->getPrototype([10 => self::CORE_FORM_SETUP, 1763890800 => self::FORM_SETUP]);

        self::assertSame(
            $corePrototype['finishersDefinition'],
            array_intersect_key($prototype['finishersDefinition'], $corePrototype['finishersDefinition']),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function recordTypeDataProvider(): array
    {
        return [
            'topic form' => ['Topic'],
            'post form' => ['Post'],
        ];
    }

    #[Test]
    #[DataProvider('recordTypeDataProvider')]
    public function formUsesOwnMailTemplate(string $recordType): void
    {
        $templateName = $recordType . 'Moderation';
        $options = $this->getEmailFinisherOptionsOfForm($recordType);

        self::assertSame($templateName, $options['templateName']);
        self::assertSame(self::TEMPLATE_PATH, end($options['templateRootPaths']));

        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths(array_map(GeneralUtility::getFileAbsFileName(...), $options['templateRootPaths']));
        foreach (['html', 'txt'] as $format) {
            self::assertSame(
                GeneralUtility::getFileAbsFileName(self::TEMPLATE_PATH . $templateName . '.' . $format),
                $templatePaths->resolveTemplateFileForControllerAndActionAndFormat('Default', $templateName, $format),
            );
        }
    }

    #[Test]
    #[DataProvider('recordTypeDataProvider')]
    public function formSetsPartialsAndTranslationsOfPforum(string $recordType): void
    {
        $renderingOptions = Yaml::parseFile($this->getFormDefinitionPath($recordType))['renderingOptions'];

        self::assertSame('version2', $renderingOptions['templateVariant']);
        self::assertSame(['EXT:pforum/Resources/Private/FormFramework/Partials/'], array_values($renderingOptions['partialRootPaths']));
        self::assertSame(
            ['EXT:pforum/Resources/Private/Language/locallang_form.xlf'],
            array_values($renderingOptions['translation']['translationFiles']),
        );
    }

    #[Test]
    #[DataProvider('recordTypeDataProvider')]
    public function mailTemplateUsesVariablesOfOwnFinisher(string $recordType): void
    {
        foreach (['html', 'txt'] as $format) {
            $template = (string)file_get_contents(
                GeneralUtility::getFileAbsFileName(self::TEMPLATE_PATH . $recordType . 'Moderation.' . $format),
            );

            self::assertStringContainsString('{finisherVariableProvider.PforumSave' . $recordType . '.accessToken}', $template);
            self::assertStringContainsString('{finisherVariableProvider.PforumSave' . $recordType . '.activationToken}', $template);
        }
    }

    /**
     * Options of EmailToSender like FormDefinition::createFinisher() merges them
     *
     * @return array<string, mixed>
     */
    private function getEmailFinisherOptionsOfForm(string $recordType): array
    {
        $prototype = $this->getPrototype([10 => self::CORE_FORM_SETUP, 1763890800 => self::FORM_SETUP]);
        $options = $prototype['finishersDefinition']['EmailToSender']['options'];
        foreach (Yaml::parseFile($this->getFormDefinitionPath($recordType))['finishers'] as $finisher) {
            if ($finisher['identifier'] === 'EmailToSender') {
                ArrayUtility::mergeRecursiveWithOverrule($options, $finisher['options']);
            }
        }
        ksort($options['templateRootPaths']);

        return $options;
    }

    private function getFormDefinitionPath(string $recordType): string
    {
        return __DIR__ . '/../../../Configuration/Form/' . $recordType . '.form.yaml';
    }

    /**
     * @param array<int, string> $yamlConfigurations
     * @return array<string, mixed>
     */
    private function getPrototype(array $yamlConfigurations): array
    {
        $configuration = $this->get(ConfigurationManagerInterface::class)
            ->getYamlConfiguration(['yamlConfigurations' => $yamlConfigurations], true);

        return ($configuration['prototypes'] ?? $configuration['TYPO3']['CMS']['Form']['prototypes'])['standard'];
    }
}
