<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\EventListener;

use JWeiland\Pforum\Domain\Model\Topic;
use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\EventListener\AddAnonymousUserValidatorEventListener;
use JWeiland\Pforum\Validation\Validator\AnonymousUserValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Extbase\Mvc\Controller\Argument;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Validation\Validator\ConjunctionValidator;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class AddAnonymousUserValidatorEventListenerTest extends UnitTestCase
{
    private ValidatorResolver&MockObject $validatorResolver;

    private AddAnonymousUserValidatorEventListener $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validatorResolver = $this->createMock(ValidatorResolver::class);
        $this->subject = new AddAnonymousUserValidatorEventListener($this->validatorResolver);
    }

    private function createRequest(string $controllerName, string $actionName): RequestInterface&MockObject
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getControllerName')->willReturn($controllerName);
        $request->method('getControllerActionName')->willReturn($actionName);

        return $request;
    }

    private function createArgument(string $name = 'topic'): Argument
    {
        return new Argument($name, Topic::class);
    }

    private function createArguments(Argument ...$argumentList): Arguments
    {
        $arguments = new Arguments();
        foreach ($argumentList as $argument) {
            $arguments->addArgument($argument);
        }

        return $arguments;
    }

    private function createAnonymousUserValidator(): ValidatorInterface&MockObject
    {
        return $this->createMock(ValidatorInterface::class);
    }

    #[Test]
    public function frontendUserModeSkipsValidator(): void
    {
        $this->validatorResolver->expects($this->never())->method('createValidator');
        $argument = $this->createArgument();

        ($this->subject)(new PreProcessControllerActionEvent(
            $this->createRequest('Topic', 'create'),
            $this->createArguments($argument),
            ['auth' => '2'],
        ));

        self::assertNull($argument->getValidator());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function skippedRequestDataProvider(): array
    {
        return [
            'wrong action' => ['Topic', 'show'],
            'wrong controller' => ['Forum', 'create'],
            'unknown controller' => ['Foo', 'create'],
        ];
    }

    #[Test]
    #[DataProvider('skippedRequestDataProvider')]
    public function otherControllerActionsSkipValidator(string $controller, string $action): void
    {
        $this->validatorResolver->expects($this->never())->method('createValidator');
        $argument = $this->createArgument();

        ($this->subject)(new PreProcessControllerActionEvent(
            $this->createRequest($controller, $action),
            $this->createArguments($argument),
            ['auth' => '1'],
        ));

        self::assertNull($argument->getValidator());
    }

    #[Test]
    public function missingArgumentSkipsValidator(): void
    {
        $this->validatorResolver->expects($this->never())->method('createValidator');
        $argument = $this->createArgument('post');

        ($this->subject)(new PreProcessControllerActionEvent(
            $this->createRequest('Topic', 'create'),
            $this->createArguments($argument),
            ['auth' => '1'],
        ));

        self::assertNull($argument->getValidator());
    }

    #[Test]
    public function validatorIsAddedToExistingConjunctionValidator(): void
    {
        $request = $this->createRequest('Topic', 'update');
        $anonymousUserValidator = $this->createAnonymousUserValidator();
        $this->validatorResolver
            ->expects($this->once())
            ->method('createValidator')
            ->with(AnonymousUserValidator::class, ['usernameIsMandatory' => true, 'emailIsMandatory' => false], $request)
            ->willReturn($anonymousUserValidator);
        $conjunctionValidator = new ConjunctionValidator();
        $argument = $this->createArgument();
        $argument->setValidator($conjunctionValidator);

        ($this->subject)(new PreProcessControllerActionEvent(
            $request,
            $this->createArguments($argument),
            ['auth' => '1', 'usernameIsMandatory' => '1', 'emailIsMandatory' => '0'],
        ));

        self::assertSame($conjunctionValidator, $argument->getValidator());
        self::assertSame([$anonymousUserValidator], iterator_to_array($conjunctionValidator->getValidators(), false));
    }

    #[Test]
    public function newConjunctionValidatorWrapsExistingValidator(): void
    {
        $request = $this->createRequest('Post', 'create');
        $existingValidator = $this->createMock(ValidatorInterface::class);
        $anonymousUserValidator = $this->createAnonymousUserValidator();
        $this->validatorResolver
            ->expects($this->once())
            ->method('createValidator')
            ->with(AnonymousUserValidator::class, ['usernameIsMandatory' => false, 'emailIsMandatory' => true], $request)
            ->willReturn($anonymousUserValidator);
        $argument = $this->createArgument('post');
        $argument->setValidator($existingValidator);

        ($this->subject)(new PreProcessControllerActionEvent(
            $request,
            $this->createArguments($argument),
            ['emailIsMandatory' => '1'],
        ));

        $validator = $argument->getValidator();
        self::assertInstanceOf(ConjunctionValidator::class, $validator);
        self::assertNotSame($existingValidator, $validator);
        self::assertSame(
            [$existingValidator, $anonymousUserValidator],
            iterator_to_array($validator->getValidators(), false),
        );
    }

    #[Test]
    public function newConjunctionValidatorContainsOnlyAnonymousUserValidatorWithoutExistingValidator(): void
    {
        $anonymousUserValidator = $this->createAnonymousUserValidator();
        $this->validatorResolver->method('createValidator')->willReturn($anonymousUserValidator);
        $argument = $this->createArgument();

        ($this->subject)(new PreProcessControllerActionEvent(
            $this->createRequest('Topic', 'create'),
            $this->createArguments($argument),
            [],
        ));

        $validator = $argument->getValidator();
        self::assertInstanceOf(ConjunctionValidator::class, $validator);
        self::assertSame([$anonymousUserValidator], iterator_to_array($validator->getValidators(), false));
    }
}
