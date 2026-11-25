<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\EventListener;

use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use JWeiland\Pforum\EventListener\CheckFalUploadsEventListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Extbase\Mvc\Controller\Argument;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\Controller\FileUploadConfiguration;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Validation\Validator\ValidatorInterface;
use TYPO3\CMS\Extbase\Validation\ValidatorResolver;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class CheckFalUploadsEventListenerTest extends UnitTestCase
{
    private const VALIDATOR_CLASS = 'JWeiland\\Checkfaluploads\\Validation\\Validator\\CheckFalUploadValidator';

    private ValidatorResolver&MockObject $validatorResolver;

    private PackageManager&MockObject $packageManager;

    private CheckFalUploadsEventListener $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validatorResolver = $this->createMock(ValidatorResolver::class);
        $this->packageManager = $this->createMock(PackageManager::class);
        $this->subject = new CheckFalUploadsEventListener($this->validatorResolver, $this->packageManager);
    }

    private function createRequest(string $controllerName, string $actionName): RequestInterface&MockObject
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getControllerName')->willReturn($controllerName);
        $request->method('getControllerActionName')->willReturn($actionName);

        return $request;
    }

    private function createArguments(string $argumentName, FileUploadConfiguration $configuration): Arguments
    {
        $argument = new Argument($argumentName, 'string');
        $argument->getFileHandlingServiceConfiguration()->addFileUploadConfiguration($configuration);
        $arguments = new Arguments();
        $arguments->addArgument($argument);

        return $arguments;
    }

    #[Test]
    public function nothingHappensIfCheckfaluploadsIsNotActive(): void
    {
        $this->packageManager->expects($this->once())->method('isPackageActive')->with('checkfaluploads')->willReturn(false);
        $this->validatorResolver->expects($this->never())->method('createValidator');
        $configuration = new FileUploadConfiguration('images');

        ($this->subject)(new PreProcessControllerActionEvent(
            $this->createRequest('Topic', 'create'),
            $this->createArguments('topic', $configuration),
            [],
        ));

        self::assertSame([], $configuration->getValidators());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ignoredActionDataProvider(): array
    {
        return [
            'wrong action' => ['Topic', 'show'],
            'wrong controller' => ['Forum', 'create'],
            'new action' => ['Topic', 'new'],
        ];
    }

    #[Test]
    #[DataProvider('ignoredActionDataProvider')]
    public function nothingHappensForOtherControllerActions(string $controller, string $action): void
    {
        $this->packageManager->method('isPackageActive')->willReturn(true);
        $this->validatorResolver->expects($this->never())->method('createValidator');
        $configuration = new FileUploadConfiguration('images');

        ($this->subject)(new PreProcessControllerActionEvent(
            $this->createRequest($controller, $action),
            $this->createArguments('topic', $configuration),
            [],
        ));

        self::assertSame([], $configuration->getValidators());
    }

    #[Test]
    public function nothingHappensIfArgumentDoesNotExist(): void
    {
        $this->packageManager->method('isPackageActive')->willReturn(true);
        $this->validatorResolver->expects($this->never())->method('createValidator');
        $configuration = new FileUploadConfiguration('images');

        ($this->subject)(new PreProcessControllerActionEvent(
            $this->createRequest('Topic', 'create'),
            $this->createArguments('post', $configuration),
            [],
        ));

        self::assertSame([], $configuration->getValidators());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function validActionDataProvider(): array
    {
        return [
            'topic create' => ['Topic', 'create', 'topic'],
            'topic update' => ['Topic', 'update', 'topic'],
            'post create' => ['Post', 'create', 'post'],
            'post update' => ['Post', 'update', 'post'],
        ];
    }

    #[Test]
    #[DataProvider('validActionDataProvider')]
    public function validatorIsAddedToImagesConfiguration(string $controller, string $action, string $argumentName): void
    {
        $validator = self::createStub(ValidatorInterface::class);
        $request = $this->createRequest($controller, $action);
        $this->packageManager->method('isPackageActive')->with('checkfaluploads')->willReturn(true);
        $this->validatorResolver
            ->expects($this->once())
            ->method('createValidator')
            ->with(self::VALIDATOR_CLASS, ['propertyPath' => $argumentName . '.images'], $request)
            ->willReturn($validator);
        $configuration = new FileUploadConfiguration('images');

        ($this->subject)(new PreProcessControllerActionEvent(
            $request,
            $this->createArguments($argumentName, $configuration),
            [],
        ));

        self::assertSame([$validator], $configuration->getValidators());
    }

    #[Test]
    public function missingImagesConfigurationDoesNotFail(): void
    {
        $this->packageManager->method('isPackageActive')->willReturn(true);
        $this->validatorResolver->method('createValidator')->willReturn(self::createStub(ValidatorInterface::class));
        $configuration = new FileUploadConfiguration('documents');

        ($this->subject)(new PreProcessControllerActionEvent(
            $this->createRequest('Topic', 'create'),
            $this->createArguments('topic', $configuration),
            [],
        ));

        self::assertSame([], $configuration->getValidators());
    }
}
