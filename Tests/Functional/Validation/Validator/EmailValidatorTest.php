<?php

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Validation\Validator;

use JWeiland\Pforum\Validation\Validator\EmailValidator;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Validation\Error;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case
 */
class EmailValidatorTest extends FunctionalTestCase
{
    protected EmailValidator $subject;

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

    #[Test]
    public function validateWillNotAddAnyErrorIfEmailIsNotMandatory(): void
    {
        $this->setEmailIsMandatory(false);

        self::assertEquals(
            new Result(),
            $this->subject->validate('hello'),
        );
    }

    #[Test]
    public function validateWillAddErrorIfEmailIsNotString(): void
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
            $this->subject->validate(123),
        );
    }

    #[Test]
    public function validateWillNotAddAnyErrorIfEmailIsValidAndIsString(): void
    {
        $this->setEmailIsMandatory(true);

        self::assertEquals(
            new Result(),
            $this->subject->validate('info@example.com'),
        );
    }

    #[Test]
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

    #[Test]
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
        $this->subject->setOptions([
            'emailIsMandatory' => $isMandatory,
        ]);
    }
}
