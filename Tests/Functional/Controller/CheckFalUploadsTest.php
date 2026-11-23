<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Controller;

use JWeiland\Checkfaluploads\Validation\Validator\CheckFalUploadValidator;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\EventListener\CheckFalUploadsEventListener;
use JWeiland\Pforum\Tests\Functional\Traits\FrontendRequestTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Service\FileHandlingService;
use TYPO3\CMS\Extbase\Validation\Validator\ConjunctionValidator;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * With EXT:checkfaluploads, pforum adds its CheckFalUploadValidator to the
 * image uploads. The rights checkbox is not part of the templates of
 * pforum. Integrators add it to their overrides of the form partials.
 */
class CheckFalUploadsTest extends FunctionalTestCase
{
    use FrontendRequestTrait;

    protected array $coreExtensionsToLoad = [
        'filelist',
    ];

    protected array $testExtensionsToLoad = [
        'jweiland/checkfaluploads',
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Forum.csv');
        $this->setUpForumSite();
    }

    #[Test]
    public function formRendersNoRightsCheckbox(): void
    {
        $content = (string)$this->request('Topic', 'new', ['forum' => '1'])->getBody();

        self::assertStringContainsString('name="tx_pforum_forum[topic][images][]"', $content);
        self::assertStringNotContainsString('[images][rights]', $content);
    }

    #[Test]
    public function uploadWithoutCheckedRightsCheckboxIsRejected(): void
    {
        $validator = $this->getCheckFalUploadValidator(['topic' => ['images' => ['rights' => '']]]);

        self::assertTrue($validator->validate($this->buildUploadedFile())->hasErrors());
    }

    #[Test]
    public function uploadWithCheckedRightsCheckboxIsAccepted(): void
    {
        $validator = $this->getCheckFalUploadValidator(['topic' => ['images' => ['rights' => '1']]]);

        self::assertFalse($validator->validate($this->buildUploadedFile())->hasErrors());
    }

    /**
     * Adds the validator to the file upload configuration of the images of a
     * topic like the action "create" of the TopicController does
     *
     * @param array<string, mixed> $pluginValues Submitted values of the form without plugin namespace
     */
    private function getCheckFalUploadValidator(array $pluginValues): ValidatorInterface
    {
        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);
        $request = new Request(
            (new ServerRequest('https://www.example.com/forum', 'POST'))
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('frontend.typoscript', $frontendTypoScript)
                ->withParsedBody([self::PLUGIN_NAMESPACE => $pluginValues])
                ->withAttribute(
                    'extbase',
                    (new ExtbaseRequestParameters())
                        ->setControllerExtensionName('Pforum')
                        ->setPluginName('Forum')
                        ->setControllerName('Topic')
                        ->setControllerActionName('create'),
                ),
        );
        $this->get(ConfigurationManagerInterface::class)->setRequest($request);
        $arguments = new Arguments();
        // Arguments without validator (IgnoreValidation) get no file upload configuration
        $arguments->addNewArgument('topic', Topic::class, true)->setValidator(new ConjunctionValidator());
        $this->get(FileHandlingService::class)->initializeFileUploadConfigurationsFromRequest($request, $arguments);

        $this->get(CheckFalUploadsEventListener::class)(new PreProcessControllerActionEvent($request, $arguments, []));

        $fileUploadConfiguration = $arguments->getArgument('topic')
            ->getFileHandlingServiceConfiguration()
            ->getFileUploadConfigurationForProperty('images');
        self::assertNotNull($fileUploadConfiguration);

        foreach ($fileUploadConfiguration->getValidators() as $validator) {
            if ($validator instanceof CheckFalUploadValidator) {
                return $validator;
            }
        }

        self::fail('CheckFalUploadValidator was not added to the images of the topic');
    }

    private function buildUploadedFile(): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'pforum');
        file_put_contents($file, 'image');

        return new UploadedFile($file, 5, UPLOAD_ERR_OK, 'image.png', 'image/png');
    }
}
