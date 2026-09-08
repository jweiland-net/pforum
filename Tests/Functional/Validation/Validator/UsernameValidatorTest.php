<?php

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Functional\Validation\Validator;

use JWeiland\Pforum\Validation\Validator\UsernameValidator;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Validation\Error;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test case
 */
class UsernameValidatorTest extends FunctionalTestCase
{
    protected UsernameValidator $subject;

    protected array $testExtensionsToLoad = [
        'jweiland/pforum',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['LANG'] = $this->createMock(LanguageService::class);

        $this->subject = new UsernameValidator();
    }

    protected function tearDown(): void
    {
        unset($this->subject);
        parent::tearDown();
    }

    #[Test]
    public function validateWillNotAddAnyErrorIfUsernameIsNotMandatory(): void
    {
        $this->setUsernameIsMandatory(false);

        self::assertEquals(
            new Result(),
            $this->subject->validate(''),
        );
    }

    #[Test]
    public function validateWillNotAddAnyErrorIfUsernameIsNotString(): void
    {
        $this->setUsernameIsMandatory(true);

        self::assertEquals(
            new Result(),
            $this->subject->validate(123),
        );
    }

    #[Test]
    public function validateWillNotAddAnyErrorIfUsernameIsNotEmpty(): void
    {
        $this->setUsernameIsMandatory(true);

        self::assertEquals(
            new Result(),
            $this->subject->validate('stefan'),
        );
    }

    #[Test]
    public function validateWillAddErrorIfUsernameIsEmpty(): void
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

    protected function setUsernameIsMandatory(bool $isMandatory): void
    {
        $this->subject->setOptions([
            'usernameIsMandatory' => $isMandatory,
        ]);
    }
}
