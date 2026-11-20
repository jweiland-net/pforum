<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Form;

use JWeiland\Pforum\Domain\Factory\ArrayFormFactory;
use JWeiland\Pforum\Form\RecordFormConfigurator;
use JWeiland\Pforum\Form\RecordFormContextBuilder;
use JWeiland\Pforum\Form\RecordType;
use JWeiland\Pforum\Tests\Functional\Traits\RecordFormRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Builds the topic and post forms of Configuration/Form/ with the
 * ArrayFormFactory of pforum
 */
class ArrayFormFactoryTest extends FunctionalTestCase
{
    use RecordFormRequestTrait;

    protected array $coreExtensionsToLoad = [
        'form',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'pforum' => [
                'emailFromAddress' => 'forum@example.com',
                'emailFromName' => 'Forum',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
    }

    #[Test]
    public function buildMarksFormAsFormOfPforum(): void
    {
        $formDefinition = $this->buildForm('Topic', $this->buildFormRequest(['forum' => '1']));

        self::assertSame('pforum', $formDefinition->getRenderingOptions()['formOwner']);
    }

    #[Test]
    public function buildSubmitsNewTopicToNewActionOfForum(): void
    {
        $formDefinition = $this->buildForm('Topic', $this->buildFormRequest(['forum' => '1']));

        self::assertSame('new', $formDefinition->getRenderingOptions()['controllerAction']);
        self::assertSame(['tx_pforum_forum' => ['forum' => 1]], $formDefinition->getRenderingOptions()['additionalParams']);
    }

    #[Test]
    public function buildSubmitsEditedPostWithTokenToEditAction(): void
    {
        $token = $this->generateToken('Post', 2);

        $formDefinition = $this->buildForm('Post', $this->buildFormRequest(['post' => '2', 'token' => $token]));

        self::assertSame('edit', $formDefinition->getRenderingOptions()['controllerAction']);
        self::assertSame(['tx_pforum_forum' => ['post' => 2, 'token' => $token]], $formDefinition->getRenderingOptions()['additionalParams']);
    }

    #[Test]
    public function buildKeepsElementsOfPforumUserForAnonymousUsers(): void
    {
        $formDefinition = $this->buildForm('Topic', $this->buildFormRequest(['forum' => '1']));

        self::assertInstanceOf(FormElementInterface::class, $formDefinition->getElementByIdentifier('name'));
        self::assertInstanceOf(FormElementInterface::class, $formDefinition->getElementByIdentifier('email'));
        self::assertSame([], $this->getValidatorClassNames($formDefinition, 'username'));
    }

    #[Test]
    public function buildRemovesElementsOfPforumUserForFrontendUsers(): void
    {
        $formDefinition = $this->buildForm('Topic', $this->buildFormRequest(['forum' => '1'], ['auth' => '2']));

        self::assertNull($formDefinition->getElementByIdentifier('name'));
        self::assertNull($formDefinition->getElementByIdentifier('username'));
        self::assertNull($formDefinition->getElementByIdentifier('email'));
        self::assertInstanceOf(FormElementInterface::class, $formDefinition->getElementByIdentifier('title'));
    }

    #[Test]
    public function buildMakesUsernameAndEmailMandatory(): void
    {
        $formDefinition = $this->buildForm(
            'Post',
            $this->buildFormRequest(['topic' => '1'], ['usernameIsMandatory' => '1', 'emailIsMandatory' => '1']),
        );

        self::assertContains('NotEmptyValidator', $this->getValidatorClassNames($formDefinition, 'username'));
        self::assertContains('NotEmptyValidator', $this->getValidatorClassNames($formDefinition, 'email'));
        self::assertContains('EmailAddressValidator', $this->getValidatorClassNames($formDefinition, 'email'));
        self::assertTrue($formDefinition->getElementByIdentifier('email')?->isRequired());
    }

    #[Test]
    public function buildRemovesUploadsIfImagesAreNotUsed(): void
    {
        $formDefinition = $this->buildForm('Post', $this->buildFormRequest(['topic' => '1'], ['useImages' => '0']));

        self::assertNull($formDefinition->getElementByIdentifier('image-1'));
        self::assertNull($formDefinition->getElementByIdentifier('image-2'));
    }

    #[Test]
    public function buildSendsMailToPforumUserOfNewRecordIfEmailIsMandatory(): void
    {
        $emailFinisher = $this->getFinisherOptions('Topic', $this->buildFormRequest(['forum' => '1'], ['emailIsMandatory' => '1']))['EmailToSender'] ?? [];

        self::assertSame('TopicModeration', $emailFinisher['templateName'] ?? '');
        self::assertSame('forum@example.com', $emailFinisher['senderAddress'] ?? '');
        self::assertSame(2, $emailFinisher['variables']['pageUid'] ?? 0);
    }

    #[Test]
    public function buildSendsNoMailIfEmailIsNotMandatory(): void
    {
        self::assertArrayNotHasKey('EmailToSender', $this->getFinisherOptions('Topic', $this->buildFormRequest(['forum' => '1'])));
    }

    #[Test]
    public function buildSendsNoMailForEditedRecord(): void
    {
        $finisherOptions = $this->getFinisherOptions(
            'Topic',
            $this->buildFormRequest(['topic' => '2', 'token' => $this->generateToken('Topic', 2)], ['emailIsMandatory' => '1']),
        );

        self::assertArrayNotHasKey('EmailToSender', $finisherOptions);
    }

    #[Test]
    public function buildRedirectsToTopicOfEditedPost(): void
    {
        $redirectFinisher = $this->getFinisherOptions(
            'Post',
            $this->buildFormRequest(['post' => '2', 'token' => $this->generateToken('Post', 2)]),
        )['Redirect'] ?? [];

        self::assertSame(2, $redirectFinisher['pageUid'] ?? 0);
        parse_str((string)($redirectFinisher['additionalParameters'] ?? ''), $parameters);
        self::assertSame(['tx_pforum_forum' => ['controller' => 'Topic', 'action' => 'show', 'topic' => '1']], $parameters);
    }

    #[Test]
    public function buildSetsMessageOfHiddenTopic(): void
    {
        $finisherOptions = $this->getFinisherOptions(
            'Topic',
            $this->buildFormRequest(['forum' => '1'], ['topic.' => ['hideAtCreation' => '1', 'activateByAdmin' => '1']]),
        );

        self::assertSame(
            'New Topic was created, but must be reviewed by an administrator.',
            $finisherOptions['FlashMessage']['messageBody'] ?? '',
        );
    }

    #[Test]
    public function buildPrefillsEditedTopicAndItsPforumUser(): void
    {
        $formDefinition = $this->buildForm('Topic', $this->buildFormRequest(['topic' => '2', 'token' => $this->generateToken('Topic', 2)]));

        self::assertSame('Hidden topic of Jane', $formDefinition->getElementDefaultValueByIdentifier('title'));
        self::assertSame("First line\nSecond line", $formDefinition->getElementDefaultValueByIdentifier('description'));
        self::assertSame('Jane', $formDefinition->getElementDefaultValueByIdentifier('name'));
        self::assertSame('jane@example.com', $formDefinition->getElementDefaultValueByIdentifier('email'));
    }

    #[Test]
    public function buildDoesNotPrefillTopicWithInvalidToken(): void
    {
        $formDefinition = $this->buildForm('Topic', $this->buildFormRequest(['topic' => '2', 'token' => 'invalid']));

        self::assertSame('', $formDefinition->getElementDefaultValueByIdentifier('title'));
        self::assertSame('', $formDefinition->getElementDefaultValueByIdentifier('name'));
    }

    private function buildForm(string $recordType, ServerRequestInterface $request): FormDefinition
    {
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        self::assertInstanceOf(FrontendTypoScript::class, $frontendTypoScript);
        $setup = $frontendTypoScript->getSetupArray();
        $setup['plugin.']['tx_form.']['settings.']['yamlConfigurations.'] = [
            '10' => 'EXT:form/Configuration/Yaml/FormSetup.yaml',
            '20' => 'EXT:pforum/Configuration/Form/OverrideFormSettings.yaml',
        ];
        $frontendTypoScript->setSetupArray($setup);
        $this->get(ConfigurationManagerInterface::class)->setRequest($request);

        $configuration = Yaml::parseFile(__DIR__ . '/../../../Configuration/Form/' . $recordType . '.form.yaml');

        return $this->get(ArrayFormFactory::class)->build($configuration, null, $request);
    }

    /**
     * @return string[] Short class names of the validators of the element
     */
    private function getValidatorClassNames(FormDefinition $formDefinition, string $identifier): array
    {
        $element = $formDefinition->getElementByIdentifier($identifier);
        self::assertInstanceOf(FormElementInterface::class, $element);

        $classNames = [];
        foreach ($element->getValidators() as $validator) {
            $classNames[] = substr(strrchr($validator::class, '\\') ?: '', 1);
        }

        return $classNames;
    }

    /**
     * Options of the finishers of the form definition, which is configured
     * for the request like the ArrayFormFactory does
     *
     * @return array<string, array<string, mixed>> Options of the finishers by identifier
     */
    private function getFinisherOptions(string $recordType, ServerRequestInterface $request): array
    {
        $configuration = $this->get(RecordFormConfigurator::class)->configure(
            Yaml::parseFile(__DIR__ . '/../../../Configuration/Form/' . $recordType . '.form.yaml'),
            $this->get(RecordFormContextBuilder::class)->build(RecordType::from(strtolower($recordType)), $request),
        );

        $finisherOptions = [];
        foreach ($configuration['finishers'] as $finisher) {
            $finisherOptions[$finisher['identifier']] = $finisher['options'] ?? [];
        }

        return $finisherOptions;
    }
}
