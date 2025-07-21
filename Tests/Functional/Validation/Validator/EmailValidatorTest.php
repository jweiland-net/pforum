<?php

/*
 * This file is part of the package jweiland/telephonedirectory.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Validation\Validator;

use JWeiland\Pforum\Validation\Validator\EmailValidator;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Validation\Error;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case
 */
class EmailValidatorTest extends FunctionalTestCase
{
    protected EmailValidator $subject;

    /**
     * @var ConfigurationManager|MockObject
     */
    protected $configurationManagerMock;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);
        $this->subject = new EmailValidator();
    }

    protected function tearDown(): void
    {
        unset($this->subject);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function validateWillNotAddAnyErrorIfEmailIsNotMandatory(): void
    {
        $this->setEmailIsMandatory(false);

        self::assertEquals(
            new Result(),
            $this->subject->validate('hello'),
        );
    }

    /**
     * @test
     */
    public function validateWillNotAddAnyErrorIfEmailIsNotString(): void
    {
        $this->setEmailIsMandatory(true);

        self::assertEquals(
            new Result(),
            $this->subject->validate(123),
        );
    }

    /**
     * @test
     */
    public function validateWillNotAddAnyErrorIfEmailIsValidAndIsString(): void
    {
        $this->setEmailIsMandatory(true);

        self::assertEquals(
            new Result(),
            $this->subject->validate('info@example.com'),
        );
    }

    /**
     * @test
     */
    public function validateWillAddErrorIfEmailIsStringAndEmpty(): void
    {
        $this->setEmailIsMandatory(true);

        $expectedResult = new Result();
        $expectedResult->addError(
            new Error(
                'The email of user object is mandatory',
                1378288238,
            ),
        );

        self::assertEquals(
            $expectedResult,
            $this->subject->validate(''),
        );
    }

    /**
     * @test
     */
    public function validateWillAddErrorIfEmailIsStringAndNotValid(): void
    {
        $this->setEmailIsMandatory(true);

        $expectedResult = new Result();
        $expectedResult->addError(
            new Error(
                'The email of user object is not a valid email',
                1457431804,
            ),
        );

        self::assertEquals(
            $expectedResult,
            $this->subject->validate('hello'),
        );
    }

    protected function setEmailIsMandatory(bool $isMandatory): void
    {
        $this->configurationManagerMock = $this->createMock(ConfigurationManagerInterface::class);
        $this->configurationManagerMock
            ->expects(self::once())
            ->method('getConfiguration')
            ->with(
                ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
                'pforum',
                'forum',
            )
            ->willReturn([
                'emailIsMandatory' => $isMandatory ? '1' : '0',
            ]);

        $this->subject->injectConfigurationManager($this->configurationManagerMock);
    }
}
