<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Traits;

use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;

/**
 * Frontend request of the Forum plugin for services, which are used
 * without a frontend request, e.g. repositories
 */
trait ExtbaseRequestTrait
{
    private function createExtbaseRequest(
        int $storagePid = 10,
        string $controllerName = 'Forum',
        string $actionName = 'list',
        string $method = 'GET',
    ): Request {
        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);
        $frontendTypoScript->setConfigArray([]);

        $serverRequest = new ServerRequest(
            'https://www.example.com/forum',
            $method,
            'php://input',
            [],
            ['HTTP_HOST' => 'www.example.com', 'HTTPS' => 'on', 'REQUEST_URI' => '/forum', 'SCRIPT_NAME' => '/index.php'],
        );
        $site = new Site('pforum', 1, [
            'rootPageId' => 1,
            'base' => 'https://www.example.com/',
            'languages' => [
                ['languageId' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8', 'base' => '/'],
            ],
        ]);
        $request = new Request(
            $serverRequest
                ->withAttribute('site', $site)
                ->withAttribute('language', $site->getDefaultLanguage())
                ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($serverRequest))
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('frontend.typoscript', $frontendTypoScript)
                ->withAttribute(
                    'extbase',
                    (new ExtbaseRequestParameters())
                        ->setControllerExtensionName('Pforum')
                        ->setPluginName('Forum')
                        ->setControllerName($controllerName)
                        ->setControllerActionName($actionName),
                ),
        );
        $configurationManager = $this->get(ConfigurationManagerInterface::class);
        $configurationManager->setRequest($request);
        $configurationManager->setConfiguration([
            'extensionName' => 'Pforum',
            'pluginName' => 'Forum',
            'persistence' => ['storagePid' => (string)$storagePid],
        ]);

        return $request;
    }
}
