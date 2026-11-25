<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Traits;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Configuration\SiteWriter;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;
use TYPO3\CMS\Extbase\Security\HashScope;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * Frontend requests of the Forum plugin on page 2 of Fixtures/Forum.csv
 */
trait FrontendRequestTrait
{
    private const PLUGIN_NAMESPACE = 'tx_pforum_forum';

    private const FORUM_PAGE = 2;

    /**
     * @param string[] $additionalSetupFiles
     */
    private function setUpForumSite(array $additionalSetupFiles = []): void
    {
        $this->setUpFrontendRootPage(
            1,
            [
                'constants' => ['EXT:pforum/Tests/Functional/Fixtures/Frontend/Constants.typoscript'],
                'setup' => ['EXT:pforum/Tests/Functional/Fixtures/Frontend/Forum.typoscript', ...$additionalSetupFiles],
            ],
        );
        $this->get(SiteWriter::class)->write('pforum', [
            'rootPageId' => 1,
            'base' => 'https://www.example.com/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title' => 'English',
                    'locale' => 'en_US.UTF-8',
                    'base' => '/',
                ],
            ],
        ]);
    }

    /**
     * @param array<string, string> $pluginArguments Arguments of the query string
     * @param array<string, mixed> $parsedBody
     */
    private function request(
        string $controllerName,
        string $actionName,
        array $pluginArguments = [],
        array $parsedBody = [],
        int $frontendUserUid = 0,
    ): ResponseInterface {
        $queryParameters = [
            'id' => self::FORUM_PAGE,
            self::PLUGIN_NAMESPACE . '[action]' => $actionName,
            self::PLUGIN_NAMESPACE . '[controller]' => $controllerName,
        ];
        foreach ($pluginArguments as $argumentName => $value) {
            $queryParameters[self::PLUGIN_NAMESPACE . '[' . $argumentName . ']'] = $value;
        }
        $queryParameters['cHash'] = $this->get(CacheHashCalculator::class)
            ->generateForParameters(HttpUtility::buildQueryString($queryParameters));

        $request = (new InternalRequest('https://www.example.com/'))->withQueryParameters($queryParameters);
        if ($parsedBody !== []) {
            $request = $request->withMethod('POST')->withParsedBody($parsedBody);
            // The testing framework can not build a body of nested arrays
            $request->getBody()->write(http_build_query($parsedBody));
        }

        $context = new InternalRequestContext();
        if ($frontendUserUid > 0) {
            $context = $context->withFrontendUserId($frontendUserUid);
        }

        return $this->executeFrontendSubRequest($request, $context);
    }

    /**
     * Submits an Extbase form of the Forum plugin like the browser does:
     * with the trusted properties of all submitted fields.
     *
     * @param array<string, string> $pluginArguments Arguments of the query string
     * @param array<string, mixed> $values Submitted values of the form without plugin namespace
     * @param array<string, mixed> $referringArguments Arguments of the action of the form. Extbase forwards to this action on validation errors.
     */
    private function submitForm(
        string $controllerName,
        string $actionName,
        array $pluginArguments,
        array $values,
        int $frontendUserUid = 0,
        string $referringActionName = '',
        array $referringArguments = [],
    ): ResponseInterface {
        $values['__trustedProperties'] = $this->get(MvcPropertyMappingConfigurationService::class)
            ->generateTrustedPropertiesToken($this->getFieldNames($values, self::PLUGIN_NAMESPACE), self::PLUGIN_NAMESPACE);
        if ($referringActionName !== '') {
            $hashService = $this->get(HashService::class);
            $values['__referrer'] = [
                '@request' => $hashService->appendHmac(
                    (string)json_encode(['@extension' => 'Pforum', '@controller' => $controllerName, '@action' => $referringActionName]),
                    HashScope::ReferringRequest->prefix(),
                    HashAlgo::SHA3_256,
                ),
                'arguments' => $hashService->appendHmac(
                    base64_encode(serialize($referringArguments)),
                    HashScope::ReferringArguments->prefix(),
                    HashAlgo::SHA3_256,
                ),
            ];
        }

        return $this->request($controllerName, $actionName, $pluginArguments, [self::PLUGIN_NAMESPACE => $values], $frontendUserUid);
    }

    /**
     * @param array<string, mixed> $values
     * @return string[]
     */
    private function getFieldNames(array $values, string $prefix): array
    {
        $fieldNames = [];
        foreach ($values as $key => $value) {
            $fieldName = $prefix . '[' . $key . ']';
            $fieldNames = [...$fieldNames, ...(is_array($value) ? $this->getFieldNames($value, $fieldName) : [$fieldName])];
        }

        return $fieldNames;
    }
}
