<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/pforum.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Pforum\Tests\Unit\Event;

use JWeiland\Pforum\Event\PostProcessFluidVariablesEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 */
class PostProcessFluidVariablesEventTest extends UnitTestCase
{
    private RequestInterface&MockObject $request;

    private PostProcessFluidVariablesEvent $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(RequestInterface::class);
        $this->request->method('getControllerName')->willReturn('Forum');
        $this->request->method('getControllerActionName')->willReturn('list');

        $this->subject = new PostProcessFluidVariablesEvent($this->request, ['auth' => '1'], ['forums' => [1, 2]]);
    }

    #[Test]
    public function gettersReturnConstructorArguments(): void
    {
        self::assertSame($this->request, $this->subject->getRequest());
        self::assertSame(['auth' => '1'], $this->subject->getSettings());
        self::assertSame(['forums' => [1, 2]], $this->subject->getFluidVariables());
    }

    #[Test]
    public function controllerAndActionNameAreTakenFromRequest(): void
    {
        self::assertSame('Forum', $this->subject->getControllerName());
        self::assertSame('list', $this->subject->getActionName());
    }

    #[Test]
    public function addFluidVariableAddsNewVariable(): void
    {
        $this->subject->addFluidVariable('paginator', 'foo');

        self::assertSame(['forums' => [1, 2], 'paginator' => 'foo'], $this->subject->getFluidVariables());
    }

    #[Test]
    public function addFluidVariableOverridesExistingVariable(): void
    {
        $this->subject->addFluidVariable('forums', []);

        self::assertSame(['forums' => []], $this->subject->getFluidVariables());
    }
}
