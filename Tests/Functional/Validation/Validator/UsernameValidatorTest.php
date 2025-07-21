<?php

/*
 * This file is part of the package jweiland/telephonedirectory.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Validation\Validator;

use JWeiland\Pforum\Validation\Validator\UsernameValidator;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Validation\Error;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case
 */
class UsernameValidatorTest extends FunctionalTestCase
{
    protected UsernameValidator $subject;

    protected ConfigurationManagerInterface $configurationManagerMock;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        $this->configurationManagerMock = $this->createMock(ConfigurationManager::class);

        $this->subject = new UsernameValidator();
    }

    protected function tearDown(): void
    {
        unset($this->subject);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function validateWillNotAddAnyErrorIfUsernameIsNotMandatory()
    {
        $this->setUsernameIsMandatory(false);

        self::assertEquals(
            new Result(),
            $this->subject->validate(''),
        );
    }

    /**
     * @test
     */
    public function validateWillNotAddAnyErrorIfUsernameIsNotString()
    {
        $this->setUsernameIsMandatory(true);

        self::assertEquals(
            new Result(),
            $this->subject->validate(123),
        );
    }

    /**
     * @test
     */
    public function validateWillNotAddAnyErrorIfUsernameIsNotEmpty()
    {
        $this->setUsernameIsMandatory(true);

        self::assertEquals(
            new Result(),
            $this->subject->validate('stefan'),
        );
    }

    /**
     * @test
     */
    public function validateWillAddErrorIfUsernameIsEmpty()
    {
        $this->setUsernameIsMandatory(true);

        $expectedResult = new Result();
        $expectedResult->addError(
            new Error(
                'The username of user object is mandatory',
                1378304890,
            ),
        );

        self::assertEquals(
            $expectedResult,
            $this->subject->validate(''),
        );
    }

    protected function setUsernameIsMandatory(bool $isMandatory)
    {
        $this->configurationManagerMock
            ->expects(self::once())
            ->method('getConfiguration')
            ->with(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
                'pforum',
                'forum',
            )
            ->willReturn([
                'usernameIsMandatory' => $isMandatory ? '1' : '0',
            ]);
        $this->subject->injectConfigurationManager($this->configurationManagerMock);
    }
}
