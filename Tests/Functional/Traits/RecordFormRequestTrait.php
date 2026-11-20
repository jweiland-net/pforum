<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Traits;

use JWeiland\Pforum\Security\AnonymousAccessTokenService;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Builds requests of the Forum plugin for the forms based on EXT:form
 */
trait RecordFormRequestTrait
{
    /**
     * Settings of the Forum plugin in TypoScript array notation. Anonymous
     * users, records are visible at creation.
     *
     * @var array<string, mixed>
     */
    private array $defaultSettings = [
        'auth' => '1',
        'emailIsMandatory' => '0',
        'usernameIsMandatory' => '0',
        'useImages' => '1',
        'uidOfAdminGroup' => '2',
        'uidOfUserGroup' => '1',
        'topic.' => [
            'hideAtCreation' => '0',
            'activateByAdmin' => '0',
        ],
        'post.' => [
            'hideAtCreation' => '0',
            'activateByAdmin' => '0',
        ],
    ];

    /**
     * @param int[] $groupUids
     */
    private function loginFrontendUser(int $frontendUserUid, array $groupUids = [1]): void
    {
        $frontendUser = new FrontendUserAuthentication();
        $frontendUser->user = ['uid' => $frontendUserUid, 'username' => 'user' . $frontendUserUid];
        foreach ($groupUids as $groupUid) {
            $frontendUser->userGroups[$groupUid] = ['uid' => $groupUid, 'title' => 'Group ' . $groupUid];
        }

        GeneralUtility::makeInstance(Context::class)->setAspect('frontend.user', new UserAspect($frontendUser));
    }

    /**
     * @param array<string, string> $pluginArguments Query parameters of tx_pforum_forum
     * @param array<string, mixed> $settings Settings in TypoScript array notation, which override the default settings
     */
    private function buildFormRequest(array $pluginArguments = [], array $settings = []): Request
    {
        $pluginSettings = $this->defaultSettings;
        ArrayUtility::mergeRecursiveWithOverrule($pluginSettings, $settings);

        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([
            'plugin.' => [
                'tx_pforum.' => [
                    'persistence.' => ['storagePid' => '10'],
                    'settings.' => $pluginSettings,
                ],
            ],
        ]);

        $request = (new ServerRequest('https://example.com/forum'))
            ->withQueryParams(['tx_pforum_forum' => $pluginArguments])
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.typoscript', $frontendTypoScript)
            ->withAttribute('routing', new PageArguments(2, '0', []))
            ->withAttribute('extbase', new ExtbaseRequestParameters());

        return new Request($request);
    }

    private function generateToken(string $type, int $uid): string
    {
        return $this->get(AnonymousAccessTokenService::class)->generateToken($type, $uid);
    }
}
