<?php

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Domain\Model;

use JWeiland\Pforum\Domain\Model\User;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class UserTest extends UnitTestCase
{
    protected User $subject;

    protected function setUp(): void
    {
        $this->subject = new User();
    }

    protected function tearDown(): void
    {
        unset($this->subject);
    }

    #[Test]
    public function getNameInitiallyReturnsEmptyString()
    {
        self::assertSame(
            '',
            $this->subject->getName(),
        );
    }

    #[Test]
    public function setNameSetsName()
    {
        $this->subject->setName('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getName(),
        );
    }

    #[Test]
    public function setNameWithIntegerResultsInString()
    {
        $this->subject->setName(123);
        self::assertSame('123', $this->subject->getName());
    }

    #[Test]
    public function setNameWithBooleanResultsInString()
    {
        $this->subject->setName(true);
        self::assertSame('1', $this->subject->getName());
    }

    #[Test]
    public function getUsernameInitiallyReturnsEmptyString()
    {
        self::assertSame(
            '',
            $this->subject->getUsername(),
        );
    }

    #[Test]
    public function setUsernameSetsUsername()
    {
        $this->subject->setUsername('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getUsername(),
        );
    }

    #[Test]
    public function setUsernameWithIntegerResultsInString()
    {
        $this->subject->setUsername(123);
        self::assertSame('123', $this->subject->getUsername());
    }

    #[Test]
    public function setUsernameWithBooleanResultsInString()
    {
        $this->subject->setUsername(true);
        self::assertSame('1', $this->subject->getUsername());
    }

    #[Test]
    public function getEmailInitiallyReturnsEmptyString()
    {
        self::assertSame(
            '',
            $this->subject->getEmail(),
        );
    }

    #[Test]
    public function setEmailSetsEmail()
    {
        $this->subject->setEmail('foo bar');

        self::assertSame(
            'foo bar',
            $this->subject->getEmail(),
        );
    }

    #[Test]
    public function setEmailWithIntegerResultsInString()
    {
        $this->subject->setEmail(123);
        self::assertSame('123', $this->subject->getEmail());
    }

    #[Test]
    public function setEmailWithBooleanResultsInString()
    {
        $this->subject->setEmail(true);
        self::assertSame('1', $this->subject->getEmail());
    }
}
