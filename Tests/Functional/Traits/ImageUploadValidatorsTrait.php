<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Traits;

use JWeiland\Pforum\Domain\Model\Post;
use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\EventListener\CheckFalUploadsEventListener;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Extbase\Service\FileHandlingService;
use TYPO3\CMS\Extbase\Validation\Validator\ConjunctionValidator;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;

/**
 * Dispatches PreProcessControllerActionEvent to CheckFalUploadsEventListener
 * like the controllers of pforum do and returns the validators of the
 * image uploads
 */
trait ImageUploadValidatorsTrait
{
    /**
     * @return ValidatorInterface[]
     */
    private function getImageUploadValidators(string $controllerName, string $actionName, string $argumentName): array
    {
        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);
        $request = new Request(
            (new ServerRequest('https://www.example.com/forum', 'POST'))
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('frontend.typoscript', $frontendTypoScript)
                ->withParsedBody(['tx_pforum_forum' => [$argumentName => ['images' => []]]])
                ->withAttribute(
                    'extbase',
                    (new ExtbaseRequestParameters())
                        ->setControllerExtensionName('Pforum')
                        ->setPluginName('Forum')
                        ->setControllerName($controllerName)
                        ->setControllerActionName($actionName),
                ),
        );
        $this->get(ConfigurationManagerInterface::class)->setRequest($request);
        $arguments = new Arguments();
        // Arguments without validator (IgnoreValidation) get no file upload configuration
        $arguments->addNewArgument($argumentName, $argumentName === 'post' ? Post::class : Topic::class, true)
            ->setValidator(new ConjunctionValidator());
        $this->get(FileHandlingService::class)->initializeFileUploadConfigurationsFromRequest($request, $arguments);

        $this->get(CheckFalUploadsEventListener::class)(new PreProcessControllerActionEvent($request, $arguments, []));

        $fileUploadConfiguration = $arguments->getArgument($argumentName)
            ->getFileHandlingServiceConfiguration()
            ->getFileUploadConfigurationForProperty('images');
        self::assertNotNull($fileUploadConfiguration);

        return iterator_to_array($fileUploadConfiguration->getValidators(), false);
    }
}
