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
use JWeiland\Pforum\EventListener\ApplyUploadFolderEventListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Mvc\Controller\Argument;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\Controller\FileUploadConfiguration;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class ApplyUploadFolderEventListenerTest extends UnitTestCase
{
    private ApplyUploadFolderEventListener $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ApplyUploadFolderEventListener();
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function createEvent(string $controllerName, string $actionName, Arguments $arguments, array $settings): PreProcessControllerActionEvent
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getControllerName')->willReturn($controllerName);
        $request->method('getControllerActionName')->willReturn($actionName);

        return new PreProcessControllerActionEvent($request, $arguments, $settings);
    }

    private function createArguments(string $argumentName, ?FileUploadConfiguration $configuration): Arguments
    {
        $argument = new Argument($argumentName, 'string');
        if ($configuration instanceof FileUploadConfiguration) {
            $argument->getFileHandlingServiceConfiguration()->addFileUploadConfiguration($configuration);
        }
        $arguments = new Arguments();
        $arguments->addArgument($argument);

        return $arguments;
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function validRequestDataProvider(): array
    {
        return [
            'topic create' => ['Topic', 'create', 'topic'],
            'topic update' => ['Topic', 'update', 'topic'],
            'post create' => ['Post', 'create', 'post'],
            'post update' => ['Post', 'update', 'post'],
        ];
    }

    #[Test]
    #[DataProvider('validRequestDataProvider')]
    public function uploadFolderOfSettingsIsAppliedToImages(string $controller, string $action, string $argumentName): void
    {
        $configuration = new FileUploadConfiguration('images');
        $configuration->setUploadFolder('1:/default/');
        $event = $this->createEvent(
            $controller,
            $action,
            $this->createArguments($argumentName, $configuration),
            ['new' => ['uploadFolder' => '1:/pforum/']],
        );

        ($this->subject)($event);

        self::assertSame('1:/pforum/', $configuration->getUploadFolder());
    }

    /**
     * @return array<string, array{string, string, array<string, mixed>}>
     */
    public static function ignoredRequestDataProvider(): array
    {
        $settings = ['new' => ['uploadFolder' => '1:/pforum/']];

        return [
            'wrong action' => ['Topic', 'show', $settings],
            'wrong controller' => ['Forum', 'create', $settings],
            'no upload folder' => ['Topic', 'create', []],
            'empty upload folder' => ['Topic', 'create', ['new' => ['uploadFolder' => '']]],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    #[Test]
    #[DataProvider('ignoredRequestDataProvider')]
    public function uploadFolderIsNotChangedIfRequestOrSettingsDoNotMatch(string $controller, string $action, array $settings): void
    {
        $configuration = new FileUploadConfiguration('images');
        $configuration->setUploadFolder('1:/default/');
        $event = $this->createEvent($controller, $action, $this->createArguments('topic', $configuration), $settings);

        ($this->subject)($event);

        self::assertSame('1:/default/', $configuration->getUploadFolder());
    }

    #[Test]
    public function uploadFolderIsNotChangedIfArgumentDoesNotExist(): void
    {
        $configuration = new FileUploadConfiguration('images');
        $configuration->setUploadFolder('1:/default/');
        $event = $this->createEvent(
            'Topic',
            'create',
            $this->createArguments('post', $configuration),
            ['new' => ['uploadFolder' => '1:/pforum/']],
        );

        ($this->subject)($event);

        self::assertSame('1:/default/', $configuration->getUploadFolder());
    }

    #[Test]
    public function missingImagesConfigurationDoesNotFail(): void
    {
        $arguments = $this->createArguments('topic', null);
        $event = $this->createEvent('Topic', 'create', $arguments, ['new' => ['uploadFolder' => '1:/pforum/']]);

        ($this->subject)($event);

        self::assertNull(
            $arguments->getArgument('topic')->getFileHandlingServiceConfiguration()->getFileUploadConfigurationForProperty('images'),
        );
    }

    #[Test]
    public function otherPropertiesAreNotChanged(): void
    {
        $other = new FileUploadConfiguration('documents');
        $other->setUploadFolder('1:/documents/');
        $arguments = $this->createArguments('topic', new FileUploadConfiguration('images'));
        $arguments->getArgument('topic')->getFileHandlingServiceConfiguration()->addFileUploadConfiguration($other);

        ($this->subject)($this->createEvent('Topic', 'create', $arguments, ['new' => ['uploadFolder' => '1:/pforum/']]));

        self::assertSame('1:/documents/', $other->getUploadFolder());
    }
}
