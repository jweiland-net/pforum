<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Event;

use JWeiland\Pforum\Event\PreProcessControllerActionEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class PreProcessControllerActionEventTest extends UnitTestCase
{
    private RequestInterface&MockObject $request;

    private Arguments $arguments;

    private PreProcessControllerActionEvent $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getControllerName')->willReturn('Topic');
        $this->request->method('getControllerActionName')->willReturn('create');
        $this->arguments = new Arguments();

        $this->subject = new PreProcessControllerActionEvent($this->request, $this->arguments, ['auth' => '2']);
    }

    #[Test]
    public function gettersReturnConstructorArguments(): void
    {
        self::assertSame($this->request, $this->subject->getRequest());
        self::assertSame($this->arguments, $this->subject->getArguments());
        self::assertSame(['auth' => '2'], $this->subject->getSettings());
    }

    #[Test]
    public function controllerAndActionNameAreTakenFromRequest(): void
    {
        self::assertSame('Topic', $this->subject->getControllerName());
        self::assertSame('create', $this->subject->getActionName());
    }

    #[Test]
    public function setRequestReplacesRequestAndControllerAndActionName(): void
    {
        $newRequest = $this->createMock(RequestInterface::class);
        $newRequest->method('getControllerName')->willReturn('Post');
        $newRequest->method('getControllerActionName')->willReturn('update');

        $this->subject->setRequest($newRequest);

        self::assertSame($newRequest, $this->subject->getRequest());
        self::assertSame('Post', $this->subject->getControllerName());
        self::assertSame('update', $this->subject->getActionName());
    }

    #[Test]
    public function setArgumentsReplacesArguments(): void
    {
        $newArguments = new Arguments();

        $this->subject->setArguments($newArguments);

        self::assertSame($newArguments, $this->subject->getArguments());
        self::assertNotSame($this->arguments, $this->subject->getArguments());
    }
}
